<?php
/**
 * Official Voting Action Page
 * Features Mandatory Email OTP + Optional User-Selected SMS OTP Verification,
 * Database-Level Row Locking, Anti-Double Vote Prevention, and Ballot Secrecy
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp_service.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/sms_provider.php';

require_voter();

$electionId = (int)($_REQUEST['id'] ?? 0);
if ($electionId <= 0) {
    error_log("[APEXVOTE_DIAGNOSTIC] INVALID_ELECTION_ID: Missing or invalid election ID provided (Received: " . var_export($_REQUEST['id'] ?? null, true) . ")");
    set_flash('danger', 'Please choose a valid active election to vote.');
    redirect('elections.php');
}

$pageTitle = 'Cast Your Official Ballot';
$isDashboard = true;
$isAdmin = false;
$user = current_user();
$db = getDB();
$error = '';

if (!$user) {
    error_log("[APEXVOTE_DIAGNOSTIC] SESSION_MISSING: User session could not be retrieved in vote.php");
    set_flash('danger', 'Your session has expired. Please log in again.');
    redirect('login.php');
}

// Check election existence
$stmt = $db->prepare("SELECT * FROM elections WHERE id = ? LIMIT 1");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    error_log("[APEXVOTE_DIAGNOSTIC] INVALID_ELECTION_ID: Election #{$electionId} not found in database.");
    set_flash('danger', 'Election not found.');
    redirect('elections.php');
}

if ($election['status'] !== 'active') {
    error_log("[APEXVOTE_DIAGNOSTIC] ELECTION_NOT_ACTIVE: Election #{$electionId} status is '{$election['status']}', expected 'active'.");
    set_flash('warning', 'This election is not currently open for voting.');
    redirect('election-details.php?id=' . $electionId);
}

// Check election voting window timeline
$currentTime = date('Y-m-d H:i:s');
if (!empty($election['start_time']) && $currentTime < $election['start_time']) {
    error_log("[APEXVOTE_DIAGNOSTIC] ELECTION_NOT_ACTIVE: Voting window has not opened yet for Election #{$electionId} (Starts: {$election['start_time']})");
    set_flash('warning', 'The official voting window for this election has not opened yet.');
    redirect('election-details.php?id=' . $electionId);
}
if (!empty($election['end_time']) && $currentTime > $election['end_time']) {
    error_log("[APEXVOTE_DIAGNOSTIC] ELECTION_NOT_ACTIVE: Voting window has ended for Election #{$electionId} (Ended: {$election['end_time']})");
    set_flash('warning', 'The official voting window for this election is closed.');
    redirect('election-details.php?id=' . $electionId);
}

// Check voter verified status and active roster membership
if (empty($user['is_verified']) || (int)$user['is_verified'] !== 1) {
    error_log("[APEXVOTE_DIAGNOSTIC] UNVERIFIED_ROSTER_IDENTITY: User ID {$user['id']} has is_verified=" . var_export($user['is_verified'] ?? null, true) . ". Redirecting to dashboard.");
    set_flash('danger', 'Your student identity is not verified on the authorised student roster.');
    redirect('dashboard.php');
}

if (!empty($user['student_roster_id'])) {
    $rosterChk = $db->prepare("SELECT id, status FROM authorized_students WHERE id = ? LIMIT 1");
    $rosterChk->execute([(int)$user['student_roster_id']]);
    $rosterRec = $rosterChk->fetch();
    if (!$rosterRec || $rosterRec['status'] !== 'active') {
        error_log("[APEXVOTE_DIAGNOSTIC] INACTIVE_ROSTER_RECORD: User ID {$user['id']} linked roster #{$user['student_roster_id']} status is " . var_export($rosterRec['status'] ?? 'NONE', true) . ". Redirecting to dashboard.");
        set_flash('danger', 'Your student record on the authorised college roster is inactive. Please contact administration.');
        redirect('dashboard.php');
    }
}

// Check voter eligibility and previous vote status
$eligStmt = $db->prepare("SELECT * FROM voter_eligibility WHERE election_id = ? AND user_id = ? LIMIT 1");
$eligStmt->execute([$electionId, $user['id']]);
$eligibility = $eligStmt->fetch();

if (!$eligibility || $eligibility['eligibility_status'] !== 'eligible') {
    error_log("[APEXVOTE_DIAGNOSTIC] VOTER_NOT_ELIGIBLE: User ID {$user['id']} is not eligible for Election #{$electionId} (Status: " . var_export($eligibility['eligibility_status'] ?? 'NONE', true) . ")");
    set_flash('danger', 'You are not eligible to participate in this departmental election.');
    redirect('election-details.php?id=' . $electionId);
}

if ((int)$eligibility['has_voted'] === 1) {
    error_log("[APEXVOTE_DIAGNOSTIC] ALREADY_VOTED: User ID {$user['id']} has already voted in Election #{$electionId}. Redirecting to receipt history.");
    set_flash('info', 'You have already cast your ballot in this election. Duplicate votes are strictly prohibited.');
    redirect('voting-history.php');
}

// Fetch candidates
$cStmt = $db->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY ballot_number ASC, id ASC");
$cStmt->execute([$electionId]);
$candidates = $cStmt->fetchAll();

if (empty($candidates)) {
    error_log("[APEXVOTE_DIAGNOSTIC] NO_CANDIDATES: Election #{$electionId} has no certified candidates configured.");
    set_flash('warning', 'No certified candidates have been registered for this election yet.');
    redirect('election-details.php?id=' . $electionId);
}

// Pre-compute user phone digits and masked email for OTP interface
$userPhoneDigits = preg_replace('/[^\d]/', '', $user['phone'] ?? '');
if (strlen($userPhoneDigits) === 12 && str_starts_with($userPhoneDigits, '91')) {
    $userPhoneDigits = substr($userPhoneDigits, 2);
}
$maskedUserEmail = mask_email_address($user['email']);
$preselectedCandidateId = (int)($_GET['candidate_id'] ?? 0);
$smsAvailable = SMSProviderFactory::isAvailable();

// Handle Traditional Form Submission with ACID Transaction & Authorization Validation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh the page and submit again.';
    } else {
        $candidateId = (int)($_POST['candidate_id'] ?? 0);
        $authToken = trim($_POST['authorization_token'] ?? '');

        if ($candidateId <= 0) {
            $error = 'Please select a certified candidate before submitting your ballot.';
        } elseif (empty($authToken)) {
            $error = 'Verification required: You must complete mandatory OTP verification before casting your ballot.';
        } else {
            try {
                // START DATABASE TRANSACTION
                $db->beginTransaction();

                // 1. Atomically validate and consume authorization token
                $authValidation = OtpService::validateAndConsumeAuthorization($authToken, (int)$user['id'], $electionId);
                if (!$authValidation['valid']) {
                    throw new Exception($authValidation['error'] ?? 'Voting authorization check failed.');
                }

                // 2. Lock voter eligibility row
                $lockStmt = $db->prepare("
                    SELECT id, has_voted, eligibility_status 
                    FROM voter_eligibility 
                    WHERE election_id = ? AND user_id = ? 
                    FOR UPDATE
                ");
                $lockStmt->execute([$electionId, $user['id']]);
                $lockedElig = $lockStmt->fetch();

                if (!$lockedElig || $lockedElig['eligibility_status'] !== 'eligible') {
                    throw new Exception("Authorization check failed: Voter is not eligible for this election.");
                }

                if ((int)$lockedElig['has_voted'] === 1) {
                    throw new Exception("Duplicate vote rejected: You have already cast your ballot in this election.");
                }

                // 3. Lock and check election status & timeline
                $eLockStmt = $db->prepare("SELECT id, status, start_time, end_time FROM elections WHERE id = ? FOR UPDATE");
                $eLockStmt->execute([$electionId]);
                $lockedElec = $eLockStmt->fetch();

                if (!$lockedElec || $lockedElec['status'] !== 'active') {
                    throw new Exception("Voting for this election has been concluded or suspended.");
                }

                $currentTime = date('Y-m-d H:i:s');
                if ($currentTime < $lockedElec['start_time'] || $currentTime > $lockedElec['end_time']) {
                    throw new Exception("The official voting window for this election is closed.");
                }

                // 4. Verify candidate validity
                $candLockStmt = $db->prepare("SELECT id, full_name FROM candidates WHERE id = ? AND election_id = ? FOR UPDATE");
                $candLockStmt->execute([$candidateId, $electionId]);
                $cand = $candLockStmt->fetch();

                if (!$cand) {
                    throw new Exception("The selected candidate is not valid for this election ballot.");
                }

                // 5. Generate random participation receipt token
                $receiptToken = hash('sha256', $user['id'] . '-' . $electionId . '-' . microtime(true) . '-' . bin2hex(random_bytes(16)));
                $voteHash = hash('sha256', $candidateId . '-' . microtime(true) . '-' . bin2hex(random_bytes(16)));

                // 6. Insert anonymized ballot (NO user ID attached, preserving ballot secrecy!)
                $ballotStmt = $db->prepare("INSERT INTO ballots (election_id, candidate_id, vote_hash, cast_at) VALUES (?, ?, ?, NOW())");
                $ballotStmt->execute([$electionId, $candidateId, $voteHash]);

                // 7. Increment candidate vote count
                $candUpd = $db->prepare("UPDATE candidates SET vote_count = vote_count + 1 WHERE id = ?");
                $candUpd->execute([$candidateId]);

                // 8. Mark eligibility row as has_voted = 1 with timestamp and receipt token
                $eligUpd = $db->prepare("UPDATE voter_eligibility SET has_voted = 1, voted_at = NOW(), receipt_token = ? WHERE id = ? AND has_voted = 0");
                $eligUpd->execute([$receiptToken, $lockedElig['id']]);

                if ($eligUpd->rowCount() !== 1) {
                    audit_log('DUPLICATE_VOTE_ATTEMPT', "Duplicate vote update collision in Election #{$electionId}", $user['id']);
                    throw new Exception("Duplicate vote rejected: You have already cast your ballot in this election.");
                }

                // 9. Log audit trail (Notice: candidate choice is NOT recorded in audit logs!)
                audit_log('BALLOT_CAST', "Voter participated in Election #{$electionId} with verified OTP authorization. Receipt generated.", $user['id']);

                // COMMIT TRANSACTION
                $db->commit();

                set_flash('success', "Your ballot has been recorded and your participation receipt reference generated!");
                redirect('voting-history.php');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="margin-bottom: 2rem;">
    <a href="<?= url('election-details.php?id=' . $electionId) ?>" style="font-size: 0.88rem; color: var(--text-muted);">&larr; Back to Election Details</a>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 0.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--accent-primary); font-weight: 700; letter-spacing: 0.08em; margin-bottom: 0.35rem;">
                Official Collegiate Ballot &bull; Campus Election Cycle 2026–2027
            </div>
            <h2 style="margin: 0; font-size: 1.65rem; color: var(--text-primary); font-weight: 800;">Official Ballot: <?= e($election['title']) ?></h2>
            <p style="margin: 0.4rem 0 0; color: var(--text-secondary); font-size: 0.95rem;">Select exactly one certified candidate below to represent your student body.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <span class="status-badge badge-category"><?= e($election['category']) ?></span>
            <span class="status-badge badge-emerald">
                <span class="status-indicator-ping"></span> Voting Live
            </span>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- Voting Form -->
<form id="voting-ballot-form" action="<?= url('vote.php?id=' . $electionId) ?>" method="POST" data-election-id="<?= $electionId ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="election_id" value="<?= $electionId ?>">

    <div class="candidate-voting-grid">
        <?php foreach ($candidates as $c): ?>
            <?php 
                $candPhoto = preg_replace('#^assets/#', '', $c['photo_url'] ?? '') ?: 'images/default-avatar.svg'; 
                $isPreselected = ($preselectedCandidateId > 0 && (int)$c['id'] === $preselectedCandidateId);
            ?>
            <div class="candidate-card-selectable <?= $isPreselected ? 'selected' : '' ?>" 
                 data-candidate-id="<?= (int)$c['id'] ?>"
                 data-candidate-name="<?= e($c['full_name']) ?>"
                 data-candidate-position="<?= e($c['position']) ?> (<?= e($c['party_or_slate']) ?>)">
                
                <div style="position: absolute; top: 1.25rem; right: 1.25rem;">
                    <input type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" <?= $isPreselected ? 'checked' : '' ?>
                           style="width: 22px; height: 22px; accent-color: var(--accent-primary); cursor: pointer;" aria-label="Select <?= e($c['full_name']) ?>">
                </div>

                <div class="candidate-header">
                    <img src="<?= asset($candPhoto) ?>" 
                         alt="<?= e($c['full_name']) ?>" class="candidate-avatar">
                    <div style="padding-right: 2rem;">
                        <span class="status-badge badge-slate" style="font-size: 0.72rem; margin-bottom: 0.25rem; background: rgba(244, 203, 69, 0.1); color: #F4CB45; border: 1px solid rgba(244, 203, 69, 0.25);">Official Nominee &bull; Ballot #<?= (int)$c['ballot_number'] ?></span>
                        <div class="candidate-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary);"><?= e($c['full_name']) ?></div>
                        <div class="candidate-slate" style="color: var(--text-secondary); font-size: 0.85rem;"><?= e($c['party_or_slate']) ?></div>
                    </div>
                </div>

                <div style="font-size: 0.85rem; color: var(--accent-primary); font-weight: 600; margin-bottom: 0.75rem;">
                    Office: <?= e($c['position']) ?>
                </div>

                <div class="candidate-manifesto">
                    <?= nl2br(e($c['manifesto'])) ?>
                </div>

                <div class="candidate-select-btn">
                    <button type="button" class="btn <?= $isPreselected ? 'btn-primary' : 'btn-secondary' ?> btn-block btn-sm" style="pointer-events: none;">
                        <?= $isPreselected ? 'Selected Nominee &check;' : 'Click to Choose Candidate' ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Submission Control Bar -->
    <div class="glass-card" style="margin-top: 2rem; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border: 1px solid var(--border-subtle);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(244, 203, 69, 0.1); border: 1px solid rgba(244, 203, 69, 0.25); display: flex; align-items: center; justify-content: center; color: var(--accent-primary); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div>
                <strong style="color: var(--text-primary); display: block;">Ballot Privacy & Two-Factor OTP Protection</strong>
                <span style="font-size: 0.82rem; color: var(--text-secondary);">Mandatory email verification plus optional user-selected SMS ensures only you cast your vote.</span>
            </div>
        </div>

        <button type="button" id="btn-open-vote-modal" class="btn btn-secondary btn-lg" 
                data-modal-target="#confirm-vote-modal" disabled>
            Cast Vote Now &rarr;
        </button>
    </div>

    <!-- Accessible Confirmation & OTP Verification Modal Window -->
    <div id="confirm-vote-modal" class="modal-overlay">
        <div class="modal-content otp-modal-dialog">

            <!-- ======================================================= -->
            <!-- PHASE 1: Candidate Review & Method Selection            -->
            <!-- ======================================================= -->
            <div id="modal-phase-review">
                <div class="modal-header">
                    <h3 style="display: flex; align-items: center; gap: 0.6rem; font-size: 1.25rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Confirm Selection &amp; Verify Identity
                    </h3>
                    <button type="button" data-close-modal style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <div class="modal-body">
                    <p style="font-size: 0.92rem; margin-bottom: 1rem; color: var(--text-secondary);">
                        Review your chosen candidate for:
                        <strong style="color: var(--text-primary); display: block; margin-top: 0.2rem; font-size: 1.05rem;"><?= e($election['title']) ?></strong>
                    </p>

                    <div style="background: rgba(244, 203, 69, 0.08); border: 1px solid rgba(244, 203, 69, 0.3); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; text-align: center;">
                        <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--accent-primary); letter-spacing: 0.05em; margin-bottom: 0.2rem;">
                            Selected Candidate Nominee
                        </div>
                        <div id="modal-chosen-candidate-name" style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary);">
                            [Candidate Selected]
                        </div>
                        <div id="modal-chosen-candidate-position" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                            [Position & Slate]
                        </div>
                    </div>

                    <!-- Genuine OTP Verification Method Selection -->
                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            Select Identity Verification Method
                        </label>

                        <!-- Method 1: Email OTP (Default & Institutional) -->
                        <label class="auth-method-card selected" id="method-card-email">
                            <div class="auth-method-header">
                                <span class="auth-method-title">
                                    <input type="radio" name="otp_channel" value="email" checked style="accent-color: var(--accent-primary); width: 18px; height: 18px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <span>Institutional Email OTP</span>
                                </span>
                                <span class="status-badge badge-slate" style="background: rgba(244, 203, 69, 0.1); color: var(--accent-primary); border-color: rgba(244, 203, 69, 0.3); font-size: 0.72rem;">Mandatory</span>
                            </div>
                            <div class="auth-method-desc">
                                A single-use 6-digit code will be dispatched to your registered institutional address:
                                <strong style="color: var(--text-primary); display: block; margin-top: 0.25rem;" id="email-masked-address"><?= e($maskedUserEmail) ?></strong>
                            </div>
                        </label>

                        <!-- Method 2: Mobile SMS OTP (Kept unavailable) -->
                        <?php if ($smsAvailable): ?>
                            <label class="auth-method-card" id="method-card-sms">
                                <div class="auth-method-header">
                                    <span class="auth-method-title">
                                        <input type="radio" name="otp_channel" value="sms" style="accent-color: var(--accent-primary); width: 18px; height: 18px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Mobile SMS OTP</span>
                                    </span>
                                    <span class="status-badge badge-emerald" style="font-size: 0.72rem;">Available</span>
                                </div>
                                <div class="auth-method-desc">
                                    Receive a 6-digit verification code directly on your mobile phone via SMS.
                                </div>
                                <div class="auth-method-phone-box" id="sms-phone-container" style="display: none;">
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.4rem;">
                                        Enter your 10-digit Indian mobile number:
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <span style="background: rgba(0,0,0,0.4); border: 1px solid var(--border-subtle); padding: 0.6rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.88rem; color: var(--text-primary); font-weight: 600;">🇮🇳 +91</span>
                                        <input type="tel" id="sms-phone-number" class="form-control" placeholder="9876543210" maxlength="10" value="<?= e($userPhoneDigits) ?>" style="flex: 1; padding: 0.6rem 0.85rem; font-size: 0.95rem; font-family: monospace;">
                                    </div>
                                    <div style="font-size: 0.73rem; color: var(--text-muted); margin-top: 0.35rem;">
                                        Used strictly for single-use verification. Will not link to your anonymous ballot.
                                    </div>
                                </div>
                            </label>
                        <?php else: ?>
                            <div class="auth-method-card disabled" id="method-card-sms">
                                <div class="auth-method-header">
                                    <span class="auth-method-title">
                                        <input type="radio" name="otp_channel" value="sms" disabled style="width: 18px; height: 18px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Mobile SMS OTP</span>
                                    </span>
                                    <span class="status-badge badge-amber" style="font-size: 0.72rem;">Unavailable</span>
                                </div>
                                <div class="alert-box alert-warning" style="margin: 0.65rem 0 0 1.85rem; padding: 0.5rem 0.75rem; font-size: 0.82rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <span>Mobile SMS option is unavailable until a genuine SMS gateway is configured. Please use institutional email verification.</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="request-otp-error" class="alert-box alert-danger" style="display: none; margin-bottom: 0;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" data-close-modal class="btn btn-secondary">
                        Go Back &amp; Change
                    </button>
                    <button type="button" id="btn-send-verification-code" class="btn btn-primary">
                        Confirm &amp; Verify Email &rarr;
                    </button>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- PHASE 2: Dedicated 6-Digit Verification Code Entry      -->
            <!-- ======================================================= -->
            <div id="modal-phase-verification" style="display: none;">
                <div class="modal-header">
                    <div>
                        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--accent-primary); font-weight: 700; letter-spacing: 0.05em;">Step 2 of 3: Security Verification</div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: var(--text-primary);">Enter 6-Digit Verification Code</h3>
                    </div>
                    <button type="button" data-close-modal style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <div class="modal-body" style="padding-top: 1.25rem;">
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.15rem; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.35rem;">
                            A 6-digit verification code was dispatched to:
                        </div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--accent-primary);" id="disp-auth-destination">
                            <?= e($maskedUserEmail) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">
                            ⏳ Valid for 5 minutes. Keyed HMAC-SHA256 validation with maximum 5 attempts.
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">
                            Enter 6-Digit Passcode
                        </label>
                        
                        <!-- 6 Individual Accessible Digit Input Boxes with Paste & Focus Navigation -->
                        <div class="otp-inputs-wrapper" role="group" aria-label="Six digit verification code input">
                            <input type="text" class="otp-digit" data-index="0" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" aria-label="Digit 1 of 6" autofocus>
                            <input type="text" class="otp-digit" data-index="1" maxlength="1" pattern="[0-9]" inputmode="numeric" aria-label="Digit 2 of 6">
                            <input type="text" class="otp-digit" data-index="2" maxlength="1" pattern="[0-9]" inputmode="numeric" aria-label="Digit 3 of 6">
                            <input type="text" class="otp-digit" data-index="3" maxlength="1" pattern="[0-9]" inputmode="numeric" aria-label="Digit 4 of 6">
                            <input type="text" class="otp-digit" data-index="4" maxlength="1" pattern="[0-9]" inputmode="numeric" aria-label="Digit 5 of 6">
                            <input type="text" class="otp-digit" data-index="5" maxlength="1" pattern="[0-9]" inputmode="numeric" aria-label="Digit 6 of 6">
                        </div>
                        <!-- Hidden consolidated value field -->
                        <input type="hidden" id="verification-otp-input" value="">
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; font-size: 0.85rem;">
                        <span id="otp-timer-wrap" class="resend-timer-txt">Resend code available in <strong id="otp-timer-val">60</strong>s</span>
                        <button type="button" id="btn-resend-otp" class="btn btn-link btn-xs" style="display: none; color: var(--accent-primary); padding: 0; font-weight: 600;">Resend Code</button>
                    </div>

                    <div id="otp-feedback-msg" class="feedback-msg" style="margin-top: 0.75rem;"></div>
                </div>

                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" id="btn-back-to-review" class="btn btn-secondary">
                        &larr; Change Selection
                    </button>
                    <input type="hidden" name="authorization_token" id="modal-auth-token" value="">
                    <button type="button" id="btn-verify-otp" class="btn btn-primary" disabled>
                        Verify Code &rarr;
                    </button>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- PHASE 2.5: Final Ballot Submission Confirmation         -->
            <!-- ======================================================= -->
            <div id="modal-phase-confirm-ballot" style="display: none;">
                <div class="modal-header">
                    <div>
                        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--success); font-weight: 700; letter-spacing: 0.05em;">Step 3 of 3: Final Ballot Review</div>
                        <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-primary);">Confirm Official Ballot Submission</h3>
                    </div>
                    <button type="button" data-close-modal style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <div class="modal-body" style="padding-top: 1.25rem;">
                    <div style="background: rgba(56, 185, 130, 0.1); border: 1px solid rgba(56, 185, 130, 0.35); border-radius: var(--radius-md); padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <div style="font-size: 0.9rem; color: var(--text-primary); font-weight: 600;">
                            Identity Verified via Institutional Email OTP
                        </div>
                    </div>

                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        You are about to cast your official democratic ballot in:
                        <strong style="color: var(--text-primary); display: block; font-size: 1rem; margin-top: 0.2rem;"><?= e($election['title']) ?></strong>
                    </p>

                    <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.15rem; margin-bottom: 1.25rem; text-align: center;">
                        <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--accent-primary); font-weight: 700; letter-spacing: 0.08em; margin-bottom: 0.3rem;">Selected Candidate</div>
                        <div id="confirm-candidate-name" style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary);">[Candidate Selected]</div>
                        <div id="confirm-candidate-position" style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0.25rem;">[Position & Slate]</div>
                    </div>

                    <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; padding: 0.75rem; background: rgba(244, 203, 69, 0.06); border-radius: var(--radius-sm); border-left: 3px solid var(--accent-primary);">
                        🔒 <strong>Strict Ballot Privacy:</strong> Your vote will be recorded anonymously without storing your identity with this candidate selection. Once confirmed, your ballot cannot be altered or re-cast.
                    </div>

                    <div id="final-submission-error" class="alert-box alert-danger" style="display: none; margin-top: 1rem; margin-bottom: 0;"></div>
                </div>

                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" data-close-modal class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button" id="btn-confirm-submit-ballot" class="btn btn-primary btn-lg">
                        Confirm &amp; Cast Official Ballot &rarr;
                    </button>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- PHASE 3: Success & Participation Receipt               -->
            <!-- ======================================================= -->
            <div id="modal-phase-success" style="display: none;">
                <div class="modal-body" style="text-align: center; padding: 2.5rem 1.75rem;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(56, 185, 130, 0.15); border: 2px solid var(--success); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; color: var(--success);">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>

                    <h3 style="font-size: 1.4rem; color: var(--text-primary); margin-bottom: 0.5rem;">Ballot Successfully Recorded!</h3>
                    <p style="font-size: 0.92rem; color: var(--text-secondary); max-width: 440px; margin: 0 auto 1.75rem auto;">
                        Your democratic vote has been anonymously cast and cryptographically recorded. Your participation receipt reference is below:
                    </p>

                    <div style="background: rgba(0, 0, 0, 0.4); border: 1px dashed var(--accent-primary); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 2rem;">
                        <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--accent-primary); font-weight: 700; letter-spacing: 0.1em; margin-bottom: 0.4rem;">
                            Official Participation Receipt Reference
                        </div>
                        <div id="success-receipt-token" style="font-family: 'Consolas', 'Courier New', monospace; font-size: 0.95rem; color: var(--text-primary); word-break: break-all; font-weight: 600;">
                            Generating receipt...
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; justify-content: center;">
                        <a href="<?= url('voting-history.php') ?>" class="btn btn-primary btn-lg">
                            View Official Voting Receipts &rarr;
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<script src="<?= asset('js/vote-otp.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/js/vote-otp.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
