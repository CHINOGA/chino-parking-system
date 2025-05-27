<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/vehicle_exit_all_error.log');
session_start();

// Clear any output buffer to prevent unexpected output before JSON
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/json');

$response = [
    'session_user_id' => $_SESSION['user_id'] ?? null,
    'request_method' => $_SERVER['REQUEST_METHOD'],
];

if (!isset($_SESSION['user_id'])) {
    $response['success'] = false;
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    // Find all active parking entries (exit_time IS NULL)
    $stmt = $pdo->prepare('
        SELECT pe.id AS entry_id, pe.entry_time
        FROM parking_entries pe
        WHERE pe.exit_time IS NULL
    ');
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$entries) {
        $response['success'] = false;
        $response['message'] = 'No active parking entries found.';
        echo json_encode($response);
        exit;
    }

    $fee_per_day = 1000;
    $exit_time = new DateTime();
    $total_exited = 0;

    // Begin transaction
    $pdo->beginTransaction();

    foreach ($entries as $entry) {
        $entry_time = new DateTime($entry['entry_time']);
        $interval = $entry_time->diff($exit_time);
        $days = max(1, (int)$interval->days); // Minimum 1 day
        $total_fee = $days * $fee_per_day;

        // Update exit_time to current timestamp
        $updateStmt = $pdo->prepare('UPDATE parking_entries SET exit_time = NOW() WHERE id = ?');
        $updateStmt->execute([$entry['entry_id']]);

        // Insert revenue record
        $insertStmt = $pdo->prepare('INSERT INTO revenue (parking_entry_id, amount) VALUES (?, ?)');
        $insertStmt->execute([$entry['entry_id'], $total_fee]);

        $total_exited++;
    }

    // Commit transaction
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = "Successfully exited $total_exited vehicles without sending SMS.";
    echo json_encode($response);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error in vehicle_exit_all_ajax.php: ' . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
} catch (Exception $e) {
    error_log('General error in vehicle_exit_all_ajax.php: ' . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    echo json_encode($response);
}
?>
