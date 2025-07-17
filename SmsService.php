<?php
class SmsService {
    private $username;
    private $password;
    private $senderId;
    private $useMock;
    private $apiUrl = 'https://messaging-service.co.tz/api/sms/v1/text/single';
    private $maxRetries = 3;
    private $retryDelayMs = 2000; // 2 seconds

    public function __construct() {
        $this->username = defined('NEXTSMS_USERNAME') ? NEXTSMS_USERNAME : '';
        $this->password = defined('NEXTSMS_PASSWORD') ? NEXTSMS_PASSWORD : '';
        $this->senderId = defined('NEXTSMS_SENDER_ID') ? NEXTSMS_SENDER_ID : '';
        $this->useMock = getenv('USE_MOCK_SMS') === 'true' || empty($this->username) || empty($this->password) || empty($this->senderId);
    }

    private function formatPhoneNumber(string $phoneNumber): string {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D+/', '', $phoneNumber);

        if (strpos($cleaned, '255') === 0) {
            // Already in international format
            if (strpos($cleaned, '255255') === 0) {
                $cleaned = substr($cleaned, 3);
            }
            return $cleaned;
        }

        if (strpos($phoneNumber, '+') === 0 && strpos($cleaned, '255') === 0) {
            return $cleaned;
        }

        if (strpos($cleaned, '0') === 0) {
            return '255' . substr($cleaned, 1);
        }

        if (strlen($cleaned) >= 7 && strlen($cleaned) <= 9) {
            return '255' . $cleaned;
        }

        if (strlen($cleaned) >= 9 && (strpos($cleaned, '7') === 0 || strpos($cleaned, '6') === 0)) {
            return '255' . $cleaned;
        }

        return $cleaned;
    }

    private function isValidTanzanianPhone(string $phoneNumber): bool {
        return preg_match('/^255[67]\d{8}$/', $phoneNumber) === 1;
    }

    private function sendRealSms(string $phoneNumber, string $message): bool {
        $payload = [
            'from' => $this->senderId,
            'to' => $phoneNumber,
            'text' => $message
        ];

        $auth = base64_encode($this->username . ':' . $this->password);

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Bypass SSL verification for local development
        if (getenv('APP_ENV') === 'local' || getenv('APP_ENV') === 'development') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("SMS cURL error: $curlError");
            return false;
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['code']) && $responseData['code'] !== '100') {
            error_log("NextSMS API error: " . ($responseData['message'] ?? 'Unknown error') . ", Code: " . $responseData['code']);
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("NextSMS HTTP error code: $httpCode, Response: $response");
            return false;
        }

        return true;
    }

    private function sendMockSms(string $phoneNumber, string $message): bool {
        error_log("Mock SMS to $phoneNumber: $message");
        // Simulate delay
        usleep(500000);
        return true;
    }

    public function sendSms(string $phoneNumber, string $message): bool {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (!$this->isValidTanzanianPhone($formattedPhone)) {
            error_log("Invalid phone number format: $phoneNumber (formatted: $formattedPhone)");
            return false;
        }

        if ($this->useMock) {
            return $this->sendMockSms($formattedPhone, $message);
        }

        $lastError = null;
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            if ($this->sendRealSms($formattedPhone, $message)) {
                return true;
            }
            $lastError = "Attempt $attempt failed for $formattedPhone";
            error_log($lastError);
            if ($attempt < $this->maxRetries) {
                usleep($this->retryDelayMs * 1000);
            }
        }

        error_log("All SMS send attempts failed for $formattedPhone");
        return false;
    }
}
?>
