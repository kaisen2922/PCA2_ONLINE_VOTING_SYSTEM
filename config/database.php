<?php
/**
 * ApexVote Electoral Platform
 * Database Configuration & System Initialization
 */

// Strict error reporting in development, safe error handling
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Kept clean for users; errors logged or caught via exceptions

// System Regional Configuration: Kolkata, West Bengal, India (IST, UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Lightweight .env reader
if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

load_env_file(__DIR__ . '/../.env');

// Database Credentials (XAMPP Default / Environment Configurable)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'online_voting_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Application & Security Constants
define('APP_NAME', 'ApexVote');
define('APP_VERSION', '2.0.0-PRO');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('OTP_HASH_SECRET', getenv('OTP_HASH_SECRET') ?: 'ApexVote_Secure_Keyed_HMAC_Secret_#2026!');
define('SESSION_INACTIVITY_TIMEOUT', 1800); // 30 minutes inactivity timeout

/**
 * Configure and initialize secure session
 */
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    $cookieParams = [
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// Inactivity session timeout check (30 minutes)
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_INACTIVITY_TIMEOUT)) {
        // Session expired due to inactivity
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Returns the singleton PDO Database Connection
 *
 * @return PDO
 * @throws PDOException
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+05:30'"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database error is due to database not existing yet
            if ($e->getCode() == 1049) {
                die('<div style="font-family:\'Inter\',sans-serif;padding:32px;background:#241B16;color:#F6EEDC;border:1px solid #5A4738;border-radius:12px;margin:40px auto;max-width:600px;box-shadow:0 12px 32px rgba(18,12,8,0.7);">'
                    . '<h2 style="color:#E16B62;margin-top:0;">Database Not Found</h2>'
                    . '<p style="color:#D1C2AC;">The database <code style="color:#F4CB45;background:#2C211B;border:1px solid #5A4738;padding:2px 6px;border-radius:4px;">' . htmlspecialchars(DB_NAME) . '</code> has not been imported yet.</p>'
                    . '<p style="color:#A9977F;">Please open phpMyAdmin or MySQL CLI and import <code>database/online_voting_system.sql</code>.</p>'
                    . '</div>');
            }
            // General connection error
            die('<div style="font-family:\'Inter\',sans-serif;padding:32px;background:#241B16;color:#F6EEDC;border:1px solid #5A4738;border-radius:12px;margin:40px auto;max-width:600px;box-shadow:0 12px 32px rgba(18,12,8,0.7);">'
                . '<h2 style="color:#E16B62;margin-top:0;">Database Connection Error</h2>'
                . '<p style="color:#D1C2AC;">Could not connect to MySQL server at <strong>' . htmlspecialchars(DB_HOST) . ':' . htmlspecialchars(DB_PORT) . '</strong>.</p>'
                . '<p style="color:#A9977F;font-size:0.9em;">Details: ' . htmlspecialchars($e->getMessage()) . '</p>'
                . '<p style="color:#F4CB45;">Ensure Apache and MySQL services are started in your XAMPP Control Panel.</p>'
                . '</div>');
        }
    }

    return $pdo;
}

/**
 * Dynamic Base URL resolution
 * Works automatically in root, subfolder (e.g. /voting system or /online-voting-system),
 * or built-in PHP development server.
 *
 * @return string
 */
function get_base_url(): string {
    if (defined('CUSTOM_BASE_URL') && !empty(CUSTOM_BASE_URL)) {
        return rtrim(CUSTOM_BASE_URL, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Script name relative to web root
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    $dir = str_replace('\\', '/', $dir);

    // If running within /admin subdirectory, ascend one level to root
    if (basename($dir) === 'admin') {
        $dir = dirname($dir);
        $dir = str_replace('\\', '/', $dir);
    }

    $dir = rtrim($dir, '/');
    return $protocol . $host . $dir;
}

/**
 * Generate full URL for any path
 */
function url(string $path = ''): string {
    $base = get_base_url();
    $path = ltrim($path, '/');
    return empty($path) ? $base : $base . '/' . $path;
}

/**
 * Generate asset URL
 */
function asset(string $path = ''): string {
    return url('assets/' . ltrim($path, '/'));
}
