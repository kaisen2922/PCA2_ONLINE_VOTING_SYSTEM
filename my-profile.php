<?php
/**
 * Voter Profile Management
 * Refined for ApexVote Editorial Design Language
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Collegiate Voter Profile';
$isDashboard = true;
$isAdmin = is_admin();
$user = current_user();
$db = getDB();

$error = '';
$success = '';

// Handle Profile & Password Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $phone = trim($_POST['phone'] ?? '');

            if (!empty($phone) && !is_valid_indian_phone($phone)) {
                $error = 'Please enter a valid 10-digit Indian mobile number.';
            } else {
                $formattedPhone = !empty($phone) ? format_indian_phone($phone) : '';
                
                // Administrator accounts may update their administrative display name
                if ($isAdmin) {
                    $fullName = trim($_POST['full_name'] ?? '');
                    if (empty($fullName)) {
                        $error = 'Administrator name cannot be blank.';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$fullName, $formattedPhone, $user['id']]);
                        audit_log('PROFILE_UPDATE', 'Administrator updated contact and profile details', $user['id']);
                        set_flash('success', 'Your administrative profile has been updated.');
                        redirect('my-profile.php');
                    }
                } else {
                    // Voters can update only contact phone; verified institutional fields are immutable without admin approval
                    $stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
                    $stmt->execute([$formattedPhone, $user['id']]);
                    audit_log('PROFILE_UPDATE', 'Student updated contact phone number', $user['id']);
                    set_flash('success', 'Your contact phone number has been updated successfully.');
                    redirect('my-profile.php');
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            // Fetch stored hash
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $storedHash = $stmt->fetchColumn();

            if (empty($currentPassword) || empty($newPassword)) {
                $error = 'Please provide both current and new passwords.';
            } elseif (!password_verify($currentPassword, $storedHash)) {
                $error = 'The current password you entered is incorrect.';
            } elseif (strlen($newPassword) < 10) {
                $error = 'New password must contain at least 10 characters for robust security.';
            } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $error = 'New password must include both letters and numbers.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password confirmation does not match.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $uStmt = $db->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
                $uStmt->execute([$newHash, $user['id']]);

                audit_log('PASSWORD_CHANGE', 'User updated account authentication password', $user['id']);
                set_flash('success', 'Your account password has been changed successfully.');
                redirect('my-profile.php');
            }
        }
    }
}

$flashMsg = get_flash();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem; letter-spacing: -0.02em;">Collegiate Voter Profile</h2>
    <p style="font-size: 0.92rem; color: var(--text-secondary); margin: 0;">Review your verified institutional registration, manage contact details, and update security credentials.</p>
</div>

<?php if ($flashMsg): ?>
    <div class="alert-box alert-<?= e($flashMsg['type'] ?? 'info') ?>" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($flashMsg['message']) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 2rem; align-items: start;">
    
    <!-- ============================================================== -->
    <!-- SECTION A: Institutional Identity                              -->
    <!-- ============================================================== -->
    <div class="glass-card" style="padding: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
            <h3 style="margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 0.65rem; font-size: 1.25rem;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="8" x2="17" y2="8"/><line x1="15" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="17" y2="16"/></svg>
                <span>Institutional Identity</span>
            </h3>
            <span class="status-badge badge-emerald" style="font-size: 0.72rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                ✓ Registrar Verified
            </span>
        </div>

        <form action="<?= url('my-profile.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <!-- Read-Only Institutional Keys -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="college_roll_no" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>College Roll Number</span>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">🔒 Locked</span>
                    </label>
                    <input type="text" id="college_roll_no" class="form-control form-control-readonly" 
                           value="<?= e($user['college_roll_no'] ?: $user['voter_id_number']) ?>" readonly 
                           title="Verified institutional registration record">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="university_reg_no" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>University Reg. No.</span>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">🔒 Locked</span>
                    </label>
                    <input type="text" id="university_reg_no" class="form-control form-control-readonly" 
                           value="<?= e($user['university_reg_no'] ?: 'Verified Student') ?>" readonly 
                           title="Official university student registration">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Institutional Email Address</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">🔒 Mandatory OTP Destination</span>
                </label>
                <input type="email" id="email" class="form-control form-control-readonly" 
                       value="<?= e($user['email']) ?>" readonly>
                <span class="form-help" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                    Bound to your student account. Used for mandatory two-factor voting OTP verification.
                </span>
            </div>

            <!-- Academic Session & Course Line -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="academic_session">Academic Session</label>
                    <input type="text" id="academic_session" class="form-control form-control-readonly" 
                           value="<?= e($user['academic_session'] ?? '2026–2027') ?>" readonly>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="course" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Course / Degree</span>
                        <?php if (!$isAdmin): ?><span style="font-size: 0.72rem; color: var(--text-muted);">🔒 Locked</span><?php endif; ?>
                    </label>
                    <?php if ($isAdmin): ?>
                        <select name="course" id="course" class="form-select">
                            <?php
                            $courses = ['BCA', 'B.Tech CSE', 'B.Tech IT', 'BBA', 'B.Com (Hons.)', 'MCA', 'M.Sc Computer Science', 'Faculty Administration'];
                            $userCourse = $user['course'] ?? 'BCA';
                            foreach ($courses as $c): ?>
                                <option value="<?= e($c) ?>" <?= ($userCourse === $c) ? 'selected' : '' ?>>
                                    <?= e($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="course" class="form-control form-control-readonly" value="<?= e($user['course'] ?? 'BCA') ?>" readonly>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Full Legal Name *</span>
                    <?php if (!$isAdmin): ?><span style="font-size: 0.72rem; color: var(--text-muted);">🔒 Official Roster Name</span><?php endif; ?>
                </label>
                <?php if ($isAdmin): ?>
                    <input type="text" name="full_name" id="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                <?php else: ?>
                    <input type="text" id="full_name" class="form-control form-control-readonly" value="<?= e($user['full_name']) ?>" readonly>
                    <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                        Verified against the official college enrolment roster. Contact registrar to request official name corrections.
                    </span>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="department" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Department</span>
                        <?php if (!$isAdmin): ?><span style="font-size: 0.72rem; color: var(--text-muted);">🔒 Locked</span><?php endif; ?>
                    </label>
                    <?php if ($isAdmin): ?>
                        <select name="department" id="department" class="form-select">
                            <?php
                            $depts = [
                                'Computer Applications',
                                'Computer Science & Engineering',
                                'Information Technology',
                                'Business Administration',
                                'Commerce',
                                'Electronics & Communication',
                                'Basic Sciences & Humanities',
                                'College Election Committee'
                            ];
                            foreach ($depts as $dept): ?>
                                <option value="<?= e($dept) ?>" <?= ($user['department'] === $dept) ? 'selected' : '' ?>>
                                    <?= e($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="department" class="form-control form-control-readonly" value="<?= e($user['department']) ?>" readonly>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="semester" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Current Semester</span>
                        <?php if (!$isAdmin): ?><span style="font-size: 0.72rem; color: var(--text-muted);">🔒 Locked</span><?php endif; ?>
                    </label>
                    <?php if ($isAdmin): ?>
                        <select name="semester" id="semester" class="form-select">
                            <?php
                            $userSem = (int)($user['semester'] ?? 1);
                            for ($s = 1; $s <= 8; $s++): ?>
                                <option value="<?= $s ?>" <?= ($userSem === $s) ? 'selected' : '' ?>>
                                    Semester <?= $s ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="semester" class="form-control form-control-readonly" value="Semester <?= (int)($user['semester'] ?? 1) ?>" readonly>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Default Contact Mobile (+91)</label>
                <input type="tel" name="phone" id="phone" class="form-control" 
                       value="<?= e($user['phone'] ?? '') ?>" placeholder="+91 98301 23456">
                <span class="form-help" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                    Used for campus notifications and pre-filling SMS verification requests during active ballots.
                </span>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Save Contact Changes &rarr;
            </button>
        </form>
    </div>

    <!-- ============================================================== -->
    <!-- SECTION B & C: Account Security & Status                       -->
    <!-- ============================================================== -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Account Security Card -->
        <div class="glass-card" style="padding: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <h3 style="margin: 0 0 1.5rem 0; color: var(--text-primary); display: flex; align-items: center; gap: 0.65rem; font-size: 1.25rem;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Account Security</span>
            </h3>

            <form action="<?= url('my-profile.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password *</label>
                    <div class="password-input-wrap">
                        <input type="password" name="current_password" id="current_password" class="form-control" 
                               placeholder="Enter current password" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password *</label>
                    <div class="password-input-wrap">
                        <input type="password" name="new_password" id="new_password" class="form-control" 
                               placeholder="Minimum 10 characters (letters & numbers)" required autocomplete="new-password" minlength="10">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted);">Must be at least 10 characters and include both letters and numbers</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password *</label>
                    <div class="password-input-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                               placeholder="Re-enter new password" required autocomplete="new-password" minlength="10">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                               placeholder="Confirm new password" required autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <span class="form-help" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                        Minimum 6 characters. Use letters, numbers, and symbols for collegiate account protection.
                    </span>
                </div>

                <button type="submit" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">
                    Update Security Password &rarr;
                </button>
            </form>
        </div>

        <!-- Section C: Account Status Card -->
        <div class="glass-card" style="padding: 1.5rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <h4 style="margin: 0 0 1rem 0; font-size: 1.05rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                <span>Collegiate Account Status</span>
            </h4>

            <div style="background: rgba(0, 0, 0, 0.28); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.15rem; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                    <span style="color: var(--text-muted);">Account Role:</span>
                    <strong style="color: var(--accent-primary); letter-spacing: 0.05em;"><?= strtoupper(e($user['role'] === 'admin' ? 'ELECTION OFFICER' : 'STUDENT VOTER')) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                    <span style="color: var(--text-muted);">Verification Status:</span>
                    <span class="status-badge badge-emerald" style="font-size: 0.72rem; padding: 0.2rem 0.6rem;">✓ Verified Collegiate Voter</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                    <span style="color: var(--text-muted);">Account State:</span>
                    <span class="status-badge badge-slate" style="font-size: 0.72rem; padding: 0.2rem 0.6rem;"><?= ucfirst(e($user['status'] ?? 'active')) ?> (Good Standing)</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--text-muted);">Enrolled Since:</span>
                    <span style="color: var(--text-secondary);"><?= format_date($user['created_at'], 'd M Y') ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
