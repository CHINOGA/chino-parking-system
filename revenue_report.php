<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$vehicle_type_filter = $_GET['vehicle_type'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$page_size = 7; // Show 7 days per page (a week)

if (!$start_date && !$end_date) {
    // If no dates provided, set end_date to yesterday and start_date to 6 days before end_date (7 days total)
    $end_date = date('Y-m-d', strtotime('yesterday'));
    $start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));
} elseif ($start_date && !$end_date) {
    // If start_date provided but no end_date, set end_date to 6 days after start_date
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
} elseif (!$start_date && $end_date) {
    // If end_date provided but no start_date, set start_date to 6 days before end_date
    $start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));
} else {
    // Both dates provided, ensure range is exactly 7 days by adjusting end_date
    $expected_end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
    if ($end_date !== $expected_end_date) {
        $end_date = $expected_end_date;
    }
}

// Temporarily remove date filtering to show all data
$params = [];
$where = '';
if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
    $params[] = $vehicle_type_filter;
}
$whereClause = $where ? "WHERE $where" : "";

// Add date range filter to WHERE clause
$dateFilter = "pe.entry_time BETWEEN ? AND ?";
$params[] = $start_date . ' 00:00:00';
$params[] = $end_date . ' 23:59:59';

if ($whereClause) {
    $whereClause .= " AND $dateFilter";
} else {
    $whereClause = "WHERE $dateFilter";
}

// Get total count of distinct dates for pagination
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

// Generate full 7-day date range array
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

// Fill in actual data from query results
foreach ($raw_daily_revenue_data as $row) {
    $daily_revenue_data[$row['date']] = $row;
}

// Re-index array to be zero-based and ordered by date ascending
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

// Calculate summary statistics
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

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Fetch peak days of week data for current week (Monday to Sunday order)
$stmt = $pdo->prepare("
    SELECT DAYNAME(pe.entry_time) AS day_of_week, COUNT(*) AS vehicle_count
    FROM parking_entries pe
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE pe.entry_time BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(pe.entry_time)
    ORDER BY FIELD(DAYNAME(pe.entry_time), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->execute([$week_start . ' 00:00:00', $week_end . ' 23:59:59']);
$peak_days_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch first-time parked vehicles count by date
$first_time_where = 'pe.entry_time BETWEEN ? AND ?';
$first_time_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $first_time_where .= ' AND v.vehicle_type = ?';
    $first_time_params[] = $vehicle_type_filter;
}
$stmt = $pdo->prepare("
    SELECT DATE(pe.entry_time) AS date, COUNT(DISTINCT v.id) AS first_time_count
    FROM parking_entries pe
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE $first_time_where
    AND v.id NOT IN (
        SELECT vehicle_id FROM parking_entries WHERE entry_time < ?
    )
    GROUP BY DATE(pe.entry_time)
    ORDER BY DATE(pe.entry_time) ASC
    LIMIT $page_size OFFSET $offset
");
$stmt->execute(array_merge($first_time_params, [$start_date . ' 00:00:00']));
$first_time_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$first_time_labels = array_map(fn($d) => $d['date'], $first_time_data);
$first_time_counts = array_map(fn($d) => (int)$d['first_time_count'], $first_time_data);

// Prepare chart data for embedding in HTML
$chart_labels = array_map(fn($d) => $d['date'], $daily_revenue_data);
$chart_data = array_map(fn($d) => (float)$d['daily_revenue'], $daily_revenue_data);

$peak_labels = array_map(fn($d) => $d['day_of_week'], $peak_days_data);
$peak_counts = array_map(fn($d) => (int)$d['vehicle_count'], $peak_days_data);

// Fetch weekly revenue and transactions grouped by year and week number
$weeklyStmt = $pdo->prepare("
    SELECT YEAR(pe.entry_time) AS year, WEEK(pe.entry_time, 1) AS week, SUM(r.amount) AS weekly_revenue, COUNT(*) AS transactions
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    $whereClause
    GROUP BY year, week
    ORDER BY year DESC, week DESC
    LIMIT 12
");
$weeklyStmt->execute($params);
$weekly_data_raw = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare weekly data for chart and table
$weekly_labels = [];
$weekly_revenue = [];
$weekly_transactions = [];
foreach (array_reverse($weekly_data_raw) as $week) {
    $weekly_labels[] = "Week {$week['week']}, {$week['year']}";
    $weekly_revenue[] = (float)$week['weekly_revenue'];
    $weekly_transactions[] = (int)$week['transactions'];
}

// Fetch monthly revenue and transactions grouped by year and month
// For monthly report, show months January to December for the year of start_date or current year
$report_year = $start_date ? (int)date('Y', strtotime($start_date)) : (int)date('Y');
$monthlyStmt = $pdo->prepare("
    SELECT MONTH(pe.entry_time) AS month, SUM(r.amount) AS monthly_revenue, COUNT(*) AS transactions
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE YEAR(pe.entry_time) = ?
    " . ($vehicle_type_filter && $vehicle_type_filter !== 'All' ? "AND v.vehicle_type = ?" : "") . "
    GROUP BY month
    ORDER BY month ASC
");
$monthlyParams = [$report_year];
if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $monthlyParams[] = $vehicle_type_filter;
}
$monthlyStmt->execute($monthlyParams);
$monthly_data_raw = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare monthly data for chart and table
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$monthly_labels = [];
$monthly_revenue = [];
$monthly_transactions = [];

// Initialize all months with zero
for ($m = 1; $m <= 12; $m++) {
    $monthly_labels[$m] = $month_names[$m];
    $monthly_revenue[$m] = 0;
    $monthly_transactions[$m] = 0;
}

// Fill in actual data
foreach ($monthly_data_raw as $monthData) {
    $m = (int)$monthData['month'];
    $monthly_revenue[$m] = (float)$monthData['monthly_revenue'];
    $monthly_transactions[$m] = (int)$monthData['transactions'];
}

// Re-index arrays to zero-based for charts
$monthly_labels = array_values($monthly_labels);
$monthly_revenue = array_values($monthly_revenue);
$monthly_transactions = array_values($monthly_transactions);
?>
