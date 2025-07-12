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
$otp_sent = false;
$otp_verified = false;
$otp_error = '';
$selected_driver_id = null;

// Function to generate OTP
function generateOtp() {
    return strval(rand(100000, 999999));
}

// Function to log deletion event
function logDeletion($userId, $driverId, $phoneNumber) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO deletion_logs (user_id, driver_id, phone_number, deleted_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $driverId, $phoneNumber]);
}

// Handle AJAX search request
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $results = [];
    if ($searchTerm !== '') {
        $stmt = $pdo->prepare("SELECT id, driver_name, phone_number FROM vehicles WHERE driver_name LIKE ? OR phone_number LIKE ? LIMIT 10");
        $likeTerm = '%' . $searchTerm . '%';
        $stmt->execute([$likeTerm, $likeTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Handle OTP verification and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_otp'])) {
        $selected_driver_id = $_POST['driver_id'] ?? null;
        if ($selected_driver_id) {
            // Fetch driver phone number
            $stmt = $pdo->prepare("SELECT phone_number FROM vehicles WHERE id = ?");
            $stmt->execute([$selected_driver_id]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($driver && !empty($driver['phone_number'])) {
                // Generate OTP and send to predefined number
                $otp = generateOtp();
                $_SESSION['delete_phone_otp'] = $otp;
                $_SESSION['delete_phone_otp_expiry'] = time() + 300; // 5 minutes expiry
                $_SESSION['delete_phone_driver_id'] = $selected_driver_id;

                $otp_phone = '+255785111722';
                $otp_message = "Your OTP for deleting driver phone number is: $otp. It is valid for 5 minutes.";
                $otp_sent = $smsService->sendSms($otp_phone, $otp_message);
                if (!$otp_sent) {
                    $error = 'Failed to send OTP. Please try again later.';
                    unset($_SESSION['delete_phone_otp']);
                    unset($_SESSION['delete_phone_otp_expiry']);
                    unset($_SESSION['delete_phone_driver_id']);
                }
            } else {
                $error = 'Selected driver does not have a phone number.';
            }
        } else {
            $error = 'No driver selected.';
        }
    } elseif (isset($_POST['verify_otp'])) {
        $input_otp = trim($_POST['otp'] ?? '');
        if (isset($_SESSION['delete_phone_otp']) && isset($_SESSION['delete_phone_otp_expiry']) && isset($_SESSION['delete_phone_driver_id'])) {
            if (time() > $_SESSION['delete_phone_otp_expiry']) {
                $otp_error = 'OTP has expired. Please request a new one.';
                unset($_SESSION['delete_phone_otp']);
                unset($_SESSION['delete_phone_otp_expiry']);
                unset($_SESSION['delete_phone_driver_id']);
            } elseif ($input_otp === $_SESSION['delete_phone_otp']) {
                $otp_verified = true;
                $driver_id = $_SESSION['delete_phone_driver_id'];

                // Fetch current phone number for logging
                $stmt = $pdo->prepare("SELECT phone_number FROM vehicles WHERE id = ?");
                $stmt->execute([$driver_id]);
                $driver = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($driver) {
                    // Delete phone number
                    $stmt = $pdo->prepare("UPDATE vehicles SET phone_number = NULL WHERE id = ?");
                    $stmt->execute([$driver_id]);

                    // Log deletion
                    logDeletion($_SESSION['user_id'], $driver_id, $driver['phone_number']);

                    $success = 'Phone number deleted successfully.';
                } else {
                    $error = 'Driver not found.';
                }

                unset($_SESSION['delete_phone_otp']);
                unset($_SESSION['delete_phone_otp_expiry']);
                unset($_SESSION['delete_phone_driver_id']);
            } else {
                $otp_error = 'Invalid OTP. Please try again.';
            }
        } else {
            $otp_error = 'No OTP found. Please request a new one.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Delete Driver Phone Number - Chino Parking System</title>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
<style>
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
input[type="number"],
textarea,
select {
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
input[type="number"]:focus,
textarea:focus,
select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}
button {
  margin-top: 2rem;
  width: 100%;
  background-color: #dc3545;
  color: white;
  font-weight: 600;
  padding: 1rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #b02a37;
}
.error {
  color: #dc3545;
  margin-top: 1rem;
}
.success {
  color: #198754;
  margin-top: 1rem;
}
#search_results {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  background: white;
  margin-top: 0.5rem;
}
#search_results div {
  padding: 0.5rem;
  cursor: pointer;
  background-color: #ffffff;
  color: #000000;
}
#search_results div:hover {
  background-color: #f0f0f0;
  color: #000000;
}
.selected-driver {
  background-color: #0d6efd;
  color: white;
}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Delete Driver Phone Number</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$otp_verified): ?>
        <label for="search_input">Search Driver by Name or Phone Number:</label>
        <input type="text" id="search_input" placeholder="Type to search..." autocomplete="off" />
        <div id="search_results"></div>

        <form method="post" id="delete_form" style="display:none;">
            <input type="hidden" name="driver_id" id="driver_id" />
            <button type="submit" name="send_otp">Send OTP to Confirm Deletion</button>
        </form>

        <form method="post" id="otp_form" style="display:none;">
            <label for="otp">Enter OTP:</label>
            <input type="text" name="otp" id="otp" maxlength="6" pattern="\d{6}" required />
            <?php if ($otp_error): ?>
                <div class="error"><?= htmlspecialchars($otp_error) ?></div>
            <?php endif; ?>
            <button type="submit" name="verify_otp">Verify OTP and Delete</button>
        </form>
    <?php else: ?>
        <p>Phone number deleted successfully.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_input');
    const searchResults = document.getElementById('search_results');
    const deleteForm = document.getElementById('delete_form');
    const otpForm = document.getElementById('otp_form');
    const driverIdInput = document.getElementById('driver_id');

    let selectedDriverId = null;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length === 0) {
            searchResults.innerHTML = '';
            deleteForm.style.display = 'none';
            otpForm.style.display = 'none';
            selectedDriverId = null;
            driverIdInput.value = '';
            return;
        }
        fetch(`driver-phone-delete.php?search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                searchResults.innerHTML = '';
                if (data.length === 0) {
                    searchResults.innerHTML = '<div>No matching drivers found.</div>';
                    deleteForm.style.display = 'none';
                    otpForm.style.display = 'none';
                    selectedDriverId = null;
                    driverIdInput.value = '';
                    return;
                }
                data.forEach(driver => {
                    const div = document.createElement('div');
                    div.textContent = `${driver.driver_name} - ${driver.phone_number}`;
                    div.dataset.driverId = driver.id;
                    div.classList.remove('selected-driver');
                    div.addEventListener('click', () => {
                        // Remove selection from other divs
                        Array.from(searchResults.children).forEach(child => child.classList.remove('selected-driver'));
                        div.classList.add('selected-driver');
                        selectedDriverId = driver.id;
                        driverIdInput.value = driver.id;
                        deleteForm.style.display = 'block';
                        otpForm.style.display = 'none';
                    });
                    searchResults.appendChild(div);
                });
            })
            .catch(() => {
                searchResults.innerHTML = '<div>Error fetching search results.</div>';
                deleteForm.style.display = 'none';
                otpForm.style.display = 'none';
                selectedDriverId = null;
                driverIdInput.value = '';
            });
    });

    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            if (!selectedDriverId) {
                e.preventDefault();
                alert('Please select a driver first.');
                return;
            }
            // Show OTP form after sending OTP
            setTimeout(() => {
                deleteForm.style.display = 'none';
                otpForm.style.display = 'block';
            }, 100);
        });
    }

    // Keep OTP form visible if OTP was sent but not yet verified
    <?php if ($otp_sent && !$otp_verified): ?>
    otpForm.style.display = 'block';
    deleteForm.style.display = 'none';
    <?php endif; ?>
});
</script>
</body>
</html>
