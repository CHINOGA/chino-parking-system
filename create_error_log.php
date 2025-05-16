<?php
// Script to create error.log file and verify logging

// Set error reporting and log file path
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Trigger a test warning to verify error logging
trigger_error("This is a test warning for error logging.", E_USER_WARNING);

// Check if error.log file exists and is writable
$logFile = __DIR__ . '/error.log';
if (file_exists($logFile)) {
    echo "Error log file exists at: $logFile";
} else {
    // Try to create the file
    $handle = fopen($logFile, 'a');
    if ($handle) {
        fclose($handle);
        echo "Error log file created successfully at: $logFile";
    } else {
        echo "Failed to create error log file at: $logFile";
    }
}
?>
