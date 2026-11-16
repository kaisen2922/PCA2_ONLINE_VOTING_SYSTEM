<?php
/**
 * Endpoint: request_sms_otp.php
 * Handles initiating Mobile SMS OTP challenge for voter
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp_service.php';

if (!is_logged_in() || !is_voter()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please sign in as a student voter.']);
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
$electionId = (int)($_POST['election_id'] ?? 0);
$countryCode = trim($_POST['country_code'] ?? '+91');
$mobileNumber = trim($_POST['mobile_number'] ?? '');

if ($electionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid election specified.']);
    exit;
}

if (empty($mobileNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid 10-digit Indian mobile number for SMS verification.']);
    exit;
}

$ipAddress = get_client_ip();
$result = OtpService::requestSmsOtp((int)$voter['id'], $electionId, $countryCode, $mobileNumber, $ipAddress);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
exit;
