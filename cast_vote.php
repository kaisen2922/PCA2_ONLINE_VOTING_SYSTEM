<?php
/**
 * Official Ballot Casting Endpoint: cast_vote.php
 * 
 * Enforces:
 * - Single-use cryptographic voting authorization token validation
 * - Mandatory Email OTP verification status check
 * - Optional SMS OTP verification status check (if selected)
 * - Atomic database transaction with row locks (FOR UPDATE)
 * - Duplicate vote prevention and strict ballot secrecy
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp_service.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json');

function respond(bool $success, string $message, array $extra = [], int $httpCode = 200): void {
    global $isAjax;
    if ($isAjax) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }

    if ($success) {
        set_flash('success', $message);
        redirect($extra['redirect'] ?? 'voting-history.php');
    } else {
        set_flash('danger', $message);
        $electionId = (int)($_POST['election_id'] ?? 0);
        redirect($electionId > 0 ? "vote.php?id={$electionId}" : 'elections.php');
    }
    exit;
}

// 1. Validate voter authentication & student identity verification
if (!is_logged_in() || !is_voter()) {
    respond(false, 'Unauthorized. Please log in as an enrolled collegiate voter.', [], 401);
}

$user = current_user();
if (!$user || $user['status'] !== 'active') {
    respond(false, 'Your voter account is not active. Please contact the campus election committee.', [], 403);
}

if (empty($user['is_verified']) || (int)$user['is_verified'] !== 1) {
    audit_log('UNAUTHORIZED_VOTE_REQUEST', "Unverified account attempted ballot submission in Election", (int)$user['id']);
    respond(false, 'Your student identity is not verified on the authorised student roster.', [], 403);
}

$db = getDB();

// Confirm student belongs to active authorized roster
if (!empty($user['student_roster_id'])) {
    $rosterChk = $db->prepare("SELECT id, status FROM authorized_students WHERE id = ? LIMIT 1");
    $rosterChk->execute([(int)$user['student_roster_id']]);
    $rosterRec = $rosterChk->fetch();
    if (!$rosterRec || $rosterRec['status'] !== 'active') {
        audit_log('UNAUTHORIZED_VOTE_REQUEST', "Inactive roster student attempted ballot submission", (int)$user['id']);
        respond(false, 'Your student record on the authorised college roster is inactive. Please contact administration.', [], 403);
    }
}

// 2. Reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed. Ballots must be submitted via POST.', [], 405);
}

// 3. Validate CSRF Token
if (!verify_csrf_token()) {
    respond(false, 'Security token validation failed. Please refresh your browser and try again.', [], 403);
}

$electionId = (int)($_POST['election_id'] ?? 0);
$candidateId = (int)($_POST['candidate_id'] ?? 0);
$authToken = trim($_POST['authorization_token'] ?? '');

if ($electionId <= 0 || $candidateId <= 0) {
    respond(false, 'Invalid ballot data: Please select a valid election and certified candidate.', [], 400);
}

if (empty($authToken)) {
    respond(false, 'Ballot submission rejected: Missing OTP verification authorization token.', [], 403);
}

try {
    // BEGIN DATABASE TRANSACTION
    $db->beginTransaction();

    // 4. Validate & atomically consume the authorization token
    $authValidation = OtpService::validateAndConsumeAuthorization($authToken, (int)$user['id'], $electionId);
    if (!$authValidation['valid']) {
        throw new Exception($authValidation['error'] ?? 'Voting authorization check failed.');
    }

    // 5. Lock and check voter eligibility row
    $lockStmt = $db->prepare("
        SELECT id, has_voted, eligibility_status 
        FROM voter_eligibility 
        WHERE election_id = ? AND user_id = ? 
        FOR UPDATE
    ");
    $lockStmt->execute([$electionId, $user['id']]);
    $lockedElig = $lockStmt->fetch();

    if (!$lockedElig || $lockedElig['eligibility_status'] !== 'eligible') {
        audit_log('UNAUTHORIZED_VOTE_REQUEST', "Ineligible ballot cast attempt in Election #{$electionId}", (int)$user['id']);
        throw new Exception("Eligibility check failed: You are not authorized to vote in this election.");
    }

    if ((int)$lockedElig['has_voted'] === 1) {
        audit_log('DUPLICATE_VOTE_ATTEMPT', "Duplicate vote attempt blocked in Election #{$electionId}", (int)$user['id']);
        throw new Exception("Duplicate vote rejected: You have already cast your ballot in this election.");
    }

    // 6. Lock and verify election status and official voting window
    $eLockStmt = $db->prepare("SELECT id, status, start_time, end_time FROM elections WHERE id = ? FOR UPDATE");
    $eLockStmt->execute([$electionId]);
    $lockedElec = $eLockStmt->fetch();

    if (!$lockedElec || $lockedElec['status'] !== 'active') {
        throw new Exception("This election is currently closed or suspended.");
    }

    $currentTime = date('Y-m-d H:i:s');
    if ($currentTime < $lockedElec['start_time'] || $currentTime > $lockedElec['end_time']) {
        throw new Exception("The voting window for this election has closed.");
    }

    // 7. Lock and verify candidate validity for this election
    $candLockStmt = $db->prepare("SELECT id, full_name FROM candidates WHERE id = ? AND election_id = ? FOR UPDATE");
    $candLockStmt->execute([$candidateId, $electionId]);
    $cand = $candLockStmt->fetch();

    if (!$cand) {
        throw new Exception("The chosen candidate is not valid for this election ballot.");
    }

    // 8. Generate participation receipt token & anonymized ballot hash
    $receiptToken = hash('sha256', $user['id'] . '-' . $electionId . '-' . microtime(true) . '-' . bin2hex(random_bytes(16)));
    $voteHash = hash('sha256', $candidateId . '-' . microtime(true) . '-' . bin2hex(random_bytes(16)));

    // 9. Insert anonymized ballot (Strict Ballot Secrecy: NO voter id stored in ballots table!)
    $ballotStmt = $db->prepare("INSERT INTO ballots (election_id, candidate_id, vote_hash, cast_at) VALUES (?, ?, ?, NOW())");
    $ballotStmt->execute([$electionId, $candidateId, $voteHash]);

    // 10. Increment candidate vote count
    $candUpd = $db->prepare("UPDATE candidates SET vote_count = vote_count + 1 WHERE id = ?");
    $candUpd->execute([$candidateId]);

    // 11. Mark voter eligibility row as voted, storing receipt token
    $eligUpd = $db->prepare("UPDATE voter_eligibility SET has_voted = 1, voted_at = NOW(), receipt_token = ? WHERE id = ? AND has_voted = 0");
    $eligUpd->execute([$receiptToken, $lockedElig['id']]);

    if ($eligUpd->rowCount() !== 1) {
        audit_log('DUPLICATE_VOTE_ATTEMPT', "Atomic duplicate vote update collision detected for Election #{$electionId}", (int)$user['id']);
        throw new Exception("Duplicate vote rejected: You have already cast your ballot in this election.");
    }

    // 12. Write audit log (Ballot privacy: candidate selection is NEVER recorded in audit trail!)
    audit_log(
        'BALLOT_CAST', 
        "Ballot cast in Election #{$electionId} with verified OTP authorization. Receipt generated.", 
        (int)$user['id']
    );

    // COMMIT ATOMIC TRANSACTION
    $db->commit();

    respond(true, "Your democratic ballot has been successfully recorded!", [
        'receipt_token' => $receiptToken,
        'redirect'      => 'voting-history.php'
    ], 200);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062')) {
        respond(false, "You have already participated in this election.", [], 409);
    }
    error_log("Database Ballot Exception: " . $e->getMessage());
    respond(false, "A database error occurred while processing your ballot.", [], 500);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(false, $e->getMessage(), [], 400);
}
