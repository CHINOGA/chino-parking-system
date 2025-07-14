<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';
require_once __DIR__ . '/SmsService.php';
require_once __DIR__ . '/PesapalService.php';

$smsService = new SmsService();
$pesapalService = new PesapalService();

$orderNotificationType = $_GET['OrderNotificationType'] ?? null;
$orderTrackingId = $_GET['OrderTrackingId'] ?? null;
$orderMerchantReference = $_GET['OrderMerchantReference'] ?? null;

if ($orderNotificationType === 'IPN' && $orderTrackingId && $orderMerchantReference) {
    $token = $pesapalService->getAuthToken();
    if ($token) {
        $transactionStatus = $pesapalService->getTransactionStatus($token, $orderTrackingId);

        if ($transactionStatus && $transactionStatus['status_code'] === 1) {
            // Payment is successful
            try {
                // Find the parking entry by notification_id
                $stmt = $pdo->prepare('SELECT * FROM parking_entries WHERE notification_id = ?');
                $stmt->execute([$orderMerchantReference]);
                $entry = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($entry && is_null($entry['exit_time'])) {
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

                    // Respond to PesaPal IPN
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Callback processed successfully.']);
                }
            } catch (PDOException $e) {
                error_log('PesaPal Callback DB Error: ' . $e->getMessage());
            }
        }
    }
} else {
    // Handle cases where it's not an IPN or parameters are missing
    header("Location: index.php");
    exit;
}
?>
