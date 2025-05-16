<?php
require_once __DIR__ . '/config.php';

$username = 'Francis';
$password = 'Francis@8891';

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $password_hash]);
    echo "User '$username' created successfully.";
} catch (PDOException $e) {
    echo "Error creating user: " . $e->getMessage();
}
?>
