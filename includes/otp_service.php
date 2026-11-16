<?php
/**
 * ApexVote OTP Service Layer
 * 
 * Enforces:
 * - Voter choice of Email OTP or Mobile SMS OTP
 * - Cryptographic code generation via random_int()
 * - Keyed HMAC hashing (hash_hmac with secret) of all OTP codes (never plaintext)
 * - Single-use, short-lived voting authorization tokens
 * - Resend cooldowns, attempt lockouts, and rate limits
 * - Strict dissociation from candidate choices (Ballot Privacy)
 * - Real gateway delivery without simulated fake delivery
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms_provider.php';

class OtpService {
    /**
     * Get the HMAC secret key for OTP protection
     */
    public static function getHashSecret(): string {
        $config = require __DIR__ . '/../config/mail_sms_config.php';
        return $config['security']['hash_secret'] ?? (defined('OTP_HASH_SECRET') ? OTP_HASH_SECRET : 'ApexVote_Default_Secret_#2026');
    }

    /**
     * Compute keyed HMAC of an OTP code
     */
    public static function hashOtp(string $otp): string {
        return hash_hmac('sha256', trim($otp), self::getHashSecret());
    }

    /**
     * Compute session binding hash
     */
    public static function getSessionBindingHash(): string {
        $sessionId = session_id();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        return hash('sha256', $sessionId . '|' . $userAgent);
    }

    /**
     * Enforce rate limit check and record hits
     * 
     * @param string $rateKey Unique key e.g. "email_voter_2", "sms_phone_<hash>"
     * @param string $actionType Action descriptor
     * @param int $maxHits Max allowed hits in window
     * @param int $windowSeconds Window in seconds (e.g. 3600 for 1 hour)
     * @return bool True if permitted, false if rate limit exceeded
     */
    public static function checkAndRecordRateLimit(string $rateKey, string $actionType, int $maxHits, int $windowSeconds): bool {
        $db = getDB();
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

        // Clean expired rate limit records older than 24 hours
        $db->query("DELETE FROM otp_rate_limits WHERE last_hit < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

        // Find active window record
        $stmt = $db->prepare("SELECT id, hit_count, window_start FROM otp_rate_limits WHERE rate_key = ? AND action_type = ? AND window_start >= ? LIMIT 1");
        $stmt->execute([$rateKey, $actionType, $windowStart]);
        $record = $stmt->fetch();

        if ($record) {
            if ((int)$record['hit_count'] >= $maxHits) {
                return false; // Limit exceeded
            }
            $upd = $db->prepare("UPDATE otp_rate_limits SET hit_count = hit_count + 1, last_hit = NOW() WHERE id = ?");
            $upd->execute([$record['id']]);
            return true;
        }

        // Start new window
        $ins = $db->prepare("INSERT INTO otp_rate_limits (rate_key, action_type, hit_count, window_start, last_hit) VALUES (?, ?, 1, NOW(), NOW())");
        $ins->execute([$rateKey, $actionType]);
        return true;
    }

    /**
     * Invalidate any previous unconsumed challenges for this voter and election
     */
    public static function invalidatePreviousChallenges(int $voterId, int $electionId): void {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE otp_challenges 
            SET status = 'invalidated' 
            WHERE voter_id = ? AND election_id = ? AND status IN ('pending', 'verified')
        ");
        $stmt->execute([$voterId, $electionId]);
    }

    /**
     * Retrieve the active challenge for voter & election
     */
    public static function getActiveChallenge(int $voterId, int $electionId): ?array {
        $db = getDB();
        $sessionHash = self::getSessionBindingHash();
        $stmt = $db->prepare("
            SELECT * FROM otp_challenges 
            WHERE voter_id = ? 
              AND election_id = ? 
              AND session_binding_hash = ? 
              AND status IN ('pending', 'verified')
              AND (email_expires_at > NOW() OR sms_expires_at > NOW() OR authorization_expires_at > NOW())
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$voterId, $electionId, $sessionHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Request Email OTP Challenge
     */
    public static function requestEmailOtp(int $voterId, int $electionId, string $ipAddress): array {
        $db = getDB();
        $config = require __DIR__ . '/../config/mail_sms_config.php';
        $sec = $config['security'];

        // 1. Fetch authenticated voter details and verify student identity status
        $uStmt = $db->prepare("SELECT id, full_name, email, status, is_verified, student_roster_id FROM users WHERE id = ? AND role = 'voter' LIMIT 1");
        $uStmt->execute([$voterId]);
        $voter = $uStmt->fetch();

        if (!$voter || $voter['status'] !== 'active') {
            audit_log('UNAUTHORIZED_VOTE_REQUEST', "Inactive voter profile attempted OTP for Election #{$electionId}", $voterId);
            return ['success' => false, 'error' => 'Voter profile is not active or unauthorized.'];
        }

        if (empty($voter['is_verified']) || (int)$voter['is_verified'] !== 1) {
            audit_log('UNAUTHORIZED_VOTE_REQUEST', "Unverified student identity attempted OTP for Election #{$electionId}", $voterId);
            return ['success' => false, 'error' => 'Your student voter account is not verified on the authorised student roster.'];
        }

        // Confirm linked student roster record is active
        if (!empty($voter['student_roster_id'])) {
            $rosterStmt = $db->prepare("SELECT id, status FROM authorized_students WHERE id = ? LIMIT 1");
            $rosterStmt->execute([(int)$voter['student_roster_id']]);
            $rosterRecord = $rosterStmt->fetch();
            if (!$rosterRecord || $rosterRecord['status'] !== 'active') {
                audit_log('UNAUTHORIZED_VOTE_REQUEST', "Inactive student roster record attempted OTP for Election #{$electionId}", $voterId);
                return ['success' => false, 'error' => 'Your authorised student record is inactive on the college roster. Please contact campus administration.'];
            }
        }

        // 2. Verify election is currently active and within time window
        $eStmt = $db->prepare("SELECT id, title, status, start_time, end_time FROM elections WHERE id = ? LIMIT 1");
        $eStmt->execute([$electionId]);
        $election = $eStmt->fetch();

        if (!$election || $election['status'] !== 'active') {
            return ['success' => false, 'error' => 'This election is not currently open for voting.'];
        }

        $now = date('Y-m-d H:i:s');
        if ($now < $election['start_time'] || $now > $election['end_time']) {
            return ['success' => false, 'error' => 'The official voting window for this election is closed.'];
        }

        // 3. Verify voter eligibility and duplicate vote status
        $eligStmt = $db->prepare("SELECT id, has_voted, eligibility_status FROM voter_eligibility WHERE election_id = ? AND user_id = ? LIMIT 1");
        $eligStmt->execute([$electionId, $voterId]);
        $elig = $eligStmt->fetch();

        if (!$elig || $elig['eligibility_status'] !== 'eligible') {
            audit_log('UNAUTHORIZED_VOTE_REQUEST', "Ineligible voter requested OTP for Election #{$electionId}", $voterId);
            return ['success' => false, 'error' => 'You are not eligible to vote in this election.'];
        }
        if ((int)$elig['has_voted'] === 1) {
            audit_log('DUPLICATE_VOTE_ATTEMPT', "Voter attempted OTP request after already casting ballot in Election #{$electionId}", $voterId);
            return ['success' => false, 'error' => 'Duplicate vote rejected: You have already participated in this election.'];
        }

        // 4. Rate Limiting Check
        $rateKeyVoter = "email_voter_{$voterId}";
        $rateKeyIp = "email_ip_{$ipAddress}";

        if (!self::checkAndRecordRateLimit($rateKeyVoter, 'email_send', $sec['max_hourly_sends_per_voter'], 3600)) {
            audit_log('OTP_RATE_LIMIT_EXCEEDED', "Hourly email OTP limit exceeded for voter in Election #{$electionId}", $voterId);
            return [
                'success' => false, 
                'error'   => 'Hourly verification limit reached (maximum 5 requests per hour). Please try again later.'
            ];
        }

        if (!self::checkAndRecordRateLimit($rateKeyIp, 'email_send', $sec['max_hourly_sends_per_ip'], 3600)) {
            audit_log('OTP_RATE_LIMIT_EXCEEDED', "Hourly email OTP limit exceeded for IP {$ipAddress}", $voterId);
            return [
                'success' => false, 
                'error'   => 'Too many requests from this network address. Please wait before retrying.'
            ];
        }

        // 5. Check cooldown on existing challenge
        $existing = self::getActiveChallenge($voterId, $electionId);
        if ($existing && !empty($existing['updated_at'])) {
            $secondsSinceLast = time() - strtotime($existing['updated_at']);
            if ($secondsSinceLast < $sec['resend_cooldown_seconds']) {
                $wait = $sec['resend_cooldown_seconds'] - $secondsSinceLast;
                return [
                    'success'            => false, 
                    'error'              => "Please wait {$wait} seconds before requesting a new code.",
                    'cooldown_remaining' => $wait
                ];
            }
        }

        // 6. Generate 6-digit cryptographically secure numeric OTP
        $otp = (string)random_int(100000, 999999);
        $otpHash = self::hashOtp($otp);
        $sessionHash = self::getSessionBindingHash();
        $expiresAt = date('Y-m-d H:i:s', time() + $sec['otp_lifetime_seconds']);

        // 7. Dispatch Email via PHPMailer
        $mailResult = send_vote_verification_email($voter['email'], $voter['full_name'], $otp, $election['title'] ?? 'Collegiate Election');

        if (!$mailResult['success']) {
            audit_log('OTP_DELIVERY_FAILED', 'Failed to dispatch email OTP: ' . substr($mailResult['error'] ?? 'Delivery failure', 0, 100), $voterId);
            return [
                'success' => false,
                'error'   => $mailResult['error'] ?? 'Could not deliver verification email. Please contact the election coordinator.'
            ];
        }

        // 8. Invalidate previous unconsumed challenges
        self::invalidatePreviousChallenges($voterId, $electionId);

        // 9. Insert new challenge
        $ins = $db->prepare("
            INSERT INTO otp_challenges (
                voter_id, election_id, session_binding_hash, email_otp_hash, 
                verification_channel, email_expires_at, email_attempts, status, ip_address, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'email', ?, 0, 'pending', ?, NOW(), NOW())
        ");
        $ins->execute([$voterId, $electionId, $sessionHash, $otpHash, $expiresAt, $ipAddress]);
        $challengeId = (int)$db->lastInsertId();

        audit_log('OTP_EMAIL_REQUESTED', "Email OTP challenge #{$challengeId} generated for Election #{$electionId}", $voterId);

        return [
            'success'            => true,
            'challenge_id'       => $challengeId,
            'channel'            => 'email',
            'masked_destination' => mask_email_address($voter['email']),
            'cooldown_seconds'   => $sec['resend_cooldown_seconds'],
            'expires_in_seconds' => $sec['otp_lifetime_seconds'],
            'message'            => 'Verification code sent to your registered institutional email address.'
        ];
    }

    /**
     * Request Mobile SMS OTP Challenge
     */
    public static function requestSmsOtp(int $voterId, int $electionId, string $countryCode, string $mobileNumber, string $ipAddress): array {
        $db = getDB();
        $config = require __DIR__ . '/../config/mail_sms_config.php';
        $sec = $config['security'];

        // 1. Check if SMS gateway is configured
        if (!SMSProviderFactory::isAvailable()) {
            return [
                'success' => false,
                'error'   => 'Mobile OTP is temporarily unavailable. Please use email verification.'
            ];
        }

        // 2. Fetch voter details
        $uStmt = $db->prepare("SELECT id, full_name, email, status, is_verified FROM users WHERE id = ? AND role = 'voter' LIMIT 1");
        $uStmt->execute([$voterId]);
        $voter = $uStmt->fetch();

        if (!$voter || $voter['status'] !== 'active') {
            return ['success' => false, 'error' => 'Voter profile is not active or unauthorized.'];
        }

        // 3. Verify election is active & window open
        $eStmt = $db->prepare("SELECT id, status, start_time, end_time FROM elections WHERE id = ? LIMIT 1");
        $eStmt->execute([$electionId]);
        $election = $eStmt->fetch();

        if (!$election || $election['status'] !== 'active') {
            return ['success' => false, 'error' => 'This election is not currently open for voting.'];
        }

        $now = date('Y-m-d H:i:s');
        if ($now < $election['start_time'] || $now > $election['end_time']) {
            return ['success' => false, 'error' => 'The official voting window for this election is closed.'];
        }

        // 4. Verify voter eligibility and duplicate vote status
        $eligStmt = $db->prepare("SELECT id, has_voted, eligibility_status FROM voter_eligibility WHERE election_id = ? AND user_id = ? LIMIT 1");
        $eligStmt->execute([$electionId, $voterId]);
        $elig = $eligStmt->fetch();

        if (!$elig || $elig['eligibility_status'] !== 'eligible') {
            return ['success' => false, 'error' => 'You are not eligible to vote in this election.'];
        }
        if ((int)$elig['has_voted'] === 1) {
            return ['success' => false, 'error' => 'Duplicate vote rejected: You have already participated in this election.'];
        }

        // 5. Normalize and validate Indian mobile number format
        $e164 = normalize_phone_e164($countryCode, $mobileNumber);
        if (!$e164) {
            return [
                'success' => false, 
                'error'   => 'Invalid mobile number. Please enter a valid 10-digit Indian mobile number (+91) starting with 6, 7, 8, or 9.'
            ];
        }

        // 6. Rate Limiting Check
        $phoneHash = hash('sha256', $e164);
        $rateKeyPhone = "sms_phone_{$phoneHash}";
        $rateKeyVoter = "sms_voter_{$voterId}";

        if (!self::checkAndRecordRateLimit($rateKeyPhone, 'sms_send', $sec['max_hourly_sends_per_phone'], 3600)) {
            return [
                'success' => false, 
                'error'   => 'Hourly SMS request limit reached for this mobile number.'
            ];
        }

        if (!self::checkAndRecordRateLimit($rateKeyVoter, 'sms_send', $sec['max_hourly_sends_per_voter'], 3600)) {
            return [
                'success' => false, 
                'error'   => 'Hourly verification limit reached for your account.'
            ];
        }

        // 7. Check cooldown on existing challenge
        $existing = self::getActiveChallenge($voterId, $electionId);
        if ($existing && !empty($existing['updated_at'])) {
            $secondsSinceLast = time() - strtotime($existing['updated_at']);
            if ($secondsSinceLast < $sec['resend_cooldown_seconds']) {
                $wait = $sec['resend_cooldown_seconds'] - $secondsSinceLast;
                return [
                    'success'            => false, 
                    'error'              => "Please wait {$wait} seconds before requesting a new code.",
                    'cooldown_remaining' => $wait
                ];
            }
        }

        // 8. Generate 6-digit numeric OTP and dispatch via SMS provider
        $otp = (string)random_int(100000, 999999);
        $otpHash = self::hashOtp($otp);
        $maskedPhone = mask_phone_number($e164);
        $expiresAt = date('Y-m-d H:i:s', time() + $sec['otp_lifetime_seconds']);
        $sessionHash = self::getSessionBindingHash();

        $provider = SMSProviderFactory::create();
        $smsResult = $provider->sendOtp($e164, $otp);

        if (!$smsResult['success']) {
            return [
                'success' => false, 
                'error'   => $smsResult['message'] ?? 'SMS delivery failed. Please use email verification.'
            ];
        }

        // 9. Invalidate previous unconsumed challenges
        self::invalidatePreviousChallenges($voterId, $electionId);

        // 10. Record new SMS challenge
        $ins = $db->prepare("
            INSERT INTO otp_challenges (
                voter_id, election_id, session_binding_hash, sms_otp_hash, 
                verification_channel, sms_selected, sms_destination_masked, sms_destination_hash,
                sms_provider_ref, sms_expires_at, sms_attempts, status, ip_address, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'sms', 1, ?, ?, ?, ?, 0, 'pending', ?, NOW(), NOW())
        ");
        $ins->execute([
            $voterId, $electionId, $sessionHash, $otpHash,
            $maskedPhone, $phoneHash, $smsResult['provider_ref'] ?? null,
            $expiresAt, $ipAddress
        ]);
        $challengeId = (int)$db->lastInsertId();

        audit_log('OTP_SMS_REQUESTED', "SMS OTP challenge #{$challengeId} generated for Election #{$electionId}", $voterId);

        return [
            'success'            => true,
            'challenge_id'       => $challengeId,
            'channel'            => 'sms',
            'masked_destination' => $maskedPhone,
            'cooldown_seconds'   => $sec['resend_cooldown_seconds'],
            'expires_in_seconds' => $sec['otp_lifetime_seconds'],
            'message'            => 'Verification code dispatched to ' . $maskedPhone
        ];
    }

    /**
     * Verify Submitted OTP Code (Handles both Email and SMS challenges)
     * 
     * @param int $challengeId
     * @param int $voterId
     * @param string $submittedCode
     * @return array
     */
    public static function verifyOtp(int $challengeId, int $voterId, string $submittedCode): array {
        $db = getDB();
        $config = require __DIR__ . '/../config/mail_sms_config.php';
        $sec = $config['security'];
        $sessionHash = self::getSessionBindingHash();

        $cleanCode = preg_replace('/[^\d]/', '', trim($submittedCode));
        if (strlen($cleanCode) !== 6) {
            return ['success' => false, 'error' => 'Please enter the complete 6-digit verification code.'];
        }

        // Lock challenge record FOR UPDATE
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                SELECT * FROM otp_challenges 
                WHERE id = ? AND voter_id = ? AND session_binding_hash = ? 
                FOR UPDATE
            ");
            $stmt->execute([$challengeId, $voterId, $sessionHash]);
            $challenge = $stmt->fetch();

            if (!$challenge) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Verification challenge session not found or expired.'];
            }

            if ($challenge['status'] !== 'pending') {
                $db->rollBack();
                return ['success' => false, 'error' => 'This verification challenge is no longer active. Please request a new code.'];
            }

            $isSms = ($challenge['verification_channel'] === 'sms' || (int)$challenge['sms_selected'] === 1);
            $expiresAt = $isSms ? $challenge['sms_expires_at'] : $challenge['email_expires_at'];
            $attempts = (int)($isSms ? $challenge['sms_attempts'] : $challenge['email_attempts']);
            $storedHash = $isSms ? $challenge['sms_otp_hash'] : $challenge['email_otp_hash'];

            // Check Expiry
            if (empty($expiresAt) || strtotime($expiresAt) < time()) {
                $db->prepare("UPDATE otp_challenges SET status = 'expired' WHERE id = ?")->execute([$challengeId]);
                $db->commit();
                audit_log('OTP_EXPIRED', "OTP challenge #{$challengeId} expired upon verification attempt", $voterId);
                return ['success' => false, 'error' => 'Verification code has expired (5-minute validity). Please request a new code.'];
            }

            // Check Attempts Limit
            if ($attempts >= $sec['max_incorrect_attempts']) {
                $db->prepare("UPDATE otp_challenges SET status = 'invalidated' WHERE id = ?")->execute([$challengeId]);
                $db->commit();
                audit_log('OTP_VERIFICATION_FAILED', "Challenge #{$challengeId} blocked: Maximum attempts already exceeded", $voterId);
                return ['success' => false, 'error' => 'Maximum verification attempts exceeded (5/5). Please request a fresh code.'];
            }

            // Compare Keyed HMAC Hash
            $submittedHash = self::hashOtp($cleanCode);
            $isValid = hash_equals($storedHash ?? '', $submittedHash);

            // Backwards compatibility for bcrypt password_hash if migrating
            if (!$isValid && !empty($storedHash) && str_starts_with($storedHash, '$2y$')) {
                $isValid = password_verify($cleanCode, $storedHash);
            }

            if (!$isValid) {
                $newAttempts = $attempts + 1;
                $rem = max(0, $sec['max_incorrect_attempts'] - $newAttempts);
                $attemptCol = $isSms ? 'sms_attempts' : 'email_attempts';

                if ($newAttempts >= $sec['max_incorrect_attempts']) {
                    $upd = $db->prepare("UPDATE otp_challenges SET {$attemptCol} = ?, status = 'invalidated' WHERE id = ?");
                    $upd->execute([$newAttempts, $challengeId]);
                    $db->commit();
                    audit_log('OTP_VERIFICATION_FAILED', "Challenge #{$challengeId} invalidated after {$newAttempts} failed attempts", $voterId);
                    return [
                        'success' => false, 
                        'error'   => 'Incorrect verification code. Maximum attempts reached. This challenge has been invalidated.'
                    ];
                } else {
                    $upd = $db->prepare("UPDATE otp_challenges SET {$attemptCol} = ? WHERE id = ?");
                    $upd->execute([$newAttempts, $challengeId]);
                    $db->commit();
                    audit_log('OTP_VERIFICATION_FAILED', "Failed OTP verification attempt ({$newAttempts}/{$sec['max_incorrect_attempts']}) for challenge #{$challengeId}", $voterId);
                    return [
                        'success'            => false, 
                        'error'              => "Incorrect verification code. {$rem} attempt(s) remaining.",
                        'remaining_attempts' => $rem
                    ];
                }
            }

            // OTP is CORRECT!
            // Issue single-use cryptographic authorization token (5-minute lifetime)
            $authToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $authToken);
            $authExpiresAt = date('Y-m-d H:i:s', time() + $sec['auth_token_lifetime_seconds']);
            $verifiedCol = $isSms ? 'sms_verified_at' : 'email_verified_at';

            $authUpd = $db->prepare("
                UPDATE otp_challenges 
                SET {$verifiedCol} = NOW(),
                    authorization_token_hash = ?, 
                    authorization_expires_at = ?, 
                    status = 'verified' 
                WHERE id = ?
            ");
            $authUpd->execute([$tokenHash, $authExpiresAt, $challengeId]);

            $db->commit();

            audit_log('OTP_VERIFIED', "OTP verified successfully via {$challenge['verification_channel']} for challenge #{$challengeId}", $voterId);
            audit_log('VOTING_AUTH_ISSUED', "Single-use voting authorization token issued for challenge #{$challengeId} in Election #{$challenge['election_id']}", $voterId);

            return [
                'success'             => true,
                'authorized'          => true,
                'authorization_token' => $authToken,
                'channel'             => $challenge['verification_channel'],
                'message'             => 'Collegiate identity verified! Proceeding with ballot submission.'
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("OTP Verification Exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'A database error occurred during verification. Please retry.'];
        }
    }

    /**
     * Atomically Validate and Consume Voting Authorization Token
     */
    public static function validateAndConsumeAuthorization(string $authToken, int $voterId, int $electionId): array {
        $cleanToken = trim($authToken);
        if (empty($cleanToken) || strlen($cleanToken) !== 64) {
            return ['valid' => false, 'error' => 'Invalid voting authorization token format.'];
        }

        $tokenHash = hash('sha256', $cleanToken);
        $sessionHash = self::getSessionBindingHash();
        $db = getDB();

        // Lock authorization challenge row
        $stmt = $db->prepare("
            SELECT id, status, authorization_expires_at 
            FROM otp_challenges 
            WHERE voter_id = ? 
              AND election_id = ? 
              AND authorization_token_hash = ?
              AND session_binding_hash = ?
            FOR UPDATE
        ");
        $stmt->execute([$voterId, $electionId, $tokenHash, $sessionHash]);
        $challenge = $stmt->fetch();

        if (!$challenge) {
            return ['valid' => false, 'error' => 'Voting authorization token not found or unbound.'];
        }

        if ($challenge['status'] === 'consumed') {
            return ['valid' => false, 'error' => 'This voting authorization token has already been consumed.'];
        }

        if ($challenge['status'] !== 'verified') {
            return ['valid' => false, 'error' => 'This voting authorization challenge is not active.'];
        }

        if (empty($challenge['authorization_expires_at']) || strtotime($challenge['authorization_expires_at']) < time()) {
            $db->prepare("UPDATE otp_challenges SET status = 'expired' WHERE id = ?")->execute([$challenge['id']]);
            return ['valid' => false, 'error' => 'Voting authorization token has expired. Please verify again.'];
        }

        // Atomically consume token
        $upd = $db->prepare("UPDATE otp_challenges SET status = 'consumed', consumed_at = NOW() WHERE id = ?");
        $upd->execute([$challenge['id']]);

        audit_log('AUTH_TOKEN_CONSUMED', "Single-use voting authorization token consumed for challenge #{$challenge['id']} in Election #{$electionId}", $voterId);

        return ['valid' => true, 'challenge_id' => (int)$challenge['id']];
    }
}
