<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

header('Content-Type: application/json');

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$vehicle_type_filter = $_GET['vehicle_type'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$page_size = 7; // Show 7 days per page (a week)

if (!$start_date && !$end_date) {
    $end_date = date('Y-m-d', strtotime('yesterday'));
    $start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));
} elseif ($start_date && !$end_date) {
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
} elseif (!$start_date && $end_date) {
    $start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));
} else {
    $expected_end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
    if ($end_date !== $expected_end_date) {
        $end_date = $expected_end_date;
    }
}

$params = [];
$where = '';

if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
    $params[] = $vehicle_type_filter;
}

$whereClause = $where ? "WHERE $where" : "";

$dateFilter = "pe.entry_time BETWEEN ? AND ?";
$params[] = $start_date . ' 00:00:00';
$params[] = $end_date . ' 23:59:59';

if ($whereClause) {
    $whereClause .= " AND $dateFilter";
} else {
    $whereClause = "WHERE $dateFilter";
}

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT DATE(pe.entry_time)) AS total_days
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    $whereClause
");
$countStmt->execute($params);
$total_days = (int)$countStmt->fetchColumn();
$total_pages = (int)ceil($total_days / $page_size);

$offset = ($page - 1) * $page_size;

$stmt = $pdo->prepare("
    SELECT DATE(pe.entry_time) AS date, SUM(r.amount) AS daily_revenue, COUNT(*) AS transactions
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    $whereClause
    GROUP BY DATE(pe.entry_time)
    ORDER BY DATE(pe.entry_time) ASC
    LIMIT $page_size OFFSET $offset
");
$stmt->execute($params);
$raw_daily_revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);

$daily_revenue_data = [];
foreach ($period as $date) {
    $date_str = $date->format('Y-m-d');
    $daily_revenue_data[$date_str] = [
        'date' => $date_str,
        'daily_revenue' => 0,
        'transactions' => 0,
    ];
}

foreach ($raw_daily_revenue_data as $row) {
    $daily_revenue_data[$row['date']] = $row;
}

$daily_revenue_data = array_values($daily_revenue_data);

$stmt = $pdo->prepare("
    SELECT v.vehicle_type, SUM(r.amount) AS revenue
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    $whereClause
    GROUP BY v.vehicle_type
");
$stmt->execute($params);
$revenue_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

$average_daily_revenue = $total_days > 0 ? array_sum(array_column($daily_revenue_data, 'daily_revenue')) / $total_days : 0;
$highest_revenue_day = null;
$highest_revenue_amount = 0;
foreach ($daily_revenue_data as $day) {
    if ($day['daily_revenue'] > $highest_revenue_amount) {
        $highest_revenue_amount = $day['daily_revenue'];
        $highest_revenue_day = $day['date'];
    }
}
$total_transactions = array_sum(array_column($daily_revenue_data, 'transactions'));

$totalRevenueStmt = $pdo->prepare("
    SELECT SUM(r.amount) AS total_revenue
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    $whereClause
");
$totalRevenueStmt->execute($params);
$total_revenue = (float)$totalRevenueStmt->fetchColumn();

$response = [
    'total_revenue' => number_format($total_revenue, 2),
    'daily_revenue_data' => $daily_revenue_data,
    'revenue_by_type' => $revenue_by_type,
    'average_daily_revenue' => number_format($average_daily_revenue, 2),
    'highest_revenue_day' => $highest_revenue_day,
    'highest_revenue_amount' => number_format($highest_revenue_amount, 2),
    'total_transactions' => $total_transactions,
];

echo json_encode($response);
?>
