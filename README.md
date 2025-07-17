# Chino Parking System

![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)
![Database](https://img.shields.io/badge/database-MySQL-00758F.svg)
![License](https://img.shields.io/badge/license-Private-red.svg)

A comprehensive, PHP-based web application for efficient management of vehicle parking operations, featuring payment integration with PesaPal and SMS notifications with NextSMS.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Local Development Setup](#local-development-setup)
  - [1. Clone the Repository](#1-clone-the-repository)
  - [2. Database Setup](#2-database-setup)
  - [3. Configuration](#3-configuration)
  - [4. Run the Application](#4-run-the-application)
- [Project Structure](#project-structure)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

Chino Parking System is designed to streamline parking management. It allows administrators to log vehicle entries, process exits, calculate parking fees, and handle payments through the PesaPal v3 API. The system also keeps drivers informed by sending automated SMS notifications for entry and exit events via the NextSMS API. It is built with vanilla PHP and a MySQL database, making it lightweight and easy to deploy.

## Features

- **User Authentication:** Secure login for system administrators.
- **Vehicle Entry & Exit:** Track vehicle entry and exit times seamlessly.
- **Automated Fee Calculation:** Automatically calculates parking fees based on duration.
- **Payment Integration:** Secure payment processing powered by the **PesaPal v3 API**.
- **SMS Notifications:** Automated entry and exit notifications for drivers via the **NextSMS API**.
- **Real-time Reporting:** View lists of currently parked and recently exited vehicles.
- **Search & Filtering:** Easily search and filter reports by date, vehicle type, and more.
- **PWA Support:** Can be installed on a mobile device for a native-app-like experience.

## System Requirements

- **PHP:** Version 7.4 or higher
- **Web Server:** Apache (included with XAMPP) or Nginx
- **Database:** MySQL or MariaDB (included with XAMPP)
- **Git:** For version control

## Local Development Setup

These instructions will guide you through setting up the project on a local machine using XAMPP.

### 1. Clone the Repository

Clone the project into your XAMPP `htdocs` directory.

```bash
cd C:/xampp/htdocs/
git clone https://github.com/CHINOGA/chino-parking-system.git
cd chino-parking-system-1
```

### 2. Database Setup

You need to create the database and tables required for the application.

1. **Start XAMPP:** Open the XAMPP Control Panel and start the **Apache** and **MySQL** services.
2. **Create the Database:**
    - Navigate to `http://localhost/phpmyadmin/`.
    - Create a new database named `chino_parking_system`.
3. **Import the Base Schema:**
    - Select the `chino_parking_system` database.
    - Go to the **Import** tab.
    - Click **Choose File** and select `database/schema.sql` from the project directory.
    - Click **Go** to create the initial tables.
4. **Apply Schema Alterations:**
    - The base schema has been updated. You must apply the alteration script.
    - Go back to the **Import** tab.
    - Click **Choose File** and select `database/alter_schema.sql`.
    - Click **Go** to add the `order_tracking_id` column to the `parking_entries` table.

### 3. Configuration

All configuration is handled in the `config.php` file.

1. **Open `config.php`:** Open the `config.php` file in your code editor.
2. **Database Credentials:** The default settings are configured for a standard XAMPP installation (user `root` with no password). Adjust if your setup is different.
3. **API Credentials:** The file is pre-populated with API keys for **NextSMS** and **PesaPal**.
4. **Environment Variable:** The `APP_ENV` is set to `local` by default to enable development-specific features, such as bypassing SSL verification for API calls. **Do not change this for local development.**

```php
// config.php

// Set the application environment to 'local' for development-specific settings
putenv('APP_ENV=local');

// ... Database, NextSMS, and PesaPal credentials
```

### 4. Run the Application

You can run the application using the built-in PHP development server.

1. **Open a terminal** in the project's root directory (`C:/xampp/htdocs/chino-parking-system-1`).
2. **Run the server** with the following command:

    ```bash
    php -S localhost:8000
    ```

3. **Access the application** by navigating to `http://localhost:8000` in your web browser.

## Project Structure

Here is an overview of the key files in the project:

```text
.
├── database/
│   ├── schema.sql          # Main database schema
│   └── alter_schema.sql    # Schema modifications
├── config.php              # All application and API configuration
├── login.php               # User login page
├── vehicle-entry.php       # Form to register a new vehicle entry
├── vehicle_exit.php        # Handles payment processing and vehicle exit
├── parked-vehicles.php     # Dashboard view of currently parked vehicles
├── exited-vehicles.php     # Report of vehicles that have exited
├── pesapal_callback.php    # Handles incoming IPN and redirect from PesaPal
├── payment_success.php     # Page shown to the user after a successful payment
├── PesapalService.php      # Service class for interacting with the PesaPal API
├── SmsService.php          # Service class for sending SMS via the NextSMS API
└── README.md               # This documentation file
```

## Usage

- **Login:** Access the system using your administrator credentials.
- **Vehicle Entry:** Navigate to the "Vehicle Entry" page to register a new vehicle. An SMS is sent to the driver upon successful entry.
- **Vehicle Exit:** From the "Parked Vehicles" dashboard, find the vehicle and initiate the exit process. This will redirect to PesaPal for payment.
- **Payment:** After a successful payment on PesaPal, the user is redirected to a success page, and an exit SMS is sent to the driver.
- **Reporting:** Use the "Exited Vehicles" and "Revenue Report" pages to view historical data.

## Contributing

Contributions are welcome. Please follow these steps:

1. **Fork** the repository.
2. Create a new branch (`git checkout -b feature/YourFeature`).
3. Commit your changes (`git commit -m 'Add some feature'`).
4. Push to the branch (`git push origin feature/YourFeature`).
5. Open a **Pull Request**.

## License

This project is private and intended for authorized users only. All rights reserved.
