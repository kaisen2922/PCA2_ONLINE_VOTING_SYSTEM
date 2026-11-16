<?php
/**
 * Voter & Administrator Authentication Portal
 */

require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$pageTitle = 'Sign In to Electoral Portal';
$isDashboard = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter your registered email/voter ID and password.';
        } else {
            try {
                $db = getDB();
                $ipAddress = get_client_ip();
                $identHash = hash('sha256', strtolower($identifier));

                // 1. Check Rate Limiting Lockout (5 attempts -> 15 min lock)
                $lockCheck = $db->prepare("
                    SELECT id, attempt_count, locked_until 
                    FROM login_rate_limits 
                    WHERE (identifier_hash = ? OR ip_address = ?) 
                      AND locked_until > NOW() 
                    ORDER BY locked_until DESC LIMIT 1
                ");
                $lockCheck->execute([$identHash, $ipAddress]);
                $locked = $lockCheck->fetch();

                if ($locked) {
                    $waitMinutes = max(1, (int)ceil((strtotime($locked['locked_until']) - time()) / 60));
                    $error = "Account access temporarily locked due to excessive failed attempts. Please try again in {$waitMinutes} minute(s).";
                } else {
                    // Query by email, voter ID number, college roll number, or university registration number
                    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR voter_id_number = ? OR college_roll_no = ? OR university_reg_no = ? LIMIT 1");
                    $stmt->execute([$identifier, $identifier, $identifier, $identifier]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        if ($user['status'] === 'suspended') {
                            $error = 'Your account has been suspended by the College Election Committee. Contact campus administration.';
                        } elseif ($user['status'] === 'pending') {
                            $error = 'Your registration is pending departmental verification.';
                        } else {
                            // Clear failed attempts upon successful login
                            $db->prepare("DELETE FROM login_rate_limits WHERE identifier_hash = ? OR ip_address = ?")->execute([$identHash, $ipAddress]);

                            // Successful login with session regeneration
                            login_user($user);
                            set_flash('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!');

                            $redirectUrl = !empty($_GET['redirect']) ? $_GET['redirect'] : ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
                            redirect($redirectUrl);
                        }
                    } else {
                        // Record failed login attempt
                        $checkAttempt = $db->prepare("SELECT id, attempt_count FROM login_rate_limits WHERE identifier_hash = ? AND ip_address = ? LIMIT 1");
                        $checkAttempt->execute([$identHash, $ipAddress]);
                        $att = $checkAttempt->fetch();

                        if ($att) {
                            $newCount = (int)$att['attempt_count'] + 1;
                            $lockTime = ($newCount >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;
                            $db->prepare("UPDATE login_rate_limits SET attempt_count = ?, locked_until = ?, last_attempt = NOW() WHERE id = ?")->execute([$newCount, $lockTime, $att['id']]);
                        } else {
                            $db->prepare("INSERT INTO login_rate_limits (identifier_hash, ip_address, attempt_count, last_attempt) VALUES (?, ?, 1, NOW())")->execute([$identHash, $ipAddress]);
                        }

                        $error = 'Invalid credentials. Please verify your ID/email and password.';
                        audit_log('LOGIN_FAILED', 'Failed login attempt for identifier hash: ' . substr($identHash, 0, 10));
                    }
                }
            } catch (Exception $e) {
                error_log("Login Database Error: " . $e->getMessage());
                $error = 'A secure service error occurred during sign in. Please retry shortly.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding: 5rem 1.5rem;">
    <div class="glass-card" style="max-width: 480px; margin: 0 auto; padding: 2.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="<?= asset('images/logo.png') ?>" alt="ApexVote Logo" style="width: 64px; height: 64px; object-fit: contain; margin-bottom: 0.75rem;">
            <h2>Portal Authentication</h2>
            <p style="font-size: 0.9rem;">Sign in using your institutional credentials</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box alert-danger">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Quick Demo Credentials Selector -->
        <div style="background: rgba(244, 203, 69, 0.08); border: 1px dashed rgba(244, 203, 69, 0.35); border-radius: var(--radius-md); padding: 0.85rem; margin-bottom: 1.5rem; text-align: center;">
            <div style="font-size: 0.8rem; font-weight: 700; color: #F4CB45; margin-bottom: 0.4rem; text-transform: uppercase;">
                Quick Demo Accounts (Click to Fill)
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('admin@college.edu', 'Admin@123')">
                    Admin (Dr. Mukherjee)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('voter1@college.edu', 'Voter@123')">
                    Voter 1 (Sourav - BCA)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('voter2@college.edu', 'Voter@123')">
                    Voter 2 (Rituparna - CSE)
                </button>
            </div>
        </div>

        <form action="<?= url('login.php') ?><?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="identifier">College Email or College Roll Number</label>
                <input type="text" name="identifier" id="identifier" class="form-control" 
                       placeholder="e.g. voter1@college.edu or BCA/2026/041" 
                       value="<?= e($_POST['identifier'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Default: Admin@123 or Voter@123</span>
                </div>
                <div class="password-input-wrap">
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="••••••••••••" required autocomplete="current-password">
                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div style="margin-top: 1.75rem;">
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Sign In Securely &rarr;
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 2rem; border-top: 1px solid var(--border-subtle); padding-top: 1.25rem; font-size: 0.88rem;">
            New to campus elections? 
            <a href="<?= url('register.php') ?>" style="font-weight: 600;">Register as Voter</a>
        </div>
    </div>
</div>

<script>
function fillCreds(ident, pass) {
    document.getElementById('identifier').value = ident;
    document.getElementById('password').value = pass;
    showToast('info', 'Demo credentials inserted! Click "Sign In Securely"');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
