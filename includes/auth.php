<?php
/**
 * Authentication & Authorization Control Layer
 */

require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Retrieve cached or fresh authenticated user record
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $currentUser = null;
    if ($currentUser !== null && (int)($currentUser['id'] ?? 0) === (int)$_SESSION['user_id']) {
        return $currentUser;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, full_name, email, voter_id_number, college_roll_no, university_reg_no, course, semester, academic_session, role, department, phone, status, avatar_url, is_verified, student_roster_id, totp_enabled, password_changed_at, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            logout_user();
            return null;
        }

        $currentUser = $user;
        return $currentUser;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if the authenticated user is an administrator
 */
function is_admin(): bool {
    $user = current_user();
    return ($user && $user['role'] === 'admin');
}

/**
 * Check if the authenticated user is a voter
 */
function is_voter(): bool {
    $user = current_user();
    return ($user && $user['role'] === 'voter');
}

/**
 * Route protection: Require authentication
 */
function require_login(string $redirectPath = 'login.php'): void {
    if (!is_logged_in()) {
        error_log("[APEXVOTE_DIAGNOSTIC] AUTH_REQUIRED: Access attempted without authenticated session from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . " URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
        set_flash('danger', 'Please log in to access this page.');
        redirect($redirectPath . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

/**
 * Route protection: Require administrator privileges
 */
function require_admin(): void {
    require_login('login.php');
    if (!is_admin()) {
        error_log("[APEXVOTE_DIAGNOSTIC] ADMIN_REQUIRED: Non-admin user attempted admin route.");
        set_flash('danger', 'Access denied. Administrative privileges are required.');
        redirect('dashboard.php');
    }
}

/**
 * Route protection: Require voter role
 */
function require_voter(): void {
    require_login('login.php');
    if (!is_voter()) {
        $role = current_user()['role'] ?? 'NONE';
        error_log("[APEXVOTE_DIAGNOSTIC] ROLE_NOT_VOTER: User role '{$role}' attempted voter-only route.");
        if (is_admin()) {
            redirect('admin/dashboard.php');
        }
        set_flash('danger', 'This section is reserved for registered collegiate voters.');
        redirect('login.php');
    }
}

/**
 * Generate or get existing CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF form input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate CSRF token from POST request
 */
function verify_csrf_token(?string $token = null): bool {
    $submittedToken = $token ?? ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($submittedToken) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Set up authenticated session for user
 */
function login_user(array $user): void {
    // Regenerate session id to guard against session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['voter_id'] = $user['voter_id_number'];

    // Refresh CSRF token on login
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    audit_log('USER_LOGIN', 'User logged in: ' . $user['email'], (int)$user['id']);
}

/**
 * Securely terminate user session
 */
function logout_user(): void {
    if (!empty($_SESSION['user_id'])) {
        audit_log('USER_LOGOUT', 'User logged out', (int)$_SESSION['user_id']);
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
