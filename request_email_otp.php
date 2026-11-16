<?php
/**
 * Endpoint: request_email_otp.php
 * Handles initiating mandatory Email OTP challenge for voting
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp_service.php';

// Route access: Voter role only
if (!is_logged_in() || !is_voter()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in as a student voter.']);
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

if ($electionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid election ID specified.']);
    exit;
}

$ipAddress = get_client_ip();
$result = OtpService::requestEmailOtp((int)$voter['id'], $electionId, $ipAddress);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
exit;
