<?php
require_once 'config.php';

$error = '';
$success = '';
$showForm = true;

$token = $_GET['token'] ?? '';

if ($token === '') {
    $error = 'Invalid or missing token.';
    $showForm = false;
} else {
    // Fetch password reset record
    $stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.otp, pr.expires_at, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ?');
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        $error = 'Invalid or expired token.';
        $showForm = false;
    } elseif (new DateTime() > new DateTime($resetRecord['expires_at'])) {
        $error = 'Token has expired.';
        $showForm = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($otp === '' || $new_password === '' || $confirm_password === '') {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } elseif ($otp !== $resetRecord['otp']) {
        $error = 'Invalid OTP.';
    } else {
        // Update user password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$password_hash, $resetRecord['user_id']]);

        // Delete password reset record
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE id = ?');
        $stmt->execute([$resetRecord['id']]);

        $success = 'Password has been reset successfully. You can now <a href="login.php">login</a>.';
        $showForm = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Password Reset - Chino Parking System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="custom.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-4">
    <h2>Password Reset for <?= htmlspecialchars($resetRecord['username'] ?? '') ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <form method="post" class="mb-4">
        <div class="mb-3">
            <label for="otp" class="form-label">OTP (sent via SMS):</label>
            <input type="text" name="otp" id="otp" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password:</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required />
        </div>
        <button type="submit" class="btn btn-primary">Reset Password</button>
    </form>
    <?php endif; ?>

    <p><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>
