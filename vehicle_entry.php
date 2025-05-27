<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';

$error = '';
$success = '';

require_once __DIR__ . '/SmsService.php';

$smsService = new SmsService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = strtoupper(preg_replace('/\s+/', '', $_POST['registration_number'] ?? ''));
    $vehicle_type = $_POST['vehicle_type'] ?? 'Motorcycle';
    $driver_name = strtoupper(trim($_POST['driver_name'] ?? ''));
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Backend validation
    if (!preg_match('/^[A-Z0-9]+$/', $registration_number)) {
        $error = 'Vehicle Registration Number must be alphanumeric with no spaces.';
    } elseif (in_array($vehicle_type, ['Bajaj', 'Motorcycle', 'Car', 'Truck', 'Other']) && strlen($registration_number) !== 8) {
        $error = 'Vehicle Registration Number must be exactly 8 characters for the selected vehicle type.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $driver_name) || $driver_name === '') {
        $error = 'Driver Name must contain alphabetic characters only.';
    } elseif (!preg_match('/^0\d{9}$/', $phone_number)) {
        $error = 'Phone Number must be exactly 10 digits and start with 0.';
    } else {
        try {
            // Check if vehicle exists
            $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE registration_number = ?');
            $stmt->execute([$registration_number]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($vehicle) {
                $vehicle_id = $vehicle['id'];
                // Check if vehicle is already parked (no exit_time)
                $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM parking_entries WHERE vehicle_id = ? AND exit_time IS NULL');
                $stmtCheck->execute([$vehicle_id]);
                $count = $stmtCheck->fetchColumn();
                if ($count > 0) {
                    $error = 'Vehicle is already parked and has not exited.';
                } else {
                    // Update vehicle info
                    $stmt = $pdo->prepare('UPDATE vehicles SET vehicle_type = ?, driver_name = ?, phone_number = ? WHERE id = ?');
                    $stmt->execute([$vehicle_type, $driver_name, $phone_number, $vehicle_id]);

                    // Insert parking entry
                    $stmt = $pdo->prepare('INSERT INTO parking_entries (vehicle_id) VALUES (?)');
                    $stmt->execute([$vehicle_id]);

                    // Send entry SMS
                    $entry_time = new DateTime();
                    $formatted_entry_time = $entry_time->format('Y-m-d H:i:s');

                    $message = "CHINO PARK RECEIPT\n" .
                               "Reg#: $registration_number\n" .
                               "Type: $vehicle_type\n" .
                               "In: $formatted_entry_time\n" .
                               "Call 0787753325 for help";

                    $sms_sent = $smsService->sendSms($phone_number, $message);

                    if (!$sms_sent) {
                        $error = 'Failed to send entry SMS notification. Please check SMS service or try again later.';
                        error_log("SMS sending failed for vehicle entry: $registration_number to phone $phone_number");
                    } else {
                        $success = 'Vehicle entry recorded successfully and SMS notification sent.';
                    }
                }
            } else {
                // Insert new vehicle
                $stmt = $pdo->prepare('INSERT INTO vehicles (registration_number, vehicle_type, driver_name, phone_number) VALUES (?, ?, ?, ?)');
                $stmt->execute([$registration_number, $vehicle_type, $driver_name, $phone_number]);
                $vehicle_id = $pdo->lastInsertId();

                // Insert parking entry
                $stmt = $pdo->prepare('INSERT INTO parking_entries (vehicle_id) VALUES (?)');
                $stmt->execute([$vehicle_id]);

                // Send entry SMS
                $message = "Vehicle $registration_number has entered the parking lot. Thank you for using Chino Parking System.";
                $sms_sent = $smsService->sendSms($phone_number, $message);

                if (!$sms_sent) {
                    $error = 'Failed to send entry SMS notification. Please check SMS service or try again later.';
                    error_log("SMS sending failed for vehicle entry: $registration_number to phone $phone_number");
                } else {
                    $success = 'Vehicle entry recorded successfully and SMS notification sent.';
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
<title>Chino Parking System - Vehicle Entry</title>
<link rel="manifest" href="manifest.json" />
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js')
      .then(function(registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      })
      .catch(function(error) {
        console.error('ServiceWorker registration failed:', error);
      });
    });
  }

  // PWA install prompt handling
  let deferredPrompt;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
      installBtn.style.display = 'block';
    }
  });

  function installPWA() {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          console.log('User accepted the install prompt');
          alert('App installed successfully!');
        } else {
          console.log('User dismissed the install prompt');
          alert('App installation dismissed.');
        }
        deferredPrompt = null;
        const installBtn = document.getElementById('installBtn');
        if (installBtn) {
          installBtn.style.display = 'none';
        }
      });
    }
  }

  // Listen for appinstalled event
  window.addEventListener('appinstalled', (evt) => {
    console.log('PWA was installed');
    alert('Thank you for installing the app!');
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
      installBtn.style.display = 'none';
    }
  });
</script>
<style>
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(to right, #2563eb, #4f46e5);
  color: white;
  margin: 0;
  padding: 0;
}
.container {
  max-width: 480px;
  margin: 3rem auto;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border-radius: 0.5rem;
  padding: 2rem;
  box-shadow: 0 0 15px rgba(0,0,0,0.3);
}
h2 {
  text-align: center;
  font-weight: 700;
  margin-bottom: 1.5rem;
}
form label {
  display: block;
  margin-top: 1rem;
  margin-bottom: 0.25rem;
  font-weight: 600;
}
input[type="text"],
select {
  width: 100%;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid #ccc;
  background: rgba(255, 255, 255, 0.9);
  color: #111;
  font-size: 1rem;
  outline: none;
  transition: box-shadow 0.3s ease;
}
input[type="text"]:focus,
select:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
  border-color: #3b82f6;
}
button {
  margin-top: 1.5rem;
  width: 100%;
  background-color: #3b82f6;
  color: white;
  font-weight: 600;
  padding: 0.75rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #2563eb;
}
.error {
  color: #f87171;
  margin-top: 1rem;
}
.success {
  color: #4ade80;
  margin-top: 1rem;
}
.install-btn {
  display: none;
  position: fixed;
  bottom: 1.25rem;
  right: 1.25rem;
  background-color: #2563eb;
  color: white;
  border-radius: 9999px;
  padding: 0.75rem 1.5rem;
  font-size: 1.125rem;
  box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.5);
  cursor: pointer;
  z-index: 50;
}
.install-btn:hover {
  background-color: #1e40af;
}
</style>
<script>
function validateForm() {
    const regNumInput = document.getElementById('registration_number');
    const vehicleTypeInput = document.getElementById('vehicle_type');
    const driverNameInput = document.getElementById('driver_name');
    const phoneInput = document.getElementById('phone_number');
    const errorDiv = document.getElementById('error');

    errorDiv.textContent = '';

    // Vehicle Registration Number: alphanumeric, no spaces
    const regNum = regNumInput.value.trim();
    const vehicleType = vehicleTypeInput.value;

    if (!/^[a-zA-Z0-9]+$/.test(regNum)) {
        errorDiv.textContent = 'Vehicle Registration Number must be alphanumeric with no spaces.';
        return false;
    }

    // Enforce 8 characters for Bajaj, Motorcycle, Car, Truck, Other
    if (['Bajaj', 'Motorcycle', 'Car', 'Truck', 'Other'].includes(vehicleType)) {
        if (regNum.length !== 8) {
            errorDiv.textContent = 'Vehicle Registration Number must be exactly 8 characters for the selected vehicle type.';
            return false;
        }
    }

    // Driver Name: alphabetic only (allow spaces for multiple names)
    const driverName = driverNameInput.value.trim();
    if (!/^[a-zA-Z\s]+$/.test(driverName) || driverName === '') {
        errorDiv.textContent = 'Driver Name must contain alphabetic characters only.';
        return false;
    }

    // Phone Number: exactly 10 digits, starts with 0
    const phone = phoneInput.value.trim();
    if (!/^0\d{9}$/.test(phone)) {
        errorDiv.textContent = 'Phone Number must be exactly 10 digits and start with 0.';
        return false;
    }

    return true;
}

function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, '');
}
</script>
<script>
function showNotification(message) {
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.backgroundColor = '#28a745';
        notification.style.color = 'white';
        notification.style.padding = '10px 20px';
        notification.style.borderRadius = '5px';
        notification.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
        notification.style.zIndex = '10000';
        document.body.appendChild(notification);
    }
    notification.textContent = message;
    notification.style.display = 'block';
    setTimeout(() => {
        notification.style.display = 'none';
    }, 5000);
}

// Replace alert calls with showNotification in PHP success/error messages
window.addEventListener('DOMContentLoaded', () => {
    const errorDiv = document.getElementById('error');
    const successDiv = document.getElementById('success');
    if (errorDiv && errorDiv.textContent.trim() !== '') {
        showNotification(errorDiv.textContent.trim());
        errorDiv.style.display = 'none';
    }
    if (successDiv && successDiv.textContent.trim() !== '') {
        showNotification(successDiv.textContent.trim());
        successDiv.style.display = 'none';
    }
});
</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Vehicle Entry Form</h2>
    <?php if ($error): ?>
        <div id="error" class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div id="success" class="success"><?= htmlspecialchars($success) ?></div>
    <?php else: ?>
        <div id="error" class="error"></div>
    <?php endif; ?>
    <form method="post" action="vehicle_entry.php" onsubmit="return validateForm();">
        <label for="registration_number">Vehicle Registration Number</label>
        <input type="text" id="registration_number" name="registration_number" oninput="removeSpaces(this); this.value = this.value.toUpperCase();" required autofocus />

        <label for="vehicle_type">Vehicle Type</label>
        <select id="vehicle_type" name="vehicle_type" required>
            <option value="Motorcycle" selected>Motorcycle</option>
            <option value="Bajaj">Bajaj</option>
            <option value="Car">Car</option>
            <option value="Truck">Truck</option>
            <option value="Other">Other</option>
        </select>

        <label for="driver_name">Driver Name</label>
        <input type="text" id="driver_name" name="driver_name" oninput="this.value = this.value.toUpperCase();" required />

        <label for="phone_number">Phone Number</label>
        <input type="text" id="phone_number" name="phone_number" maxlength="10" required />

        <button type="submit">Submit Entry</button>
    </form>
</div>
</body>
</html>
