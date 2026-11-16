<?php
/**
 * Example Email and SMS Provider Configuration for ApexVote
 * Duplicate or rename this file to config/mail_sms_config.php or set via .env
 */

return [
    'app_env' => 'development',

    'mail' => [
        'driver'      => 'smtp',
        'host'        => 'smtp.gmail.com',
        'port'        => 587,
        'auth'        => true,
        'username'    => 'collegiate_voting@example.edu',
        'password'    => 'your_app_password_here',
        'encryption'  => 'tls',
        'from_email'  => 'no-reply@apexvote.college.edu',
        'from_name'   => 'ApexVote Electoral Management System',
        'mock_mode'   => true, // In local dev, set true to record to storage/logs/mail_dev.log
        'log_path'    => __DIR__ . '/../storage/logs/mail_dev.log',
    ],

    'sms' => [
        'provider'             => 'mock', // 'mock', 'twilio', or 'msg91'
        'default_country_code' => '+91',
        'twilio' => [
            'account_sid' => '',
            'auth_token'  => '',
            'from_number' => '',
        ],
        'msg91' => [
            'auth_key'  => '',
            'sender_id' => 'APXVOT',
            'dlt_te_id' => '',
        ],
        'mock_log_path' => __DIR__ . '/../storage/logs/sms_dev.log',
    ],

    'security' => [
        'otp_length'                  => 6,
        'otp_lifetime_seconds'        => 300,
        'auth_token_lifetime_seconds' => 300,
        'resend_cooldown_seconds'     => 60,
        'max_incorrect_attempts'      => 5,
        'max_hourly_sends_per_voter'  => 3,
        'max_hourly_sends_per_phone'  => 3,
        'max_hourly_sends_per_ip'     => 15,
    ],
];
