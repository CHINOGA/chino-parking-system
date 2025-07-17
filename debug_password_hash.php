<?php
require_once __DIR__ . '/config.php';

$username = 'Francis';

try {
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Password hash for user '$username': " . htmlspecialchars($user['password_hash']);
    } else {
        echo "User '$username' not found.";
    }
} catch (PDOException $e) {
    echo "Error fetching password hash: " . $e->getMessage();
}
?>
