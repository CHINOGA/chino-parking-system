<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handling.php';
require __DIR__ . '/SmsService.php';

$smsService = new SmsService();

$error = '';
$success = '';

// Fetch distinct phone numbers and driver names from vehicles table
try {
    $stmt = $pdo->query("SELECT DISTINCT phone_number, driver_name FROM vehicles WHERE phone_number IS NOT NULL AND phone_number != '' ORDER BY driver_name ASC");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$otp_phone = '+255785111722';
$otp_sent = false;
$otp_verified = false;
$otp_error = '';

function cleanPhoneNumber($number) {
    // Remove all non-digit characters
    $number = preg_replace('/\D/', '', $number);
    // Ensure it starts with 0
    if (substr($number, 0, 1) !== '0') {
        $number = '0' . $number;
    }
    return $number;
}

// Normalize phone number to standard 0XXXXXXXXX format for duplicate removal
function normalizePhoneNumber($number) {
    $number = preg_replace('/\D/', '', $number);
    if (strpos($number, '255') === 0) {
        // Convert from international format to local format
        $number = '0' . substr($number, 3);
    }
    if (substr($number, 0, 1) !== '0') {
        $number = '0' . $number;
    }
    return $number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        // Verify OTP
        $input_otp = trim($_POST['otp'] ?? '');
        if (isset($_SESSION['sms_otp']) && isset($_SESSION['sms_otp_expiry'])) {
            if (time() > $_SESSION['sms_otp_expiry']) {
                $otp_error = 'OTP has expired. Please request a new one.';
                unset($_SESSION['sms_otp']);
                unset($_SESSION['sms_otp_expiry']);
            } elseif ($input_otp === $_SESSION['sms_otp']) {
                $otp_verified = true;
                unset($_SESSION['sms_otp']);
                unset($_SESSION['sms_otp_expiry']);
            } else {
                $otp_error = 'Invalid OTP. Please try again.';
            }
        } else {
            $otp_error = 'No OTP found. Please request a new one.';
        }
    } elseif (isset($_POST['send_sms'])) {
        // Initial SMS send request - generate and send OTP
        $selected_numbers = $_POST['selected_numbers'] ?? [];
        $new_number = trim($_POST['new_number'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate message
        if ($message === '') {
            $error = 'Message content cannot be empty.';
        } else {
            // Prepare list of recipients
            $recipients = [];

            // Clean, validate and add selected numbers
            if (is_array($selected_numbers)) {
                foreach ($selected_numbers as $num) {
                    $clean_num = cleanPhoneNumber(trim($num));
                    $normalized_num = normalizePhoneNumber($clean_num);
                    if (preg_match('/^0\d{9}$/', $normalized_num)) {
                        $recipients[] = $normalized_num;
                    }
                }
            }

            // Clean, validate and add new number if provided
            if ($new_number !== '') {
                $clean_new_number = cleanPhoneNumber($new_number);
                $normalized_new_number = normalizePhoneNumber($clean_new_number);
                if (preg_match('/^0\d{9}$/', $normalized_new_number)) {
                    $recipients[] = $normalized_new_number;
                } else {
                    $error = 'New phone number must be exactly 10 digits and start with 0.';
                }
            }

            if (empty($recipients) && $error === '') {
                $error = 'Please select at least one recipient or enter a valid new phone number.';
            }

            if ($error === '') {
                // Remove duplicate phone numbers
                $recipients = array_unique($recipients);

                // Store recipients and message in session for later sending after OTP verification
                $_SESSION['sms_recipients'] = $recipients;
                $_SESSION['sms_message'] = $message;

                // Generate OTP
                $otp = strval(rand(100000, 999999));
                $_SESSION['sms_otp'] = $otp;
                $_SESSION['sms_otp_expiry'] = time() + 300; // OTP valid for 5 minutes

                // Send OTP to user's phone
                $otp_message = "Your OTP for sending SMS is: $otp. It is valid for 5 minutes.";
                $otp_sent = $smsService->sendSms($otp_phone, $otp_message);

                if (!$otp_sent) {
                    $error = 'Failed to send OTP to your phone. Please try again later.';
                    unset($_SESSION['sms_otp']);
                    unset($_SESSION['sms_otp_expiry']);
                    unset($_SESSION['sms_recipients']);
                    unset($_SESSION['sms_message']);
                }
            }
        }
    }
}

if ($otp_verified) {
    // Send SMS to all recipients
    $failed_numbers = [];
    $recipients = $_SESSION['sms_recipients'] ?? [];
    $message = $_SESSION['sms_message'] ?? '';

    foreach ($recipients as $phone) {
        $sent = $smsService->sendSms($phone, $message);
        if (!$sent) {
            $failed_numbers[] = $phone;
        }
    }
    if (count($failed_numbers) > 0) {
        $error = 'Failed to send SMS to: ' . implode(', ', $failed_numbers);
    } else {
        $success = 'SMS sent successfully to all recipients.';
    }
    unset($_SESSION['sms_recipients']);
    unset($_SESSION['sms_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Send SMS to Drivers - Chino Parking System</title>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
<style>
/* Revised styles for SMS send page */
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
  color: #212529;
  margin: 0;
  padding: 0;
}
.container {
  max-width: 600px;
  margin: 3rem auto;
  background: white;
  border-radius: 0.5rem;
  padding: 2rem;
  box-shadow: 0 0 15px rgba(0,0,0,0.1);
}
h2 {
  text-align: center;
  font-weight: 700;
  margin-bottom: 2rem;
  color: #212529;
}
label {
  display: block;
  margin-top: 1.5rem;
  font-weight: 600;
  color: #495057;
}
input[type="text"],
textarea {
  width: 100%;
  padding: 0.75rem;
  margin-top: 0.5rem;
  border-radius: 0.375rem;
  border: 1px solid #ced4da;
  box-sizing: border-box;
  outline: none;
  color: #212529;
  font-size: 1rem;
}
input[type="text"]:focus,
textarea:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}
button {
  margin-top: 2rem;
  width: 100%;
  background-color: #0d6efd;
  color: white;
  font-weight: 600;
  padding: 1rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #0b5ed7;
}
.error {
  color: #dc3545;
  margin-top: 1rem;
}
.success {
  color: #198754;
  margin-top: 1rem;
}
#mobile_checkbox_container {
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  padding: 1rem;
  max-height: 18rem;
  overflow-y: auto;
  background: white;
  color: #212529;
}
#selected_count_display {
  font-weight: 700;
  margin-top: 1rem;
  color: #212529;
}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Send SMS to Drivers</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$otp_verified && $otp_sent): ?>
        <form method="post" action="sms_send.php">
            <label for="otp">Enter the 6-digit OTP sent to your phone:</label>
            <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" required />
            <?php if ($otp_error): ?>
                <div class="error"><?= htmlspecialchars($otp_error) ?></div>
            <?php endif; ?>
            <button type="submit" name="verify_otp">Verify OTP</button>
        </form>
    <?php else: ?>
        <form method="post" action="sms_send.php">
        <label for="driver_search">Search Drivers:</label>
        <input type="text" id="driver_search" placeholder="Type to search driver names..." onkeyup="filterDrivers()" autocomplete="off" />
        <label for="selected_numbers">Select Driver Phone Numbers (multiple selection allowed):</label>
        <div>
            <label><input type="checkbox" id="select_all_checkbox_mobile" /> Select All</label>
        </div>
        <div id="selected_count_display">Selected: 0</div>
        <div id="mobile_checkbox_container">

            <?php foreach ($drivers as $driver): ?>
                <label class="block">
                    <input type="checkbox" class="mobile_driver_checkbox" name="selected_numbers[]" value="<?= htmlspecialchars($driver['phone_number']) ?>" />
                    <?= htmlspecialchars($driver['driver_name']) ?> - <?= htmlspecialchars($driver['phone_number']) ?>
                </label>
            <?php endforeach; ?>
        </div>

            <label for="new_number">Or enter a new phone number (10 digits, starts with 0):</label>
            <input type="text" id="new_number" name="new_number" maxlength="10" pattern="0\d{9}" placeholder="e.g. 0712345678" />

            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="5" required></textarea>

            <button type="submit" name="send_sms">Send SMS</button>
        </form>
    <?php endif; ?>
</div>
<script>
function filterDrivers() {
    const input = document.getElementById('driver_search');
    const filter = input.value.toUpperCase();
    const checkboxes = document.querySelectorAll('.mobile_driver_checkbox');
    checkboxes.forEach(cb => {
        const label = cb.parentElement.textContent.toUpperCase();
        cb.parentElement.style.display = label.indexOf(filter) > -1 ? '' : 'none';
    });
}
window.addEventListener('DOMContentLoaded', function() {
    const selectAllMobile = document.getElementById('select_all_checkbox_mobile');
    if (selectAllMobile) {
        selectAllMobile.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.mobile_driver_checkbox');
            const checked = this.checked;
            checkboxes.forEach(cb => cb.checked = checked);
            updateSelectedCount();
        });
    }

    const mobileCheckboxes = document.querySelectorAll('.mobile_driver_checkbox');
    mobileCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateSelectedCount();
        });
    });

    // Initial count update
    updateSelectedCount();
});

function updateSelectedCount() {
    let count = 0;
    const mobileCheckboxes = document.querySelectorAll('.mobile_driver_checkbox');
    if (mobileCheckboxes.length > 0) {
        mobileCheckboxes.forEach(cb => {
            if (cb.checked) {
                count++;
            }
        });
    }
    const display = document.getElementById('selected_count_display');
    if (display) {
        display.textContent = 'Selected: ' + count;
    }
}
</script>
</body>
</html>
