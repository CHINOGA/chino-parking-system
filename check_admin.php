<?php
require_once 'config.php';

try {
    // Check if roles table exists and has data
    $stmt = $pdo->query('SELECT * FROM roles');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Roles in database:' . PHP_EOL;
    foreach ($roles as $role) {
        echo 'ID: ' . $role['id'] . ', Name: ' . $role['role_name'] . PHP_EOL;
    }

    // Check if admin user exists
    $stmt = $pdo->prepare('SELECT u.username, u.role_id, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.username = ?');
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo PHP_EOL . 'Admin user found:' . PHP_EOL;
        echo 'Username: ' . $user['username'] . ', Role ID: ' . $user['role_id'] . ', Role Name: ' . $user['role_name'] . PHP_EOL;
    } else {
        echo PHP_EOL . 'Admin user not found.' . PHP_EOL;
    }

    // Check if adminuser exists
    $stmt->execute(['adminuser']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo PHP_EOL . 'Adminuser found:' . PHP_EOL;
        echo 'Username: ' . $user['username'] . ', Role ID: ' . $user['role_id'] . ', Role Name: ' . $user['role_name'] . PHP_EOL;
    } else {
        echo PHP_EOL . 'Adminuser not found.' . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
