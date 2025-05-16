<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';
require './backend/SmsService.php';

$error = '';
$success = '';

$smsService = new SmsService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = preg_replace('/\s+/', '', $_POST['registration_number'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9]+$/', $registration_number)) {
        $error = 'Invalid vehicle registration number.';
    } else {
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
                $error = 'No active parking entry found for this vehicle.';
            } else {
                // Calculate parking fee: 1000 TZS per day
                $entry_time = new DateTime($entry['entry_time']);
                $exit_time = new DateTime();
                $interval = $entry_time->diff($exit_time);
                $days = max(1, $interval->days); // Minimum 1 day
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
                $vehicle_type_stmt = $pdo->prepare('SELECT vehicle_type, driver_name FROM vehicles WHERE registration_number = ?');
                $vehicle_type_stmt->execute([$registration_number]);
                $vehicle_info = $vehicle_type_stmt->fetch(PDO::FETCH_ASSOC);

                $entry_time = new DateTime($entry['entry_time']);
                $formatted_entry_time = $entry_time->format('Y-m-d H:i:s');

                $message = "Vehicle Exit Notification\n" .
                           "Registration: $registration_number\n" .
                           "Vehicle Type: " . ($vehicle_info['vehicle_type'] ?? 'N/A') . "\n" .
                           "Driver Name: " . ($vehicle_info['driver_name'] ?? 'N/A') . "\n" .
                           "Driver Phone: $phone_number\n" .
                           "Entry Time: $formatted_entry_time\n" .
                           "Exit Time: " . (new DateTime())->format('Y-m-d H:i:s') . "\n" .
                           "Parking Fee: TZS $total_fee\n" .
                           "Thank you for parking at Chino Park.";

                $sms_sent = $smsService->sendSms($phone_number, $message);

                if (!$sms_sent) {
                    $error = 'Failed to send exit SMS notification. Please check SMS service or try again later.';
                    error_log("SMS sending failed for vehicle exit: $registration_number to phone $phone_number");
                } else {
                    $success = "Vehicle exit recorded successfully. Parking fee: TZS $total_fee. SMS notification sent.";
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Vehicle Exit - Chino Parking System</title>
<link rel="manifest" href="manifest.json" />
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js').then(function(registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      }, function(err) {
        console.log('ServiceWorker registration failed: ', err);
      });
    });
  }
</script>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; }
.container { max-width: 400px; margin: 50px auto; background: #fff; padding: 20px; border-radius: 5px; }
h2 { text-align: center; }
.error { color: red; margin-bottom: 10px; }
.success { color: green; margin-bottom: 10px; }
input[type="text"] {
    width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 3px;
}
button {
    width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 3px;
    cursor: pointer;
}
button:hover { background: #0056b3; }

/* Responsive styles */
@media (max-width: 600px) {
    .container {
        margin: 10px;
        padding: 15px;
        max-width: 100%;
    }
    input[type="text"], button {
        font-size: 1em;
    }
}
</style>
</head>
<body>
<div class="container">
    <h2>Vehicle Exit</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <form method="post" action="vehicle_exit.php" novalidate>
        <label for="registration_number">Vehicle Registration Number</label>
        <input type="text" id="registration_number" name="registration_number" required autofocus />
        <button type="submit">Record Exit</button>
    </form>
</div>
</body>
</html>
