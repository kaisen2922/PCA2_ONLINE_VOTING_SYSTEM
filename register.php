<?php
/**
 * Voter Registration Portal - Localized for Indian College Campus (Kolkata, WB)
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$pageTitle = 'Student Voter Registration';
$isDashboard = false;
$error = '';
$allowReg = get_setting('allow_registration', '1');
$academicSession = get_setting('academic_year', '2026–2027');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($allowReg !== '1') {
        $error = 'Self-registration is currently closed by the College Election Committee.';
    } elseif (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $collegeRoll = trim($_POST['college_roll_no'] ?? '');
        $univRegNo = trim($_POST['university_reg_no'] ?? '');
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? 'Computer Applications');
        $course = trim($_POST['course'] ?? 'BCA');
        $semester = (int)($_POST['semester'] ?? 1);
        $session = trim($_POST['academic_session'] ?? $academicSession);
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Validation
        if (empty($fullName) || empty($collegeRoll) || empty($univRegNo) || empty($email) || empty($phone) || empty($password)) {
            $error = 'All required fields marked with * must be filled.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid institutional email address.';
        } elseif (!is_valid_indian_phone($phone)) {
            $error = 'Please enter a valid 10-digit Indian mobile number (e.g. 9830123456 or +91 9830123456).';
        } elseif (strlen($password) < 10) {
            $error = 'Password must contain at least 10 characters for secure access.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must include both letters and numbers for enhanced security.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                $db = getDB();

                // 1. Check if authorized student roster is imported
                $rosterCount = (int)$db->query("SELECT COUNT(*) FROM authorized_students")->fetchColumn();
                if ($rosterCount === 0) {
                    $error = 'Voter registration is currently unavailable: The authorised collegiate student roster has not been imported yet. Please contact the College Election Committee.';
                } else {
                    // 2. Validate against authorized student roster
                    $rosterStmt = $db->prepare("SELECT * FROM authorized_students WHERE college_roll_no = ? LIMIT 1");
                    $rosterStmt->execute([$collegeRoll]);
                    $rosterStudent = $rosterStmt->fetch();

                    if (!$rosterStudent) {
                        $error = 'Identity verification failed: College Roll Number not found on the authorised college student roster.';
                    } elseif ($rosterStudent['status'] !== 'active') {
                        $error = 'Identity verification failed: This student roster record is inactive. Please contact campus administration.';
                    } elseif (strcasecmp(trim($rosterStudent['university_reg_no']), $univRegNo) !== 0) {
                        $error = 'Identity verification failed: University Registration Number does not match the official student roster record.';
                    } elseif (strcasecmp(trim($rosterStudent['email']), $email) !== 0) {
                        $error = 'Identity verification failed: Institutional email does not match the official student roster record (' . mask_email_address($rosterStudent['email']) . ').';
                    } elseif (!empty($rosterStudent['registered_user_id']) || $rosterStudent['verification_status'] === 'verified') {
                        $error = 'This authorised student record has already been linked to a registered account. Please sign in to your existing account.';
                    } else {
                        // 3. Check for existing voter account
                        // Enforce unique roll number, university registration number, email, and roster link
                        $checkStmt = $db->prepare("
                            SELECT id, email, college_roll_no, university_reg_no, student_roster_id 
                            FROM users 
                            WHERE email = ? 
                               OR college_roll_no = ? 
                               OR university_reg_no = ? 
                               OR student_roster_id = ?
                            LIMIT 1
                        ");
                        $checkStmt->execute([
                            $rosterStudent['email'], 
                            $rosterStudent['college_roll_no'], 
                            $rosterStudent['university_reg_no'],
                            (int)$rosterStudent['id']
                        ]);
                        $existing = $checkStmt->fetch();

                        if ($existing) {
                            if (strcasecmp($existing['email'] ?? '', $rosterStudent['email']) === 0) {
                                $error = 'An account with this institutional email address already exists. Please sign in or reset your password.';
                            } elseif (strcasecmp($existing['college_roll_no'] ?? '', $rosterStudent['college_roll_no']) === 0) {
                                $error = 'This College Roll Number has already been registered on the voter roll. Please sign in to vote.';
                            } elseif (strcasecmp($existing['university_reg_no'] ?? '', $rosterStudent['university_reg_no']) === 0) {
                                $error = 'This University Registration Number has already been registered.';
                            } else {
                                $error = 'This authorised student record has already been registered on the voter roll. Please sign in to your existing account.';
                            }
                        } else {
                            // 4. Create verified voter account using authoritative roster records
                            $formattedPhone = format_indian_phone($phone);
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $avatar = 'default-avatar.svg';

                            // Adopt verified details strictly from authoritative college roster
                            $authoritativeName = $rosterStudent['full_name'];
                            $authoritativeRoll = $rosterStudent['college_roll_no'];
                            $authoritativeReg  = $rosterStudent['university_reg_no'];
                            $authoritativeEmail = $rosterStudent['email'];
                            $deptVerified = $rosterStudent['department'] ?: $department;
                            $courseVerified = $rosterStudent['course'] ?: $course;
                            $semVerified = (int)($rosterStudent['semester'] ?: $semester);
                            $sessionVerified = $rosterStudent['academic_session'] ?: $session;
                            $rosterId = (int)$rosterStudent['id'];

                            $db->beginTransaction();

                            $insertStmt = $db->prepare("
                                INSERT INTO users (
                                    full_name, email, voter_id_number, college_roll_no, university_reg_no, 
                                    password_hash, role, department, course, semester, academic_session, 
                                    phone, status, is_verified, student_roster_id, avatar_url, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, 'voter', ?, ?, ?, ?, ?, 'active', 1, ?, ?, NOW())
                            ");
                            $insertStmt->execute([
                                $authoritativeName, $authoritativeEmail, $authoritativeRoll, $authoritativeRoll, $authoritativeReg,
                                $passwordHash, $deptVerified, $courseVerified, $semVerified, $sessionVerified,
                                $formattedPhone, $rosterId, $avatar
                            ]);
                            $newUserId = (int)$db->lastInsertId();

                            // Mark student roster record as verified and link to user
                            $updRoster = $db->prepare("UPDATE authorized_students SET verification_status = 'verified', registered_user_id = ? WHERE id = ?");
                            $updRoster->execute([$newUserId, $rosterId]);

                            // Automatically enroll voter into eligible active/draft elections
                            $elecStmt = $db->query("SELECT id, eligibility_type, target_department FROM elections WHERE status IN ('active', 'draft')");
                            $elections = $elecStmt->fetchAll();

                            $eligStmt = $db->prepare("INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, eligibility_status, created_at) VALUES (?, ?, 0, 'eligible', NOW())");
                            foreach ($elections as $elec) {
                                if ($elec['eligibility_type'] === 'all' || strcasecmp($elec['target_department'], $deptVerified) === 0) {
                                    $eligStmt->execute([$elec['id'], $newUserId]);
                                }
                            }

                            $db->commit();

                            audit_log('VOTER_REGISTERED', 'Verified student registered: ' . $authoritativeEmail . ' (' . $authoritativeRoll . ', Reg: ' . $authoritativeReg . ')', $newUserId);

                            set_flash('success', 'Registration and identity verification successful! You may now sign in to your voter command center.');
                            redirect('login.php');
                        }
                    }
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Registration Error: " . $e->getMessage());
                $error = 'Registration processing error. Please verify your inputs and retry.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding: 3.5rem 1.5rem;">
    <div class="glass-card" style="max-width: 680px; margin: 0 auto; padding: 2.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <span class="status-badge badge-category" style="margin-bottom: 0.5rem;">Student Electoral Enrollment</span>
            <h2>Voter Registration</h2>
            <p style="font-size: 0.9rem; color: var(--text-secondary);">
                Register as an eligible student voter for <?= e(get_setting('institution_name', 'ApexVote College Demonstration Campus')) ?>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box alert-danger">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($allowReg !== '1'): ?>
            <div class="alert-box alert-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Self-registration is currently disabled by the College Election Committee. Please contact the Election Helpdesk.</span>
            </div>
        <?php else: ?>
            <form action="<?= url('register.php') ?>" method="POST">
                <?= csrf_field() ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <!-- Full Name -->
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="full_name">Full Legal / Student Name *</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" 
                               placeholder="e.g. Rahul Das" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                    </div>

                    <!-- College Roll Number -->
                    <div class="form-group">
                        <label class="form-label" for="college_roll_no">College Roll Number *</label>
                        <input type="text" name="college_roll_no" id="college_roll_no" class="form-control" 
                               placeholder="e.g. BCA/2026/041" value="<?= e($_POST['college_roll_no'] ?? '') ?>" required>
                        <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted);">As per college identity card</span>
                    </div>

                    <!-- University Registration Number -->
                    <div class="form-group">
                        <label class="form-label" for="university_reg_no">University Registration Number *</label>
                        <input type="text" name="university_reg_no" id="university_reg_no" class="form-control" 
                               placeholder="e.g. 12623010041" value="<?= e($_POST['university_reg_no'] ?? '') ?>" required>
                        <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted);">From University registration certificate</span>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label class="form-label" for="email">Institutional / College Email *</label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="student@college.edu.in" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>

                    <!-- Mobile Number -->
                    <div class="form-group">
                        <label class="form-label" for="phone">Mobile Number (+91) *</label>
                        <input type="tel" name="phone" id="phone" class="form-control" 
                               placeholder="e.g. 9830123456" value="<?= e($_POST['phone'] ?? '') ?>" required>
                        <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted);">10-digit Indian mobile number</span>
                    </div>

                    <!-- Department -->
                    <div class="form-group">
                        <label class="form-label" for="department">Department *</label>
                        <select name="department" id="department" class="form-select" required>
                            <?php
                            $departments = [
                                'Computer Applications',
                                'Computer Science & Engineering',
                                'Information Technology',
                                'Business Administration',
                                'Commerce',
                                'Electronics & Communication',
                                'Basic Sciences & Humanities'
                            ];
                            $selectedDept = $_POST['department'] ?? 'Computer Applications';
                            foreach ($departments as $dept): ?>
                                <option value="<?= e($dept) ?>" <?= ($selectedDept === $dept) ? 'selected' : '' ?>>
                                    <?= e($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Course -->
                    <div class="form-group">
                        <label class="form-label" for="course">Course *</label>
                        <select name="course" id="course" class="form-select" required>
                            <?php
                            $courses = [
                                'BCA' => 'BCA (Bachelor of Computer Applications)',
                                'B.Tech CSE' => 'B.Tech CSE',
                                'B.Tech IT' => 'B.Tech IT',
                                'BBA' => 'BBA',
                                'B.Com (Hons.)' => 'B.Com (Hons.)',
                                'MCA' => 'MCA',
                                'M.Sc Computer Science' => 'M.Sc Computer Science'
                            ];
                            $selectedCourse = $_POST['course'] ?? 'BCA';
                            foreach ($courses as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= ($selectedCourse === $val) ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Current Semester -->
                    <div class="form-group">
                        <label class="form-label" for="semester">Current Semester *</label>
                        <select name="semester" id="semester" class="form-select" required>
                            <?php
                            $selectedSem = (int)($_POST['semester'] ?? 1);
                            for ($s = 1; $s <= 8; $s++): ?>
                                <option value="<?= $s ?>" <?= ($selectedSem === $s) ? 'selected' : '' ?>>
                                    Semester <?= $s ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Academic Session -->
                    <div class="form-group">
                        <label class="form-label" for="academic_session">Academic Session</label>
                        <input type="text" name="academic_session" id="academic_session" class="form-control" 
                               value="<?= e($academicSession) ?>" readonly style="background: rgba(0,0,0,0.3); opacity: 0.85; cursor: not-allowed;">
                    </div>

                    <!-- Passwords -->
                    <div class="form-group">
                        <label class="form-label" for="password">Create Password *</label>
                        <div class="password-input-wrap">
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Minimum 10 characters (letters & numbers)" required autocomplete="new-password" minlength="10">
                            <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <span class="form-help" style="font-size: 0.75rem; color: var(--text-muted);">Must be at least 10 characters and include both letters and numbers</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password *</label>
                        <div class="password-input-wrap">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                   placeholder="Re-enter password" required autocomplete="new-password" minlength="10">
                            <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.75rem;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        Complete Student Registration &rarr;
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 2rem; border-top: 1px solid var(--border-subtle); padding-top: 1.25rem; font-size: 0.88rem;">
            Already registered on the student voter roll? 
            <a href="<?= url('login.php') ?>" style="font-weight: 600; color: var(--accent-primary);">Sign in to your account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
