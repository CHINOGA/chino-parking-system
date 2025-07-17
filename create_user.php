<?php
require_once __DIR__ . '/config.php';

$username = 'Chinopark';
$password = 'Chinopark@8891';

try {
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $password]);
    echo "User '$username' created successfully with plain text password.";
} catch (PDOException $e) {
    echo "Error creating user: " . $e->getMessage();
}
?>
