<?php
// Set the application environment to 'local' for development-specific settings
putenv('APP_ENV=local');

date_default_timezone_set('Africa/Nairobi');

// Enable error reporting and logging for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Database connection configuration for XAMPP
$host = 'localhost';
$dbname = 'chinotra_chino_parking'; // Make sure this DB exists in phpMyAdmin
$user = 'chinotra_francis';
$password = 'Francis@8891';

// NextSMS API credentials and settings
define('NEXTSMS_USERNAME', 'abelchinoga');
define('NEXTSMS_PASSWORD', 'Abelyohana@8');
define('NEXTSMS_SENDER_ID', 'CHINOTRACK');

// PesaPal API credentials
define('PESAPAL_CONSUMER_KEY', 'H8BRbCyD0ima8h0FVs0vVU1nV9ymorPi');
define('PESAPAL_CONSUMER_SECRET', 't20fUM2sdIl1cn1W4Yi2jvAg/Pk=');
define('PESAPAL_API_URL', 'https://pay.pesapal.com/v3'); // Live URL

// Base URL of the deployed app (update this to your actual domain)
define('APP_BASE_URL', 'https://park.chinotrack.com');


// Add the PesaPal IPN URL ID constant (replace with your actual IPN ID)
define('PESAPAL_IPN_ID', 'your_actual_ipn_url_id_here');

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4", $user, $password);

    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Re-throw the exception to be caught by a global error handler (from error_handling.php).
    throw $e;
}
?>
