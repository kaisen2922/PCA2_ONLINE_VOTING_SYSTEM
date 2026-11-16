<?php
/**
 * Administrator Election Management
 * Add, edit, delete, start, end, and assign eligibility
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Manage Elections';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$error = '';
$editElection = null;

// Check if editing
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editElection = $stmt->fetch();
}

// Handle Form Submissions & Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'Student Council');
            $description = trim($_POST['description'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '');
            $endTime = trim($_POST['end_time'] ?? '');
            $status = $_POST['status'] ?? 'draft';
            $eligibilityType = $_POST['eligibility_type'] ?? 'all';
            $targetDepartment = ($eligibilityType === 'department') ? trim($_POST['target_department'] ?? '') : null;
            $resultsPublished = isset($_POST['results_published']) ? 1 : 0;

            if (empty($title) || empty($description) || empty($startTime) || empty($endTime)) {
                $error = 'Please complete all required election fields.';
            } else {
                if ($action === 'create') {
                    $stmt = $db->prepare("
                        INSERT INTO elections (title, category, description, start_time, end_time, status, eligibility_type, target_department, results_published, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $category, $description, $startTime, $endTime, $status, $eligibilityType, $targetDepartment, $resultsPublished]);
                    $newElectionId = (int)$db->lastInsertId();

                    // Automatically enroll eligible voters
                    $userSql = "SELECT id FROM users WHERE role = 'voter' AND status = 'active'";
                    $params = [];
                    if ($eligibilityType === 'department' && !empty($targetDepartment)) {
                        $userSql .= " AND department = ?";
                        $params[] = $targetDepartment;
                    }
                    $uStmt = $db->prepare($userSql);
                    $uStmt->execute($params);
                    $eligibleUsers = $uStmt->fetchAll(PDO::FETCH_COLUMN);

                    $eligInsert = $db->prepare("INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, eligibility_status, created_at) VALUES (?, ?, 0, 'eligible', NOW())");
                    foreach ($eligibleUsers as $uId) {
                        $eligInsert->execute([$newElectionId, $uId]);
                    }

                    audit_log('ELECTION_CREATED', "Created election #{$newElectionId}: {$title}", $user['id']);
                    set_flash('success', "Election '{$title}' created with " . count($eligibleUsers) . " enrolled voters!");
                    redirect('admin/manage-elections.php');
                } else {
                    $electionId = (int)($_POST['election_id'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE elections 
                        SET title = ?, category = ?, description = ?, start_time = ?, end_time = ?, status = ?, eligibility_type = ?, target_department = ?, results_published = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $category, $description, $startTime, $endTime, $status, $eligibilityType, $targetDepartment, $resultsPublished, $electionId]);

                    audit_log('ELECTION_UPDATED', "Updated election #{$electionId}: {$title}", $user['id']);
                    set_flash('success', "Election updated successfully!");
                    redirect('admin/manage-elections.php');
                }
            }
        } elseif ($action === 'start') {
            $electionId = (int)$_POST['election_id'];
            $stmt = $db->prepare("UPDATE elections SET status = 'active', start_time = LEAST(start_time, NOW()) WHERE id = ?");
            $stmt->execute([$electionId]);
            audit_log('ELECTION_STARTED', "Started voting for election #{$electionId}", $user['id']);
            set_flash('success', "Election #{$electionId} is now LIVE for voter balloting!");
            redirect('admin/manage-elections.php');
        } elseif ($action === 'close') {
            $electionId = (int)$_POST['election_id'];
            $stmt = $db->prepare("UPDATE elections SET status = 'completed', end_time = NOW() WHERE id = ?");
            $stmt->execute([$electionId]);
            audit_log('ELECTION_CLOSED', "Closed voting for election #{$electionId}", $user['id']);
            set_flash('info', "Election #{$electionId} has been closed.");
            redirect('admin/manage-elections.php');
        } elseif ($action === 'toggle_results') {
            $electionId = (int)$_POST['election_id'];
            $curr = (int)$_POST['current_val'];
            $newVal = ($curr == 1) ? 0 : 1;
            $stmt = $db->prepare("UPDATE elections SET results_published = ? WHERE id = ?");
            $stmt->execute([$newVal, $electionId]);
            audit_log('RESULTS_TOGGLED', "Results publication set to {$newVal} for election #{$electionId}", $user['id']);
            set_flash('success', "Results visibility updated for Election #{$electionId}!");
            redirect('admin/manage-elections.php');
        } elseif ($action === 'sync_voters') {
            $electionId = (int)$_POST['election_id'];
            $eStmt = $db->prepare("SELECT eligibility_type, target_department FROM elections WHERE id = ?");
            $eStmt->execute([$electionId]);
            $el = $eStmt->fetch();

            $userSql = "SELECT id FROM users WHERE role = 'voter' AND status = 'active'";
            $params = [];
            if ($el['eligibility_type'] === 'department' && !empty($el['target_department'])) {
                $userSql .= " AND department = ?";
                $params[] = $el['target_department'];
            }
            $uStmt = $db->prepare($userSql);
            $uStmt->execute($params);
            $eligibleUsers = $uStmt->fetchAll(PDO::FETCH_COLUMN);

            $eligInsert = $db->prepare("INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, eligibility_status, created_at) VALUES (?, ?, 0, 'eligible', NOW())");
            $count = 0;
            foreach ($eligibleUsers as $uId) {
                if ($eligInsert->execute([$electionId, $uId])) {
                    $count += $eligInsert->rowCount();
                }
            }

            audit_log('VOTER_SYNC', "Synced {$count} eligible voters into Election #{$electionId}", $user['id']);
            set_flash('success', "Voter roll synchronized! {$count} new voter records enrolled.");
            redirect('admin/manage-elections.php');
        } elseif ($action === 'delete') {
            $electionId = (int)$_POST['election_id'];
            $stmt = $db->prepare("DELETE FROM elections WHERE id = ?");
            $stmt->execute([$electionId]);
            audit_log('ELECTION_DELETED', "Deleted election #{$electionId}", $user['id']);
            set_flash('success', "Election #{$electionId} and associated records removed.");
            redirect('admin/manage-elections.php');
        }
    }
}

// Fetch all elections
$elections = $db->query("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           COUNT(DISTINCT ve.id) as eligible_count,
           SUM(CASE WHEN ve.has_voted = 1 THEN 1 ELSE 0 END) as vote_count
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN voter_eligibility ve ON e.id = ve.election_id
    GROUP BY e.id
    ORDER BY FIELD(e.status, 'active', 'draft', 'completed', 'cancelled'), e.end_time DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Collegiate Elections Management</h2>
        <p>Create, configure, monitor, and certify campus electoral contests.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#election-form-modal">
        + Create New Election
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger">
        <span>⚠️</span>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- Elections List Table -->
<div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 3rem;">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Election Title & Category</th>
                    <th>Timing Window</th>
                    <th>Nominees</th>
                    <th>Turnout</th>
                    <th>Status</th>
                    <th>Results</th>
                    <th>Administrative Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($elections)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 2.5rem;">No elections found. Click "Create New Election" above.</td></tr>
                <?php else: ?>
                    <?php foreach ($elections as $elec): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary); display: block; font-size: 0.95rem;"><?= e($elec['title']) ?></strong>
                                <span class="status-badge badge-blue" style="font-size: 0.72rem; margin-top: 0.25rem;"><?= e($elec['category']) ?></span>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.2rem;">
                                    Scope: <?= e($elec['eligibility_type'] === 'all' ? 'All Voters' : 'Dept: ' . $elec['target_department']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;"><?= format_date($elec['start_time'], 'M d') ?> &ndash; <?= format_date($elec['end_time'], 'M d, Y') ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= time_ago($elec['end_time']) ?></div>
                            </td>
                            <td>
                                <a href="<?= url('admin/manage-candidates.php?election_id=' . $elec['id']) ?>" class="btn btn-secondary btn-sm">
                                    <?= (int)$elec['candidate_count'] ?> Candidates &rarr;
                                </a>
                            </td>
                            <td>
                                <strong><?= (int)$elec['vote_count'] ?></strong> / <?= (int)$elec['eligible_count'] ?>
                            </td>
                            <td><?= election_status_badge($elec['status']) ?></td>
                            <td>
                                <form action="<?= url('admin/manage-elections.php') ?>" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_results">
                                    <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                    <input type="hidden" name="current_val" value="<?= (int)$elec['results_published'] ?>">
                                    <button type="submit" class="btn btn-sm <?= ($elec['results_published'] == 1) ? 'btn-emerald' : 'btn-secondary' ?>" title="Click to toggle result publication">
                                        <?= ($elec['results_published'] == 1) ? '✓ Published' : 'Hidden' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                    <?php if ($elec['status'] === 'draft'): ?>
                                        <form action="<?= url('admin/manage-elections.php') ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start">
                                            <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                            <button type="submit" class="btn btn-emerald btn-sm" title="Open voting now">▶ Start</button>
                                        </form>
                                    <?php elseif ($elec['status'] === 'active'): ?>
                                        <form action="<?= url('admin/manage-elections.php') ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="close">
                                            <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Close election immediately">⏹ Close</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Sync Voters -->
                                    <form action="<?= url('admin/manage-elections.php') ?>" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="sync_voters">
                                        <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Sync newly registered voters into eligibility roll">🔄</button>
                                    </form>

                                    <a href="<?= url('admin/manage-elections.php?edit=' . $elec['id']) ?>" class="btn btn-secondary btn-sm" title="Edit Election">
                                        ✏️
                                    </a>

                                    <form action="<?= url('admin/manage-elections.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this election? All candidate records and cast ballots will be permanently removed.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Election">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create / Edit Election Modal -->
<div id="election-form-modal" class="modal-overlay <?= ($editElection || isset($_GET['action'])) ? 'open' : '' ?>">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3><?= $editElection ? 'Edit Election #' . $editElection['id'] : 'Create New Collegiate Election' ?></h3>
            <button type="button" data-close-modal style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/manage-elections.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editElection ? 'update' : 'create' ?>">
            <?php if ($editElection): ?>
                <input type="hidden" name="election_id" value="<?= (int)$editElection['id'] ?>">
            <?php endif; ?>

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="form-group">
                    <label class="form-label" for="title">Election Title *</label>
                    <input type="text" name="title" id="title" class="form-control" 
                           placeholder="e.g. Student Council Presidential Election 2026" 
                           value="<?= e($editElection['title'] ?? '') ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="College Students' Council" <?= (($editElection['category'] ?? '') === "College Students' Council") ? 'selected' : '' ?>>College Students' Council</option>
                            <option value="Department Representative" <?= (($editElection['category'] ?? '') === 'Department Representative') ? 'selected' : '' ?>>Department Representative</option>
                            <option value="Cultural Committee" <?= (($editElection['category'] ?? '') === 'Cultural Committee') ? 'selected' : '' ?>>Cultural Committee</option>
                            <option value="Sports Committee" <?= (($editElection['category'] ?? '') === 'Sports Committee') ? 'selected' : '' ?>>Sports Committee</option>
                            <option value="Alumni Association" <?= (($editElection['category'] ?? '') === 'Alumni Association') ? 'selected' : '' ?>>Alumni Association</option>
                            <option value="Student Council" <?= (($editElection['category'] ?? '') === 'Student Council') ? 'selected' : '' ?>>Student Council (General)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Initial Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="draft" <?= (($editElection['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft (Not Open)</option>
                            <option value="active" <?= (($editElection['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active (Voting Live)</option>
                            <option value="completed" <?= (($editElection['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed (Closed)</option>
                            <option value="cancelled" <?= (($editElection['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Detailed Description & Electoral Scope *</label>
                    <textarea name="description" id="description" rows="3" class="form-control" 
                              placeholder="Eligibility guidelines, positions open, and electoral rules..." required><?= e($editElection['description'] ?? '') ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="start_time">Voting Start Time *</label>
                        <input type="datetime-local" name="start_time" id="start_time" class="form-control" 
                               value="<?= !empty($editElection['start_time']) ? date('Y-m-d\TH:i', strtotime($editElection['start_time'])) : date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_time">Voting End Time *</label>
                        <input type="datetime-local" name="end_time" id="end_time" class="form-control" 
                               value="<?= !empty($editElection['end_time']) ? date('Y-m-d\TH:i', strtotime($editElection['end_time'])) : date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="eligibility_type">Voter Eligibility Scope</label>
                        <select name="eligibility_type" id="eligibility_type" class="form-select" onchange="toggleDeptField(this.value)">
                            <option value="all" <?= (($editElection['eligibility_type'] ?? '') === 'all') ? 'selected' : '' ?>>All Registered Students</option>
                            <option value="department" <?= (($editElection['eligibility_type'] ?? '') === 'department') ? 'selected' : '' ?>>Specific Academic Department</option>
                        </select>
                    </div>

                    <div class="form-group" id="dept-group" style="<?= (($editElection['eligibility_type'] ?? '') === 'department') ? '' : 'display:none;' ?>">
                        <label class="form-label" for="target_department">Target Department</label>
                        <input type="text" name="target_department" id="target_department" class="form-control" 
                               placeholder="e.g. Computer Science" value="<?= e($editElection['target_department'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;">
                        <input type="checkbox" name="results_published" value="1" <?= (!empty($editElection['results_published'])) ? 'checked' : '' ?> style="accent-color:#38B982;width:18px;height:18px;">
                        <span>Publish results publicly upon completion</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-close-modal class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?= $editElection ? 'Update Election' : 'Create & Enroll Voters' ?> &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDeptField(val) {
    const group = document.getElementById('dept-group');
    if (group) {
        group.style.display = (val === 'department') ? 'block' : 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
