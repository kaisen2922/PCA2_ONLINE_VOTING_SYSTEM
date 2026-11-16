<?php
/**
 * ApexVote Email (PHPMailer) & SMS OTP Provider Configuration
 * 
 * Supports:
 * - Email OTP via PHPMailer / Authenticated SMTP
 * - Mobile SMS OTP via Gateway Adapters (Fast2SMS / MSG91 / Twilio)
 * - Rate limiting and security policy enforcement
 */

// Load .env file if available
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

return [
    // Application environment: 'development' | 'production'
    'app_env' => getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production'),

    // -----------------------------------------------------------------
    // 1. Email OTP Configuration (PHPMailer Authenticated SMTP)
    // -----------------------------------------------------------------
    'mail' => [
        'driver'      => getenv('MAIL_DRIVER') ?: ($_ENV['MAIL_DRIVER'] ?? 'smtp'),
        'host'        => getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'),
        'port'        => (int)(getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587)),
        'auth'        => filter_var(getenv('SMTP_AUTH') ?: ($_ENV['SMTP_AUTH'] ?? 'true'), FILTER_VALIDATE_BOOLEAN),
        'username'    => getenv('SMTP_USERNAME') ?: (getenv('SMTP_USER') ?: ($_ENV['SMTP_USERNAME'] ?? '')),
        'password'    => getenv('SMTP_PASSWORD') ?: (getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASSWORD'] ?? '')),
        'encryption'  => strtolower(getenv('SMTP_ENCRYPTION') ?: ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')), // 'tls' (587), 'ssl' (465), or 'none'
        'from_email'  => getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@apexvote.college.edu'),
        'from_name'   => getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'ApexVote Electoral Management System'),
    ],

    // -----------------------------------------------------------------
    // 1b. Brevo Transactional Email Configuration (REST API v3)
    // -----------------------------------------------------------------
    'brevo' => [
        'api_key'      => getenv('BREVO_API_KEY') ?: ($_ENV['BREVO_API_KEY'] ?? ($_SERVER['BREVO_API_KEY'] ?? '')),
        'sender_email' => getenv('BREVO_SENDER_EMAIL') ?: ($_ENV['BREVO_SENDER_EMAIL'] ?? ($_SERVER['BREVO_SENDER_EMAIL'] ?? '')),
        'sender_name'  => getenv('BREVO_SENDER_NAME') ?: ($_ENV['BREVO_SENDER_NAME'] ?? ($_SERVER['BREVO_SENDER_NAME'] ?? 'ApexVote Electoral Management System')),
        'api_url'      => 'https://api.brevo.com/v3/smtp/email',
    ],

    // -----------------------------------------------------------------
    // 2. Mobile SMS OTP Provider Configuration (Indian Mobile Gateway)
    // -----------------------------------------------------------------
    'sms' => [
        // Active provider: 'none' | 'fast2sms' | 'msg91' | 'twilio'
        'provider'             => strtolower(getenv('SMS_PROVIDER') ?: 'none'),
        'default_country_code' => getenv('SMS_DEFAULT_COUNTRY_CODE') ?: '+91',
        
        // Fast2SMS API Adapter (Indian SMS Gateway)
        'fast2sms' => [
            'api_key' => getenv('FAST2SMS_API_KEY') ?: '',
        ],

        // MSG91 Indian SMS Adapter (supports DLT / Send OTP)
        'msg91' => [
            'auth_key'  => getenv('MSG91_AUTH_KEY') ?: '',
            'sender_id' => getenv('MSG91_SENDER_ID') ?: 'APXVOT',
            'dlt_te_id' => getenv('MSG91_DLT_TE_ID') ?: '',
        ],

        // Twilio API Adapter
        'twilio' => [
            'account_sid' => getenv('TWILIO_ACCOUNT_SID') ?: '',
            'auth_token'  => getenv('TWILIO_AUTH_TOKEN') ?: '',
            'from_number' => getenv('TWILIO_FROM_NUMBER') ?: '',
        ],
    ],

    // -----------------------------------------------------------------
    // 3. Verification Security & Rate Limiting Policies
    // -----------------------------------------------------------------
    'security' => [
        'otp_length'                  => 6,
        'otp_lifetime_seconds'        => (int)(getenv('OTP_LIFETIME_SECONDS') ?: 300), // 5 minutes
        'auth_token_lifetime_seconds' => (int)(getenv('AUTH_TOKEN_LIFETIME_SECONDS') ?: 300), // 5 minutes
        'resend_cooldown_seconds'     => (int)(getenv('OTP_RESEND_COOLDOWN_SECONDS') ?: 60),  // 60 seconds
        'max_incorrect_attempts'      => (int)(getenv('OTP_MAX_ATTEMPTS') ?: 5),              // 5 attempts max
        'max_hourly_sends_per_voter'  => (int)(getenv('OTP_MAX_HOURLY_SENDS') ?: 5),          // 5 OTPs per hour
        'max_hourly_sends_per_phone'  => 5,                                                   // 5 OTPs per phone number per hour
        'max_hourly_sends_per_ip'     => 15,                                                  // 15 OTPs per IP per hour
        'hash_secret'                 => getenv('OTP_HASH_SECRET') ?: 'ApexVote_Secure_Keyed_HMAC_Secret_#2026!',
    ],
];
