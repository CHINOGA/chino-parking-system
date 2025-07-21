<?php
require_once __DIR__ . '/config.php';

$users = [
    ['username' => 'adminuser', 'password' => 'AdminPass123', 'role' => 'admin'],
    ['username' => 'cashieruser', 'password' => 'CashierPass123', 'role' => 'cashier'],
    ['username' => 'securityuser', 'password' => 'SecurityPass123', 'role' => 'security'],
];

foreach ($users as $user) {
    $username = $user['username'];
    $password = $user['password'];
    $role_name = $user['role'];

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE role_name = ?');
        $stmt->execute([$role_name]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            throw new Exception("Role '$role_name' not found.");
        }

        $role_id = $role['id'];

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, ?)');
        $stmt->execute([$username, $password_hash, $role_id]);
        echo "User '$username' with role '$role_name' created successfully.\n";
    } catch (Exception $e) {
        echo "Error creating user '$username': " . $e->getMessage() . "\n";
    }
}
?>
