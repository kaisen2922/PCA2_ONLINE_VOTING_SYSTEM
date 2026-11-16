<?php
/**
 * Endpoint: verify_sms_otp.php
 * Handles validating submitted Mobile SMS OTP
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp_service.php';

if (!is_logged_in() || !is_voter()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please sign in as a voter.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. POST required.']);
    exit;
}

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token invalid or expired. Please refresh the ballot page.']);
    exit;
}

$voter = current_user();
$challengeId = (int)($_POST['challenge_id'] ?? 0);
$otp = trim($_POST['otp'] ?? '');

if ($challengeId <= 0 || empty($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required verification challenge parameters.']);
    exit;
}

$result = OtpService::verifyOtp($challengeId, (int)$voter['id'], $otp);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
exit;
