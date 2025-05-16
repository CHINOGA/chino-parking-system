<?php
require_once __DIR__ . '/config.php';

function send_sms($phone, $message) {
    $username = NEXTSMS_USERNAME;
    $password = NEXTSMS_PASSWORD;
    $sender_id = NEXTSMS_SENDER_ID;

    $url = "https://messaging-service.co.tz/api/sms/v1/text/single";

    $postData = [
        'from' => $sender_id,
        'to' => $phone,
        'text' => $message,
        'reference' => uniqid()
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $maxRetries = 3;
    $attempt = 0;
    $response = false;

    while ($attempt < $maxRetries) {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            curl_close($ch);
            return true;
        }
        $attempt++;
        sleep(1);
    }

    $error = curl_error($ch);
    error_log("NextSMS API error: $error");
    curl_close($ch);
    return false;
}

$message = "This is a test message from Chino Parking System.";
$phone = "255785111722"; // International format for 0785111722

if (send_sms($phone, $message)) {
    echo "Test SMS sent successfully to $phone";
} else {
    echo "Failed to send test SMS to $phone";
}
?>
