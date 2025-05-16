<?php
// Centralized error handling setup

// Enable error reporting for development
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Optional debug mode
define('DEBUG_MODE', false);

// Custom error handler to log errors and optionally display them
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $message = "Error [$errno] in $errfile at line $errline: $errstr";
    error_log($message);
    if (DEBUG_MODE) {
        echo "<div style='color:red; font-weight:bold;'>$message</div>";
    }
    /* Don't execute PHP internal error handler */
    return true;
}

// Custom exception handler
function customExceptionHandler($exception) {
    $message = "Uncaught exception: " . $exception->getMessage() .
               " in " . $exception->getFile() .
               " on line " . $exception->getLine();
    error_log($message);
    if (DEBUG_MODE) {
        echo "<div style='color:red; font-weight:bold;'>$message</div>";
    }
}

// Register handlers
set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");
?>
