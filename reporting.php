<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$vehicle_type_filter = $_GET['vehicle_type'] ?? '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if (empty($start_date) || strtotime($start_date) === false) {
    $start_date = date('Y-m-d', strtotime('monday this week'));
}
if (empty($end_date) || strtotime($end_date) === false) {
    $end_date = date('Y-m-d', strtotime('sunday this week'));
}

$params = [];
$where = '';

if ($start_date && $end_date) {
    $where = 'pe.entry_time BETWEEN ? AND ?';
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
}

if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
    $params[] = $vehicle_type_filter;
}

// Parked vehicles (no exit_time)
$stmt = $pdo->prepare("
    SELECT v.registration_number, v.vehicle_type, v.driver_name, v.phone_number, 
    CONVERT_TZ(pe.entry_time, '+00:00', '+03:00') AS entry_time
    FROM parking_entries pe
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE pe.exit_time IS NULL
    " . ($where ? "AND $where" : "") . "
    ORDER BY pe.entry_time DESC
");
$stmt->execute($params);
$parked = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exited vehicles with pagination support and stay duration calculation
$limit = 10;

$sqlExited = "
    SELECT v.registration_number, v.vehicle_type, v.driver_name, v.phone_number, 
    CONVERT_TZ(pe.entry_time, '+00:00', '+03:00') AS entry_time, 
    CONVERT_TZ(pe.exit_time, '+00:00', '+03:00') AS exit_time,
    TIMESTAMPDIFF(MINUTE, pe.entry_time, pe.exit_time) AS stay_duration_minutes
    FROM parking_entries pe
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE pe.exit_time IS NOT NULL
";

if ($where) {
    $sqlExited .= " AND $where ";
}

$sqlExited .= " ORDER BY pe.exit_time DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sqlExited);
$stmt->execute($params);
$exited = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'parked' => $parked,
    'parked_count' => count($parked),
    'exited' => $exited,
]);
exit;
?>
