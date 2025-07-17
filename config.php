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
$dbname = 'chinotra_chino_parking'; // replace with your actual database name
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

// Add the PesaPal IPN URL ID constant (replace with your actual IPN ID)
define('PESAPAL_IPN_ID', 'your_actual_ipn_url_id_here');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Re-throw the exception to be caught by a global error handler (from error_handling.php).
    // This prevents an abrupt script termination which causes the generic HTTP 500 error.
    throw $e;
}
?>
