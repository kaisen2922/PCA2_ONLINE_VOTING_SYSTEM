<?php
/**
 * ApexVote Email Verification Service
 * Powered by PHPMailer with Authenticated SMTP
 */

require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Mask an email address for privacy in interfaces and logs
 * Example: sourav.ganguly@college.edu -> s***y@college.edu
 */
function mask_email_address(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***@***.***';
    }
    $name = $parts[0];
    $domain = $parts[1];

    $length = strlen($name);
    if ($length <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $firstChar = substr($name, 0, 1);
        $lastChar = substr($name, -1);
        $maskedName = $firstChar . str_repeat('*', min(6, $length - 2)) . $lastChar;
    }

    return $maskedName . '@' . $domain;
}

/**
 * Check if Brevo Transactional Email API credentials have been configured in environment
 */
function is_brevo_configured(): bool {
    $config = require __DIR__ . '/../config/mail_sms_config.php';
    $b = $config['brevo'] ?? [];
    return !empty($b['api_key']) 
        && !empty($b['sender_email'])
        && !str_starts_with($b['api_key'], 'your_')
        && filter_var($b['sender_email'], FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if genuine email delivery service (Brevo or SMTP) has been configured
 */
function is_email_configured(): bool {
    return is_brevo_configured() || is_smtp_configured();
}

/**
 * Check if genuine SMTP or Brevo credentials have been configured in environment
 * Preserved for backward-compatibility with all existing service callers
 */
function is_smtp_configured(): bool {
    if (is_brevo_configured()) {
        return true;
    }
    $config = require __DIR__ . '/../config/mail_sms_config.php';
    $m = $config['mail'] ?? [];
    return !empty($m['host']) 
        && !empty($m['username']) 
        && !empty($m['password']) 
        && $m['username'] !== 'your_institutional_email@college.edu'
        && $m['username'] !== 'your_email@gmail.com';
}

/**
 * Send Transactional Email via Brevo REST API v3
 *
 * @param string $toEmail Recipient email address
 * @param string $voterName Recipient voter name
 * @param string $subject Email subject line
 * @param string $htmlBody HTML email content
 * @param string $plainBody Plain text email content
 * @return array Result array ['success' => bool, 'message_id' => ?string, 'error' => ?string]
 */
function send_brevo_email(string $toEmail, string $voterName, string $subject, string $htmlBody, string $plainBody): array {
    $config = require __DIR__ . '/../config/mail_sms_config.php';
    $brevoConfig = $config['brevo'] ?? [];

    $apiKey = $brevoConfig['api_key'] ?? '';
    $senderEmail = $brevoConfig['sender_email'] ?? '';
    $senderName = $brevoConfig['sender_name'] ?: 'ApexVote Electoral Management System';
    $apiUrl = $brevoConfig['api_url'] ?: 'https://api.brevo.com/v3/smtp/email';

    if (empty($apiKey) || empty($senderEmail)) {
        return [
            'success' => false,
            'error'   => 'Brevo API credentials are not configured in .env',
        ];
    }

    $payload = [
        'sender' => [
            'name'  => $senderName,
            'email' => $senderEmail,
        ],
        'to' => [
            [
                'email' => $toEmail,
                'name'  => !empty($voterName) ? $voterName : 'Enrolled Student',
            ]
        ],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $plainBody,
    ];

    $payloadJson = json_encode($payload);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payloadJson,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("[APEXVOTE_BREVO] cURL Connection Error: " . $curlError);
        return [
            'success' => false,
            'error'   => 'Could not connect to Brevo transactional email gateway. Check network connection or proxy settings.',
        ];
    }

    $responseData = json_decode($response, true);

    // Brevo returns HTTP 201 Created on successful email queuing with {"messageId": "<...>"}
    if ($httpCode >= 200 && $httpCode < 300) {
        $messageId = $responseData['messageId'] ?? 'accepted';
        return [
            'success'    => true,
            'message_id' => $messageId,
            'message'    => 'Verification email dispatched successfully via Brevo transactional gateway.',
        ];
    }

    // Handle Brevo error response without exposing sensitive data
    $errorMsg = $responseData['message'] ?? ("Brevo API returned HTTP status {$httpCode}");
    $errorCode = $responseData['code'] ?? 'API_ERROR';

    if ($httpCode === 401 && stripos($errorMsg, 'unrecognised IP address') !== false) {
        $ipActionMsg = 'Brevo email delivery paused: Authorized IP restriction is active in your Brevo account. Please add your current IP to https://app.brevo.com/security/authorised_ips or disable the Authorized IPs restriction.';
        error_log("[APEXVOTE_BREVO] " . $ipActionMsg . " (Details: " . $errorMsg . ")");
        return [
            'success'    => false,
            'error'      => $ipActionMsg,
            'error_code' => 'BREVO_IP_UNAUTHORIZED',
            'http_code'  => 401,
        ];
    }
    
    error_log("[APEXVOTE_BREVO] API Error (HTTP {$httpCode}, Code {$errorCode}): " . $errorMsg);

    return [
        'success'    => false,
        'error'      => 'Brevo email delivery rejected: ' . $errorMsg,
        'error_code' => $errorCode,
        'http_code'  => $httpCode,
    ];
}

/**
 * Send the official Vote Verification Email using Brevo or PHPMailer
 * 
 * @param string $toEmail Registered student email address
 * @param string $voterName Full name of voter
 * @param string $otp 6-digit numeric OTP code
 * @param string $electionName Official title of election
 * @return array Result status array
 */
function send_vote_verification_email(string $toEmail, string $voterName, string $otp, string $electionName = 'Collegiate Election'): array {
    $config = require __DIR__ . '/../config/mail_sms_config.php';
    $mailConfig = $config['mail'] ?? [];

    // Check if any genuine email service is configured
    if (!is_email_configured()) {
        error_log("ApexVote Notice: Attempted email dispatch, but neither Brevo nor SMTP credentials are configured in .env");
        return [
            'success'      => false,
            'configured'   => false,
            'error'        => 'Institutional email verification is currently unavailable (Email service not configured in .env). Please contact the election coordinator.',
            'masked_email' => mask_email_address($toEmail)
        ];
    }

    $cleanElectionName = trim($electionName);
    $subject = "ApexVote – Verification Code for {$cleanElectionName}";
    $plainBody = "Hello {$voterName},\n\n"
        . "A verification request was initiated for your college ballot in:\n"
        . "{$cleanElectionName}\n\n"
        . "Your 6-digit verification code is: {$otp}\n\n"
        . "This code expires in 5 minutes.\n\n"
        . "SECURITY WARNING: Do not share this code with anyone. Election officials, faculty, and candidate slates will never ask for your code.\n\n"
        . "BALLOT PRIVACY: This verification code only validates your eligibility to vote. It does not record or link to your confidential candidate selection.\n\n"
        . "ApexVote College Electoral Management System\n";

    $htmlElectionName = htmlspecialchars($cleanElectionName, ENT_QUOTES, 'UTF-8');
    $htmlVoterName = htmlspecialchars($voterName, ENT_QUOTES, 'UTF-8');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ApexVote – Ballot Verification Code</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #241B16; color: #F6EEDC; padding: 24px; margin: 0;">
    <div style="max-width: 520px; margin: 0 auto; background: #423329; border: 1px solid #5A4738; border-radius: 12px; padding: 32px; box-shadow: 0 12px 32px rgba(0,0,0,0.5);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-block; padding: 10px 20px; background: rgba(244, 203, 69, 0.12); border: 1px solid #F4CB45; border-radius: 8px;">
                <span style="font-size: 20px; font-weight: 800; color: #F4CB45; letter-spacing: 0.05em;">ApexVote</span>
            </div>
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #B9A994; margin-top: 6px;">
                College Electoral Management System
            </div>
        </div>
        <h2 style="color: #F6EEDC; margin-top: 0; font-size: 20px; text-align: center; font-weight: 700;">Ballot Verification Code</h2>
        <p style="font-size: 15px; color: #F6EEDC; line-height: 1.5;">Hello <strong>{$htmlVoterName}</strong>,</p>
        <p style="font-size: 14px; color: #B9A994; line-height: 1.5;">A secure verification request was initiated for your college ballot in:</p>
        <div style="background: rgba(0,0,0,0.25); border: 1px solid #5A4738; border-radius: 8px; padding: 12px 16px; margin: 12px 0 18px 0; text-align: center;">
            <strong style="color: #F4CB45; font-size: 14px;">{$htmlElectionName}</strong>
        </div>
        <div style="background: #241B16; border: 2px dashed #F4CB45; border-radius: 8px; padding: 22px; text-align: center; margin: 20px 0;">
            <div style="font-size: 11px; text-transform: uppercase; color: #B9A994; letter-spacing: 0.12em; margin-bottom: 8px;">Your One-Time Passcode</div>
            <div style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #F4CB45; font-family: 'Consolas', 'Courier New', monospace;">{$otp}</div>
        </div>
        <p style="font-size: 14px; color: #F4CB45; text-align: center; font-weight: 600; margin: 0 0 8px 0;">⏳ Valid for 5 minutes only.</p>
        <p style="font-size: 13px; color: #B9A994; text-align: center; line-height: 1.4; margin-bottom: 16px;">
            🔒 <strong>Strict Ballot Privacy:</strong> Never share this code. Election officials, faculty, and candidate slates will never ask for your verification code. This code does not link to your candidate selection.
        </p>
        <hr style="border: none; border-top: 1px solid #5A4738; margin: 24px 0;">
        <p style="font-size: 11px; color: #B9A994; text-align: center; margin: 0;">
            ApexVote Collegiate Electoral Platform &bull; Kolkata, West Bengal
        </p>
    </div>
</body>
</html>
HTML;

    // 1. Preferred Primary Gateway: Brevo REST API v3
    if (is_brevo_configured()) {
        $brevoRes = send_brevo_email($toEmail, $voterName, $subject, $htmlBody, $plainBody);
        if ($brevoRes['success']) {
            return [
                'success'      => true,
                'configured'   => true,
                'provider'     => 'brevo',
                'message'      => 'Verification email dispatched successfully via Brevo.',
                'message_id'   => $brevoRes['message_id'] ?? null,
                'masked_email' => mask_email_address($toEmail),
            ];
        }

        // If Brevo failed, check if fallback SMTP is available
        $hasSmtpFallback = !empty($mailConfig['host']) 
            && !empty($mailConfig['username']) 
            && !empty($mailConfig['password']) 
            && $mailConfig['username'] !== 'your_institutional_email@college.edu'
            && $mailConfig['username'] !== 'your_email@gmail.com';

        if (!$hasSmtpFallback) {
            return [
                'success'      => false,
                'configured'   => true,
                'provider'     => 'brevo',
                'error'        => $brevoRes['error'] ?? 'Brevo email delivery failed.',
                'masked_email' => mask_email_address($toEmail),
            ];
        }
        error_log("[APEXVOTE_BREVO] Primary dispatch failed, attempting SMTP fallback...");
    }

    // PHPMailer Authenticated SMTP Execution
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = (bool)$mailConfig['auth'];
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];

        $enc = strtolower($mailConfig['encryption'] ?? 'tls');
        if ($enc === 'ssl' || $mailConfig['port'] === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls' || $enc === 'starttls' || $mailConfig['port'] === 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->Port       = (int)$mailConfig['port'];
        $mail->Timeout    = 10;

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($toEmail, $voterName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();

        return [
            'success'      => true,
            'configured'   => true,
            'message'      => 'Verification email dispatched successfully.',
            'masked_email' => mask_email_address($toEmail),
        ];
    } catch (Exception $e) {
        error_log("PHPMailer SMTP Error: " . $mail->ErrorInfo);
        return [
            'success'    => false,
            'configured' => true,
            'error'      => 'SMTP email delivery failed. Please check mail server settings with campus administration.',
        ];
    }
}
