-- Add the order_tracking_id column to the parking_entries table
ALTER TABLE parking_entries
ADD COLUMN order_tracking_id VARCHAR(255) NULL AFTER notification_id;

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- Add role_id column to users table
ALTER TABLE chinotra_chino_parking.users
ADD COLUMN role_id INT DEFAULT NULL;

ALTER TABLE chinotra_chino_parking.users
ADD CONSTRAINT fk_role FOREIGN KEY (role_id) REFERENCES roles(id);

-- Insert default roles
INSERT IGNORE INTO roles (role_name) VALUES ('admin'), ('cashier'), ('security');
