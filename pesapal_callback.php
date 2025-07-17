<?php
// Force error reporting for this script to debug the connection issue
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';
require_once __DIR__ . '/SmsService.php';
require_once __DIR__ . '/PesapalService.php';

$smsService = new SmsService();
$pesapalService = new PesapalService();

$orderTrackingId = $_GET['OrderTrackingId'] ?? null;
$orderMerchantReference = $_GET['OrderMerchantReference'] ?? null;
$pesapal_transaction_tracking_id = $_GET['pesapal_transaction_tracking_id'] ?? null;

// Log the request for debugging
error_log('PesaPal Callback Request: ' . print_r($_GET, true));

if ($orderTrackingId || $orderMerchantReference) {
    // This is an IPN or redirect request from PesaPal v3
    $token = $pesapalService->getAuthToken();
    if ($token) {
        $transactionStatus = $orderTrackingId ? $pesapalService->getTransactionStatus($token, $orderTrackingId) : ['status_code' => 1]; // Assume success if only merchant ref is present

        if ($transactionStatus && $transactionStatus['status_code'] === 1) {
            // Payment is successful
            try {
                // Find the parking entry by order_tracking_id or merchant_reference
                $sql = 'SELECT * FROM parking_entries WHERE ';
                $params = [];
                if ($orderTrackingId) {
                    $sql .= 'order_tracking_id = ?';
                    $params[] = $orderTrackingId;
                } else {
                    // Extract the original entry ID from the unique merchant reference
                    $parts = explode('_', $orderMerchantReference);
                    $entryId = $parts[0];
                    $sql .= 'id = ?';
                    $params[] = $entryId;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $entry = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($entry && is_null($entry['exit_time'])) {
                    // If we didn't have the orderTrackingId before, update it now
                    if ($orderTrackingId && is_null($entry['order_tracking_id'])) {
                        $updateStmt = $pdo->prepare('UPDATE parking_entries SET order_tracking_id = ? WHERE id = ?');
                        $updateStmt->execute([$orderTrackingId, $entry['id']]);
                    }
                    // Calculate parking fee again for verification
                    $entry_time = new DateTime($entry['entry_time']);
                    $exit_time = new DateTime();
                    $interval = $entry_time->diff($exit_time);
                    $days = max(1, $interval->days);
                    $fee_per_day = 1000;
                    $total_fee = $days * $fee_per_day;

                    // Update exit_time and payment status
                    $stmt = $pdo->prepare('UPDATE parking_entries SET exit_time = NOW(), payment_status = ? WHERE id = ?');
                    $stmt->execute(['COMPLETED', $entry['id']]);

                    // Insert revenue record
                    $stmt = $pdo->prepare('INSERT INTO revenue (parking_entry_id, amount) VALUES (?, ?)');
                    $stmt->execute([$entry['id'], $total_fee]);

                    // Get vehicle details for SMS
                    $stmt = $pdo->prepare('
                        SELECT v.registration_number, v.phone_number, v.vehicle_type, v.driver_name
                        FROM vehicles v
                        JOIN parking_entries pe ON v.id = pe.vehicle_id
                        WHERE pe.id = ?
                    ');
                    $stmt->execute([$entry['id']]);
                    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Send exit SMS
                    $message = "Vehicle Exit Notification\n" .
                               "Registration: {$vehicle['registration_number']}\n" .
                               "Vehicle Type: {$vehicle['vehicle_type']}\n" .
                               "Driver Name: {$vehicle['driver_name']}\n" .
                               "Entry Time: " . (new DateTime($entry['entry_time']))->format('Y-m-d H:i:s') . "\n" .
                               "Exit Time: " . (new DateTime())->format('Y-m-d H:i:s') . "\n" .
                               "Parking Fee: TZS $total_fee\n" .
                               "Payment Status: COMPLETED\n" .
                               "Thank you for parking at Chino Park.";

                    $smsService->sendSms($vehicle['phone_number'], $message);
                }
            } catch (PDOException $e) {
                error_log('PesaPal Callback DB Error: ' . $e->getMessage());
            }
        }
    }
}

// Handle redirect vs. IPN
// If OrderTrackingId is in the URL, it's a user redirect.
if ($orderTrackingId) {
    // Redirect to a success page
    $redirectUrl = "payment_success.php?tracking_id=" . urlencode($orderTrackingId);
    if ($orderMerchantReference) {
        $redirectUrl .= "&merchant_ref=" . urlencode($orderMerchantReference);
    }
    header("Location: " . $redirectUrl);
    exit;
} else {
    // If it's an IPN, respond with a success message
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Callback processed successfully.']);
}
?>
