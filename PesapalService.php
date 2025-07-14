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
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200) {
            $responseData = json_decode($response, true);
            return $responseData['token'] ?? null;
        }

        return null;
    }

    public function submitOrder($token, $amount, $currency, $description, $callbackUrl, $notificationId, $billingAddress) {
        $url = $this->apiUrl . '/api/Transactions/SubmitOrderRequest';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];
        $data = [
            'id' => uniqid(),
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
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
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
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
?>
