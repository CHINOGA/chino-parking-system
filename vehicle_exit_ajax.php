<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';
require './backend/SmsService.php';

$data = json_decode(file_get_contents('php://input'), true);
$registration_number = $data['registration_number'] ?? '';

if (!preg_match('/^[a-zA-Z0-9]+$/', $registration_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle registration number.']);
    exit;
}

$smsService = new SmsService();

try {
    // Find vehicle and active parking entry (exit_time IS NULL)
    $stmt = $pdo->prepare('
        SELECT pe.id AS entry_id, pe.entry_time, v.phone_number, v.vehicle_type, v.driver_name
        FROM parking_entries pe
        JOIN vehicles v ON pe.vehicle_id = v.id
        WHERE v.registration_number = ? AND pe.exit_time IS NULL
        ORDER BY pe.entry_time DESC
        LIMIT 1
    ');
    $stmt->execute([$registration_number]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'No active parking entry found for this vehicle.']);
        exit;
    }

    // Calculate parking fee: 1000 TZS per day
    $entry_time = new DateTime($entry['entry_time']);
    $exit_time = new DateTime();
    $interval = $entry_time->diff($exit_time);
    $days = max(1, (int)$interval->days); // Minimum 1 day
    $fee_per_day = 1000;
    $total_fee = $days * $fee_per_day;

    // Update exit_time to current timestamp
    $stmt = $pdo->prepare('UPDATE parking_entries SET exit_time = NOW() WHERE id = ?');
    $stmt->execute([$entry['entry_id']]);

    // Insert revenue record
    $stmt = $pdo->prepare('INSERT INTO revenue (parking_entry_id, amount) VALUES (?, ?)');
    $stmt->execute([$entry['entry_id'], $total_fee]);

    // Format phone number to international format 2557XXXXXXX
    $phone_number = $entry['phone_number'];

    // Prepare detailed exit SMS message
    $message = "CHINO PARK RECEIPT\n" .
               "Reg#: $registration_number\n" .
               "Type: " . ($entry['vehicle_type'] ?? 'N/A') . "\n" .
               "In: " . $entry_time->format('Y-m-d H:i:s') . "\n" .
               "Out: " . $exit_time->format('Y-m-d H:i:s') . "\n" .
               "Fee: TZS $total_fee\n" .
               "Call 0787753325 for help.";

    $sms_sent = $smsService->sendSms($phone_number, $message);

    if (!$sms_sent) {
        echo json_encode(['success' => false, 'message' => 'Failed to send exit SMS notification.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Vehicle exit recorded successfully. Parking fee: TZS $total_fee. SMS notification sent."]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
