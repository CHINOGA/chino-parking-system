<?php
require_once __DIR__ . '/../config.php';

try {
    // Create roles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL UNIQUE
    )");

    // Add role_id column to users table if not exists
    $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL");

    // Drop existing foreign key constraint if exists
    $pdo->exec("ALTER TABLE users DROP FOREIGN KEY fk_role");

    // Add foreign key constraint
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_role FOREIGN KEY (role_id) REFERENCES roles(id)");

    // Insert default roles
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name) VALUES ('admin'), ('cashier'), ('security')");
    $stmt->execute();

    echo "Roles table created and default roles inserted successfully.";
} catch (PDOException $e) {
    echo "Error setting up roles table: " . $e->getMessage();
}
?>
