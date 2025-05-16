# Chino Parking System PHP

## Overview
Chino Parking System is a PHP-based web application designed to manage vehicle parking operations efficiently. It supports vehicle entry and exit tracking, real-time reporting, and notification via SMS. The system also includes Progressive Web App (PWA) support for enhanced user experience.

## Features
- Vehicle entry and exit management
- Real-time reporting dashboard with search functionality
- SMS notifications on vehicle entry
- Validation rules for vehicle registration and driver details
- Progressive Web App (PWA) support for offline usage and installation
- Git version control integration

## Requirements
- PHP 7.4 or higher
- MySQL or compatible database
- Web server (e.g., Apache, Nginx)
- Composer (for dependency management if needed)
- SMS service configured (see SmsService.php)

## Setup Instructions

1. Clone the repository or download the project files.

2. Configure the database:
   - Import the database schema from `database/schema.sql`.
   - Update database connection settings in `config.php`.

3. Configure SMS service:
   - Review and update `backend/SmsService.php` with your SMS provider credentials.

4. Set up the web server to serve the project directory.

5. Access the application via your browser.

## Usage

- **Vehicle Entry:** Use `vehicle_entry.php` to register vehicles entering the parking lot. Vehicle registration numbers and driver names are automatically converted to uppercase. Validation enforces 8-character registration numbers for all vehicle types.

- **Reporting:** Use `reporting.php` to view parked and exited vehicles. Real-time search inputs allow filtering of vehicles by registration number, type, driver name, and phone number.

- **Vehicle Exit:** Use the exit functionality on the reporting dashboard to mark vehicles as exited.

- **PWA Support:** The application supports installation as a Progressive Web App. Users will be prompted to install the app for offline access.

## Git Version Control

- The project is under Git version control.
- The remote repository is hosted at: https://github.com/CHINOGA/Chino-parking-system-php.git
- Use standard Git commands to pull, commit, and push changes.

## Notes

- Ensure the SMS service is properly configured to send notifications.
- The system uses server-side and client-side validation for data integrity.
- The reporting dashboard supports infinite scroll for exited vehicles.

## License

This project is private and intended for authorized users only.

## Contact

For support or inquiries, contact the project maintainer.
