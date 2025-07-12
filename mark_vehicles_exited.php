<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

$target_date = '2025-05-18';

try {
    // Update exit_time for vehicles parked on the target date (with exit_time IS NULL)
    $stmt = $pdo->prepare("
        UPDATE parking_entries
        SET exit_time = NOW()
        WHERE DATE(entry_time) = ? AND exit_time IS NULL
    ");
    $stmt->execute([$target_date]);

    $affected_rows = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Marked $affected_rows vehicles as exited for entry date $target_date."
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
