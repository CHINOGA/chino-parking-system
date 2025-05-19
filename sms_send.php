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
                    if (preg_match('/^0\d{9}$/', $clean_num)) {
                        $recipients[] = $clean_num;
                    }
                }
            }

            // Clean, validate and add new number if provided
            if ($new_number !== '') {
                $clean_new_number = cleanPhoneNumber($new_number);
                if (preg_match('/^0\d{9}$/', $clean_new_number)) {
                    $recipients[] = $clean_new_number;
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
<style>
body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; }
.container { max-width: 600px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 5px; }
h2 { text-align: center; }
label { display: block; margin-top: 15px; }
select, input[type="text"], textarea {
    width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 3px;
    box-sizing: border-box;
}
button {
    margin-top: 20px; padding: 10px; width: 100%; background: #007bff; color: white; border: none; border-radius: 3px;
    cursor: pointer;
}
button:hover { background: #0056b3; }
.error { color: red; margin-top: 10px; }
.success { color: green; margin-top: 10px; }
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
        <div id="selected_count_display" style="margin-top: 5px; font-weight: bold;">Selected: 0</div>
        <style>
        /* Hide desktop multi-select and desktop select all checkbox */
        #selected_numbers, #select_all_checkbox {
            display: none;
        }
        /* Show mobile checkbox container always */
        #mobile_checkbox_container {
            display: block;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 3px;
            max-height: 300px;
            overflow-y: auto;
            background: #fff;
        }
        /* Hide mobile select dropdown */
        #selected_numbers_mobile {
            display: none;
        }
        /* Ensure selected count display is visible */
        #selected_count_display {
            font-weight: bold;
            margin-top: 5px;
            color: #333;
        }
        </style>
        <div id="mobile_checkbox_container">

            <?php foreach ($drivers as $driver): ?>
                <label>
                    <input type="checkbox" class="mobile_driver_checkbox" name="selected_numbers[]" value="<?= htmlspecialchars($driver['phone_number']) ?>" />
                    <?= htmlspecialchars($driver['driver_name']) ?> - <?= htmlspecialchars($driver['phone_number']) ?>
                </label><br/>
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
