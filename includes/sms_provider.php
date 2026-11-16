<?php
/**
 * ApexVote SMS Gateway Adapters
 * Supports Indian Mobile Numbers (+91)
 * 
 * Adapters:
 * - NullSMSProvider (Default when unconfigured - safely reports SMS unavailable)
 * - Fast2SMSProvider (Fast2SMS Indian SMS OTP Gateway)
 * - MSG91SMSProvider (MSG91 Indian DLT SMS Gateway)
 * - TwilioSMSProvider (Twilio Programmable SMS API)
 */

interface SMSProviderInterface {
    /**
     * Dispatch SMS OTP to normalized E.164 phone number
     *
     * @param string $destinationE164 Phone number in E.164 format (+919830123456)
     * @param string $otp 6-digit numeric OTP code
     * @return array Result containing ['success' => bool, 'provider_ref' => ?string, 'message' => string]
     */
    public function sendOtp(string $destinationE164, string $otp): array;

    /**
     * Check if the provider has genuine active credentials configured
     */
    public function isConfigured(): bool;

    /**
     * Mask mobile number for safe display in interfaces
     */
    public function maskPhoneNumber(string $destinationE164): string;
}

/**
 * Normalizes phone numbers to standard E.164 format
 * Strictly validates Indian (+91) mobile numbers (10 digits starting with 6, 7, 8, or 9)
 */
function normalize_phone_e164(string $countryCode, string $mobileNumber): ?string {
    $cleanCode = preg_replace('/[^\d+]/', '', trim($countryCode));
    if (!str_starts_with($cleanCode, '+')) {
        $cleanCode = '+' . ltrim($cleanCode, '+');
    }

    $cleanNumber = preg_replace('/[^\d]/', '', trim($mobileNumber));
    $cleanNumber = ltrim($cleanNumber, '0');

    if ($cleanCode === '+91') {
        if (strlen($cleanNumber) === 12 && str_starts_with($cleanNumber, '91')) {
            $cleanNumber = substr($cleanNumber, 2);
        }
        if (strlen($cleanNumber) !== 10 || !preg_match('/^[6-9]\d{9}$/', $cleanNumber)) {
            return null; // Invalid Indian mobile number
        }
    } else {
        if (strlen($cleanNumber) < 6 || strlen($cleanNumber) > 14) {
            return null;
        }
    }

    return $cleanCode . $cleanNumber;
}

/**
 * Mask mobile number for client-safe feedback
 */
function mask_phone_number(string $e164): string {
    $length = strlen($e164);
    if ($length <= 6) {
        return '******';
    }
    $last4 = substr($e164, -4);
    if (preg_match('/^(\+\d{1,3})/', $e164, $m)) {
        $prefix = $m[1];
        return $prefix . ' ' . str_repeat('*', max(4, $length - strlen($prefix) - 4)) . $last4;
    }
    return '******' . $last4;
}

/**
 * Null SMS Provider
 * Active when SMS provider is unconfigured. Never simulates fake delivery.
 */
class NullSMSProvider implements SMSProviderInterface {
    public function sendOtp(string $destinationE164, string $otp): array {
        return [
            'success'      => false,
            'provider_ref' => null,
            'message'      => 'Mobile OTP is temporarily unavailable. Please use email verification.'
        ];
    }

    public function isConfigured(): bool {
        return false;
    }

    public function maskPhoneNumber(string $destinationE164): string {
        return mask_phone_number($destinationE164);
    }
}

/**
 * Fast2SMS Indian SMS Gateway Adapter
 */
class Fast2SMSProvider implements SMSProviderInterface {
    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = trim($apiKey);
    }

    public function isConfigured(): bool {
        return !empty($this->apiKey) && $this->apiKey !== 'your_fast2sms_api_key_here';
    }

    public function maskPhoneNumber(string $destinationE164): string {
        return mask_phone_number($destinationE164);
    }

    public function sendOtp(string $destinationE164, string $otp): array {
        if (!$this->isConfigured()) {
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'Mobile OTP is temporarily unavailable. Please use email verification.'
            ];
        }

        // Fast2SMS expects 10 digit Indian number without +91
        $numbers10 = substr(preg_replace('/[^\d]/', '', $destinationE164), -10);

        $url = 'https://www.fast2sms.com/dev/bulkV2';
        $postData = json_encode([
            'variables_values' => $otp,
            'route'            => 'otp',
            'numbers'          => $numbers10
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 12
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("Fast2SMS delivery error: {$curlError} (HTTP {$httpCode})");
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'SMS gateway network error. Please try email verification.'
            ];
        }

        $res = json_decode($response, true);
        if (!empty($res['return']) && $res['return'] === true) {
            $ref = $res['request_id'] ?? ('F2S-' . bin2hex(random_bytes(6)));
            return [
                'success'      => true,
                'provider_ref' => $ref,
                'message'      => 'SMS OTP dispatched successfully via Fast2SMS gateway.'
            ];
        }

        $errDetail = $res['message'][0] ?? ($res['message'] ?? 'Gateway rejected SMS request.');
        error_log("Fast2SMS gateway rejection: " . json_encode($res));
        return [
            'success'      => false,
            'provider_ref' => null,
            'message'      => 'SMS delivery was rejected by gateway: ' . htmlspecialchars($errDetail)
        ];
    }
}

/**
 * MSG91 Indian DLT-Compliant SMS Gateway Adapter
 */
class MSG91SMSProvider implements SMSProviderInterface {
    private string $authKey;
    private string $senderId;
    private string $dltTeId;

    public function __construct(string $authKey, string $senderId = 'APXVOT', string $dltTeId = '') {
        $this->authKey  = trim($authKey);
        $this->senderId = trim($senderId);
        $this->dltTeId  = trim($dltTeId);
    }

    public function isConfigured(): bool {
        return !empty($this->authKey) && $this->authKey !== 'your_msg91_auth_key_here';
    }

    public function maskPhoneNumber(string $destinationE164): string {
        return mask_phone_number($destinationE164);
    }

    public function sendOtp(string $destinationE164, string $otp): array {
        if (!$this->isConfigured()) {
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'Mobile OTP is temporarily unavailable. Please use email verification.'
            ];
        }

        $cleanPhone = ltrim(preg_replace('/[^\d]/', '', $destinationE164), '+');
        $url = 'https://control.msg91.com/api/v5/otp';
        $params = [
            'template_id' => $this->dltTeId,
            'mobile'      => $cleanPhone,
            'otp'         => $otp
        ];

        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'authkey: ' . $this->authKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 12
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("MSG91 delivery error: {$curlError} (HTTP {$httpCode})");
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'SMS gateway network error. Please try email verification.'
            ];
        }

        $res = json_decode($response, true);
        if (!empty($res['type']) && strtolower($res['type']) === 'success') {
            return [
                'success'      => true,
                'provider_ref' => $res['message'] ?? ('MSG91-' . bin2hex(random_bytes(6))),
                'message'      => 'SMS OTP dispatched successfully via MSG91.'
            ];
        }

        error_log("MSG91 rejection: " . json_encode($res));
        return [
            'success'      => false,
            'provider_ref' => null,
            'message'      => 'SMS delivery rejected by MSG91 provider.'
        ];
    }
}

/**
 * Twilio SMS Gateway Adapter
 */
class TwilioSMSProvider implements SMSProviderInterface {
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber) {
        $this->accountSid = trim($accountSid);
        $this->authToken  = trim($authToken);
        $this->fromNumber = trim($fromNumber);
    }

    public function isConfigured(): bool {
        return !empty($this->accountSid) && !empty($this->authToken) && $this->accountSid !== 'your_twilio_sid_here';
    }

    public function maskPhoneNumber(string $destinationE164): string {
        return mask_phone_number($destinationE164);
    }

    public function sendOtp(string $destinationE164, string $otp): array {
        if (!$this->isConfigured()) {
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'Mobile OTP is temporarily unavailable. Please use email verification.'
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        $postData = [
            'From' => $this->fromNumber,
            'To'   => $destinationE164,
            'Body' => "ApexVote: Your collegiate ballot verification code is {$otp}. Valid for 5 minutes. Do not share."
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->accountSid . ':' . $this->authToken,
            CURLOPT_TIMEOUT        => 12
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || ($httpCode !== 200 && $httpCode !== 201)) {
            error_log("Twilio delivery error: {$curlError} (HTTP {$httpCode})");
            return [
                'success'      => false,
                'provider_ref' => null,
                'message'      => 'Twilio gateway network error. Please try email verification.'
            ];
        }

        $res = json_decode($response, true);
        if (!empty($res['sid']) && in_array($res['status'] ?? '', ['queued', 'sending', 'sent'])) {
            return [
                'success'      => true,
                'provider_ref' => $res['sid'],
                'message'      => 'SMS OTP dispatched successfully via Twilio.'
            ];
        }

        error_log("Twilio rejection: " . json_encode($res));
        return [
            'success'      => false,
            'provider_ref' => null,
            'message'      => 'SMS delivery rejected by Twilio gateway.'
        ];
    }
}

/**
 * SMS Provider Factory
 */
class SMSProviderFactory {
    public static function create(): SMSProviderInterface {
        $config = require __DIR__ . '/../config/mail_sms_config.php';
        $smsConfig = $config['sms'];
        $providerName = strtolower($smsConfig['provider'] ?? 'none');

        switch ($providerName) {
            case 'fast2sms':
                $p = new Fast2SMSProvider($smsConfig['fast2sms']['api_key'] ?? '');
                return $p->isConfigured() ? $p : new NullSMSProvider();

            case 'msg91':
                $p = new MSG91SMSProvider(
                    $smsConfig['msg91']['auth_key'] ?? '',
                    $smsConfig['msg91']['sender_id'] ?? 'APXVOT',
                    $smsConfig['msg91']['dlt_te_id'] ?? ''
                );
                return $p->isConfigured() ? $p : new NullSMSProvider();

            case 'twilio':
                $p = new TwilioSMSProvider(
                    $smsConfig['twilio']['account_sid'] ?? '',
                    $smsConfig['twilio']['auth_token'] ?? '',
                    $smsConfig['twilio']['from_number'] ?? ''
                );
                return $p->isConfigured() ? $p : new NullSMSProvider();

            default:
                return new NullSMSProvider();
        }
    }

    /**
     * Helper to check if any genuine SMS provider is active and ready
     */
    public static function isAvailable(): bool {
        $provider = self::create();
        return $provider->isConfigured();
    }
}
