<?php
/**
 * Administrator Voter Roll & Eligibility Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Voter Roll Registry';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$error = '';

// Handle Actions (Add, Status Toggle, Reset Password, Delete, Batch Enroll)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $collegeRoll = trim($_POST['college_roll_no'] ?? '');
            $univRegNo = trim($_POST['university_reg_no'] ?? '');
            $voterId = $collegeRoll ?: trim($_POST['voter_id_number'] ?? '');
            $department = trim($_POST['department'] ?? 'Computer Applications');
            $course = trim($_POST['course'] ?? 'BCA');
            $semester = (int)($_POST['semester'] ?? 1);
            $session = get_setting('academic_year', '2026–2027');
            $role = ($_POST['role'] === 'admin') ? 'admin' : 'voter';
            $password = (string)($_POST['password'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($fullName) || empty($email) || empty($voterId) || empty($password)) {
                $error = 'All required student fields must be filled.';
            } else {
                // Check uniqueness
                $check = $db->prepare("SELECT id FROM users WHERE email = ? OR voter_id_number = ? OR college_roll_no = ? LIMIT 1");
                $check->execute([$email, $voterId, $collegeRoll]);
                if ($check->fetch()) {
                    $error = 'A student with this email or College Roll Number is already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $formattedPhone = !empty($phone) ? format_indian_phone($phone) : '';
                    $stmt = $db->prepare("
                        INSERT INTO users (full_name, email, voter_id_number, college_roll_no, university_reg_no, password_hash, role, department, course, semester, academic_session, phone, status, avatar_url, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'default-avatar.svg', NOW())
                    ");
                    $stmt->execute([$fullName, $email, $voterId, $collegeRoll, $univRegNo, $hash, $role, $department, $course, $semester, $session, $formattedPhone]);
                    $newId = (int)$db->lastInsertId();

                    // Enroll into active global elections automatically
                    $gElections = $db->query("SELECT id FROM elections WHERE status IN ('active', 'draft') AND eligibility_type = 'all'")->fetchAll(PDO::FETCH_COLUMN);
                    $eInsert = $db->prepare("INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, eligibility_status, created_at) VALUES (?, ?, 0, 'eligible', NOW())");
                    foreach ($gElections as $eId) {
                        $eInsert->execute([$eId, $newId]);
                    }

                    audit_log('VOTER_ENROLLED_BY_ADMIN', "Admin enrolled student #{$newId}: {$email} ({$collegeRoll})", $user['id']);
                    set_flash('success', "Student '{$fullName}' ({$collegeRoll}) enrolled into the voter registry!");
                    redirect('admin/manage-voters.php');
                }
            }
        } elseif ($action === 'toggle_status') {
            $targetUserId = (int)$_POST['target_user_id'];
            $newStatus = ($_POST['current_status'] === 'active') ? 'suspended' : 'active';
            
            // Prevent self-suspension
            if ($targetUserId === (int)$user['id']) {
                $error = 'You cannot suspend your own administrative session.';
            } else {
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $targetUserId]);

                audit_log('USER_STATUS_CHANGE', "Changed user #{$targetUserId} status to {$newStatus}", $user['id']);
                set_flash('success', "User account status updated to " . strtoupper($newStatus));
                redirect('admin/manage-voters.php');
            }
        } elseif ($action === 'reset_password') {
            $targetUserId = (int)$_POST['target_user_id'];
            $newPassword = (string)$_POST['new_password'];

            if (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $targetUserId]);

                audit_log('ADMIN_RESET_PASSWORD', "Admin reset password for user #{$targetUserId}", $user['id']);
                set_flash('success', "Password successfully reset for user #{$targetUserId}.");
                redirect('admin/manage-voters.php');
            }
        } elseif ($action === 'batch_enroll') {
            $electionId = (int)$_POST['election_id'];
            $department = trim($_POST['target_dept'] ?? 'all');

            $sql = "SELECT id FROM users WHERE role = 'voter' AND status = 'active'";
            $params = [];
            if ($department !== 'all') {
                $sql .= " AND department = ?";
                $params[] = $department;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $ins = $db->prepare("INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, eligibility_status, created_at) VALUES (?, ?, 0, 'eligible', NOW())");
            $count = 0;
            foreach ($userIds as $uid) {
                if ($ins->execute([$electionId, $uid])) {
                    $count += $ins->rowCount();
                }
            }

            audit_log('BATCH_ELIGIBILITY_ENROLL', "Enrolled {$count} voters into election #{$electionId}", $user['id']);
            set_flash('success', "Successfully granted eligibility to {$count} voters for election #{$electionId}!");
            redirect('admin/manage-voters.php');
        } elseif ($action === 'delete') {
            $targetUserId = (int)$_POST['target_user_id'];
            if ($targetUserId === (int)$user['id']) {
                $error = 'You cannot delete your own administrative account.';
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$targetUserId]);

                audit_log('USER_DELETED', "Deleted user #{$targetUserId}", $user['id']);
                set_flash('success', "Voter record permanently deleted.");
                redirect('admin/manage-voters.php');
            }
        }
    }
}

// Fetch all registered voters with voting count
$voters = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT ve.id) as eligible_count,
           SUM(CASE WHEN ve.has_voted = 1 THEN 1 ELSE 0 END) as votes_cast
    FROM users u
    LEFT JOIN voter_eligibility ve ON u.id = ve.user_id
    GROUP BY u.id
    ORDER BY u.role DESC, u.created_at DESC
")->fetchAll();

$electionsList = $db->query("SELECT id, title, category FROM elections WHERE status IN ('draft', 'active')")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <h2>Collegiate Voter Roll Registry</h2>
        <p>Enroll verified voters, monitor balloting eligibility, and manage access authorizations.</p>
    </div>

    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <button type="button" class="btn btn-secondary" data-modal-target="#batch-enroll-modal">
            ⚡ Batch Assign Eligibility
        </button>
        <button type="button" class="btn btn-primary" data-modal-target="#voter-form-modal">
            + Enroll New Voter
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger">
        <span>⚠️</span>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- Search Bar -->
<div style="margin-bottom: 1.5rem; max-width: 380px;">
    <input type="text" data-table-filter="#voters-table" placeholder="🔍 Search by name, email, or voter ID..." class="form-control">
</div>

<!-- Voter Registry Table -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table id="voters-table" class="custom-table">
            <thead>
                <tr>
                    <th>Voter ID</th>
                    <th>Full Name & Email</th>
                    <th>Department</th>
                    <th>System Role</th>
                    <th>Ballots Cast</th>
                    <th>Account Status</th>
                    <th>Management Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($voters as $v): ?>
                    <tr>
                        <td>
                            <code style="color: var(--color-primary); font-weight: 700; background: rgba(244, 203, 69, 0.12); border: 1px solid rgba(244, 203, 69, 0.25); padding: 0.2rem 0.5rem; border-radius: 4px;">
                                <?= e($v['voter_id_number']) ?>
                            </code>
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); font-size: 0.95rem; display: block;"><?= e($v['full_name']) ?></strong>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($v['email']) ?></span>
                        </td>
                        <td>
                            <span style="font-size: 0.88rem;"><?= e($v['department']) ?></span>
                        </td>
                        <td>
                            <?php if ($v['role'] === 'admin'): ?>
                                <span class="status-badge badge-amber">Administrator</span>
                            <?php else: ?>
                                <span class="status-badge badge-blue">Collegiate Voter</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= (int)$v['votes_cast'] ?></strong> / <?= (int)$v['eligible_count'] ?> Ballots
                        </td>
                        <td>
                            <?php if ($v['status'] === 'active'): ?>
                                <span class="status-badge badge-emerald">Active</span>
                            <?php elseif ($v['status'] === 'suspended'): ?>
                                <span class="status-badge badge-rose">Suspended</span>
                            <?php else: ?>
                                <span class="status-badge badge-slate"><?= e($v['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                <!-- Toggle Status -->
                                <form action="<?= url('admin/manage-voters.php') ?>" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="target_user_id" value="<?= (int)$v['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= e($v['status']) ?>">
                                    <button type="submit" class="btn btn-sm <?= ($v['status'] === 'active') ? 'btn-secondary' : 'btn-emerald' ?>" title="Toggle account status">
                                        <?= ($v['status'] === 'active') ? '⏸' : '▶' ?>
                                    </button>
                                </form>

                                <!-- Reset Password Trigger -->
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        onclick="openResetModal(<?= (int)$v['id'] ?>, '<?= addslashes($v['full_name']) ?>')" title="Reset Password">
                                    🔑
                                </button>

                                <!-- Delete User -->
                                <?php if ($v['id'] !== $user['id']): ?>
                                    <form action="<?= url('admin/manage-voters.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete <?= addslashes($v['full_name']) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="target_user_id" value="<?= (int)$v['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete User">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Enroll Voter Modal -->
<div id="voter-form-modal" class="modal-overlay <?= isset($_GET['action']) && $_GET['action'] === 'new' ? 'open' : '' ?>">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Enroll New Collegiate Voter</h3>
            <button type="button" data-close-modal style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/manage-voters.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Legal / Student Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="e.g. Rahul Das">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="college_roll_no">College Roll Number *</label>
                        <input type="text" name="college_roll_no" id="college_roll_no" class="form-control" required placeholder="e.g. BCA/2026/041">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="university_reg_no">University Registration No. *</label>
                        <input type="text" name="university_reg_no" id="university_reg_no" class="form-control" required placeholder="e.g. 12623010041">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="email">Institutional Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required placeholder="student@college.edu.in">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Mobile Number (+91) *</label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="9830123456" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="department">Department</label>
                        <select name="department" id="department" class="form-select">
                            <option value="Computer Applications">Computer Applications</option>
                            <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Commerce">Commerce</option>
                            <option value="Electronics & Communication">Electronics & Communication</option>
                            <option value="Basic Sciences & Humanities">Basic Sciences & Humanities</option>
                            <option value="College Election Committee">College Election Committee</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="course">Course</label>
                        <select name="course" id="course" class="form-select">
                            <option value="BCA">BCA (Bachelor of Computer Applications)</option>
                            <option value="B.Tech CSE">B.Tech CSE</option>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="BBA">BBA</option>
                            <option value="B.Com (Hons.)">B.Com (Hons.)</option>
                            <option value="MCA">MCA</option>
                            <option value="M.Sc Computer Science">M.Sc Computer Science</option>
                            <option value="Faculty Administration">Faculty Administration</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="semester">Semester</label>
                        <select name="semester" id="semester" class="form-select">
                            <?php for ($s = 1; $s <= 8; $s++): ?>
                                <option value="<?= $s ?>">Semester <?= $s ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="role">User Role</label>
                        <select name="role" id="role" class="form-select">
                            <option value="voter">Student Voter</option>
                            <option value="admin">Election Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Initial Password *</label>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-close-modal class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Enroll Voter &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Assign Eligibility Modal -->
<div id="batch-enroll-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3>Batch Assign Election Eligibility</h3>
            <button type="button" data-close-modal style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/manage-voters.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="batch_enroll">

            <div class="modal-body">
                <p style="font-size: 0.9rem; margin-bottom: 1.25rem;">
                    Select an active or upcoming election to bulk-assign eligible voting rights to enrolled students.
                </p>

                <div class="form-group">
                    <label class="form-label" for="enroll_election_id">Target Election *</label>
                    <select name="election_id" id="enroll_election_id" class="form-select" required>
                        <?php foreach ($electionsList as $el): ?>
                            <option value="<?= (int)$el['id'] ?>">
                                <?= e($el['title']) ?> (<?= e($el['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="target_dept">Target Cohort / Department</label>
                    <select name="target_dept" id="target_dept" class="form-select">
                        <option value="all">All Enrolled Active Voters</option>
                        <option value="Computer Science">Computer Science Only</option>
                        <option value="Electrical Engineering">Electrical Engineering Only</option>
                        <option value="Mechanical Engineering">Mechanical Engineering Only</option>
                        <option value="Business Administration">Business Administration Only</option>
                        <option value="Information Technology">Information Technology Only</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-close-modal class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Eligibility &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Reset Voter Password</h3>
            <button type="button" data-close-modal style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/manage-voters.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_user_id" id="reset-user-id" value="">

            <div class="modal-body">
                <p style="font-size: 0.9rem; margin-bottom: 1rem;">
                    Resetting password for: <strong id="reset-user-name" style="color: var(--color-primary);">[User]</strong>
                </p>

                <div class="form-group">
                    <label class="form-label" for="reset_new_password">New Temporary Password *</label>
                    <input type="text" name="new_password" id="reset_new_password" class="form-control" value="Voter@123" required>
                    <span class="form-help">Default suggestion: Voter@123</span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-close-modal class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Reset &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(userId, userName) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-user-name').innerText = userName;
    document.getElementById('reset-password-modal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
