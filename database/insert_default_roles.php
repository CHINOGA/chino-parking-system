<?php
require_once __DIR__ . '/../config.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name) VALUES ('admin'), ('cashier'), ('security')");
    $stmt->execute();
    echo "Default roles inserted successfully.";
} catch (PDOException $e) {
    echo "Error inserting default roles: " . $e->getMessage();
}
?>
