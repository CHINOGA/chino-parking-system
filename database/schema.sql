-- Chino Parking System Database Schema

-- Create MySQL user for application login
CREATE USER IF NOT EXISTS 'chinopark'@'localhost' IDENTIFIED BY 'Chinopark@8891';
GRANT ALL PRIVILEGES ON chinotra_chino_parking.* TO 'chinopark'@'localhost';
FLUSH PRIVILEGES;

-- Users table for login
CREATE TABLE IF NOT EXISTS chinotra_chino_parking.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vehicles table to store vehicle info
CREATE TABLE IF NOT EXISTS chinotra_chino_parking.vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('Motorcycle', 'Bajaj', 'Car', 'Truck', 'Other') NOT NULL DEFAULT 'Motorcycle',
    driver_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parking entries table
CREATE TABLE IF NOT EXISTS chinotra_chino_parking.parking_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exit_time TIMESTAMP NULL,
    notification_id VARCHAR(255) NULL,
    payment_status VARCHAR(50) DEFAULT 'PENDING',
    FOREIGN KEY (vehicle_id) REFERENCES chinotra_chino_parking.vehicles(id)
);

-- Revenue table to track payments (optional)
CREATE TABLE IF NOT EXISTS chinotra_chino_parking.revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_entry_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_entry_id) REFERENCES chinotra_chino_parking.parking_entries(id)
);
