<?php
require_once __DIR__ . '/config.php';

class PesapalService {
    private $consumerKey;
    private $consumerSecret;
    private $apiUrl;

    public function __construct() {
        $this->consumerKey = PESAPAL_CONSUMER_KEY;
        $this->consumerSecret = PESAPAL_CONSUMER_SECRET;
        $this->apiUrl = PESAPAL_API_URL;
    }

    public function getAuthToken() {
        $url = $this->apiUrl . '/api/Auth/RequestToken';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        $data = [
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Bypass SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200) {
            $responseData = json_decode($response, true);
            return $responseData['token'] ?? null;
        }

        return null;
    }

    public function registerIPNUrl($token, $ipnUrl) {
        $url = $this->apiUrl . '/api/URLSetup/RegisterIPN';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];
        $data = [
            'url' => $ipnUrl,
            'ipn_notification_type' => 'GET'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Bypass SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Register IPN URL response status: " . $http_status);
        error_log("Register IPN URL response body: " . $response);

        if ($http_status === 200) {
            return json_decode($response, true);
        }
        return null;
    }

    public function getRegisteredIPNs($token) {
        $url = $this->apiUrl . '/api/URLSetup/GetIpnList';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Bypass SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getOrRegisterIpnUrlId($token, $callbackUrl) {
        // 1. Get existing IPNs
        $registeredIpns = $this->getRegisteredIPNs($token);
        if ($registeredIpns && is_array($registeredIpns)) {
            foreach ($registeredIpns as $ipn) {
                if (isset($ipn['url']) && $ipn['url'] === $callbackUrl) {
                    error_log("Found existing IPN URL ID: " . $ipn['ipn_id']);
                    return $ipn['ipn_id'];
                }
            }
        }

        // 2. If not found, register it
        error_log("No existing IPN URL found for $callbackUrl. Registering...");
        $newIpn = $this->registerIPNUrl($token, $callbackUrl);
        if ($newIpn && isset($newIpn['ipn_id'])) {
            error_log("Successfully registered new IPN URL. ID: " . $newIpn['ipn_id']);
            return $newIpn['ipn_id'];
        }

        error_log("Failed to get or register IPN URL ID.");
        return null;
    }

    public function submitOrder($token, $id, $amount, $currency, $description, $callbackUrl, $notificationId, $billingAddress) {
        $url = $this->apiUrl . '/api/Transactions/SubmitOrderRequest';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];
        $data = [
            'id' => $id,
            'currency' => $currency,
            'amount' => $amount,
            'description' => $description,
            'callback_url' => $callbackUrl,
            'notification_id' => $notificationId,
            'billing_address' => $billingAddress
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Bypass SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Submit Order request data: " . json_encode($data));
        error_log("Submit Order response status: " . $http_status);
        error_log("Submit Order response body: " . $response);

        if ($http_status === 200) {
            return json_decode($response, true);
        }
        return null;
    }

    public function getTransactionStatus($token, $orderTrackingId) {
        $url = $this->apiUrl . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . $orderTrackingId;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Bypass SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
?>
