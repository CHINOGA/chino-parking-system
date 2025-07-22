-- Consolidated Chino Parking System Database Schema

CREATE DATABASE IF NOT EXISTS chinotra_chino_parking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chinotra_chino_parking;

-- Create MySQL user for application login
-- Note: User creation and privileges should be done via Bluehost cPanel UI, not via SQL on shared hosting.
-- The following statements are commented out to avoid permission errors.

-- CREATE USER IF NOT EXISTS 'chinotra_francis'@'localhost' IDENTIFIED BY 'Francis@8891';
-- GRANT ALL PRIVILEGES ON chinotra_chino_parking.* TO 'chinotra_francis'@'localhost';
-- FLUSH PRIVILEGES;

-- Users table for login
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vehicles table to store vehicle info
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('Motorcycle', 'Bajaj', 'Car', 'Truck', 'Other') NOT NULL DEFAULT 'Motorcycle',
    driver_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parking entries table
CREATE TABLE IF NOT EXISTS parking_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exit_time TIMESTAMP NULL,
    notification_id VARCHAR(255) NULL,
    payment_status VARCHAR(50) DEFAULT 'PENDING',
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- Revenue table to track payments (optional)
CREATE TABLE IF NOT EXISTS revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_entry_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_entry_id) REFERENCES parking_entries(id)
);

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    otp VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- Alter parking_entries table to add order_tracking_id column
ALTER TABLE parking_entries
ADD COLUMN IF NOT EXISTS order_tracking_id VARCHAR(255) NULL AFTER notification_id;

-- Alter users table to add role_id column and foreign key constraint
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role_id INT DEFAULT NULL;

-- MariaDB does not support IF NOT EXISTS for ADD CONSTRAINT, so we need to check and add constraint manually or drop and add
-- For simplicity, we will drop the constraint if exists and then add it

-- MariaDB does not support DROP FOREIGN KEY IF EXISTS, so we use a conditional drop via stored procedure or ignore errors
-- To avoid errors, we will just try to add the constraint and ignore if it exists

-- Remove the DROP FOREIGN KEY IF EXISTS line to avoid error
-- ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_role;

-- Add the foreign key constraint, ignoring error if it already exists
ALTER TABLE users
ADD CONSTRAINT fk_role FOREIGN KEY (role_id) REFERENCES roles(id);

-- Alter users table to add email and phone_number columns if not exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER username;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(15) NULL AFTER email;

-- Insert default roles
INSERT IGNORE INTO roles (role_name) VALUES ('admin'), ('cashier'), ('security');
