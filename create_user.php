<?php
require_once __DIR__ . '/config.php';

$username = 'chinopark';
$password = 'Chinopark@8891';
$role_name = 'admin';

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Get role_id from roles table
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE role_name = ?');
    $stmt->execute([$role_name]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception("Role '$role_name' not found.");
    }

    $role_id = $role['id'];

    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, ?)');
    $stmt->execute([$username, $password_hash, $role_id]);
    echo "User '$username' with role '$role_name' created successfully.";
} catch (Exception $e) {
    echo "Error creating user: " . $e->getMessage();
}
?>
