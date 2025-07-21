<?php 
// Show all errors for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Mail settings (if using mail())
ini_set('SMTP', 'smtp.gmail.com');
ini_set('smtp_port', 587);
ini_set('sendmail_from', 'abelchinoga@gmail.com');
ini_set('sendmail_path', '"C:\\xampp\\sendmail\\sendmail.exe" -t -i');

// Include dependencies
require_once 'config.php';
require_once 'SmsService.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$resetLink = '';

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $error = 'Please enter your username or email.';
    } else {
        try {
            // Lookup user
            $stmt = $pdo->prepare('SELECT id, phone_number, email FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'User not found.';
            } else {
                $token = generateToken();
                $otp = generateOTP();
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Save reset request
                $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, otp, expires_at) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['id'], $token, $otp, $expires_at]);

                // Generate reset link
                $resetLink = "http://localhost:8000/password-reset.php?token=$token";

                // Send email
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'abelchinoga@gmail.com';
                    $mail->Password = 'fvkj qezn uqxk tmnr'; // App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('no-reply@chinoparkingsystem.com', 'Chino Parking System');
                    $mail->addAddress($user['email']);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body = "You requested a password reset. Click the link below:\n\n$resetLink\n\nYour OTP is: $otp\n\nLink and OTP expire in 15 minutes.";
                    $mail->AltBody = $mail->Body;

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                    $error = 'Email could not be sent.';
                }

                // Send SMS
                try {
                    $smsService = new SmsService();
                    $smsMessage = "Your Chino Parking System OTP is: $otp. It expires in 15 minutes.";
                    $smsService->sendSms($user['phone_number'], $smsMessage);
                } catch (Exception $ex) {
                    error_log("SMS Error: " . $ex->getMessage());
                }

                if (!$error) {
                    $success = 'Reset instructions have been sent to your email and phone.';
                }
            }
        } catch (Exception $ex) {
            error_log("Fatal Error: " . $ex->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Password Reset Request - Chino Parking System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="custom.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-4">
    <h2>Password Reset Request</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?><br />
            <?php if ($resetLink): ?>
                <strong>Reset Link:</strong> <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="mb-3">
            <label for="identifier" class="form-label">Username or Email:</label>
            <input type="text" name="identifier" id="identifier" class="form-control" required />
        </div>
        <button type="submit" class="btn btn-primary">Send Reset Instructions</button>
    </form>
    <p><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>
