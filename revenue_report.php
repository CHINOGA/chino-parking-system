pushp
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
        SELECT vehicle_id FROM parking_entries
        WHERE entry_time < ?
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
    SELECT YEAR(pe.entry_time) AS year, WEEK(pe.entry_time, 1) AS week, 
           SUM(r.amount) AS weekly_revenue, COUNT(*) AS transactions
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
    SELECT MONTH(pe.entry_time) AS month, 
           SUM(r.amount) AS monthly_revenue, COUNT(*) AS transactions
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
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
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

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="description" content="Chino Parking System - Revenue report and analytics dashboard for vehicle parking management." />
<title>Chino Parking System - Revenue Report</title>
<link rel="manifest" href="manifest.json" />
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js').then(function(registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      }, function(err) {
        console.log('ServiceWorker registration failed: ', err);
      });
    });
  }
</script>
<style>
/* Revised styles for revenue report page */
body {
  background-color: #f9fafb; /* bg-gray-50 */
  font-family: 'Segoe UI', 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; /* font-sans */
  color: #111827; /* text-gray-900 */
  margin: 0; /* m-0 */
}
.container {
  max-width: 64rem; /* max-w-5xl */
  margin-left: auto;
  margin-right: auto; /* mx-auto */
  margin-top: 2.5rem; /* mt-10 */
  background: #fff; /* bg-white */
  border-radius: 0.5rem; /* rounded-lg */
  padding: 2.5rem; /* p-10 */
  box-shadow: 0 4px 24px 0 rgba(0,0,0,0.08); /* shadow-md */
}
h2 {
  text-align: center;
  font-size: 1.875rem;
  font-weight: 800;
  margin-bottom: 2rem;
  color: #1f2937;
}
form label {
  margin-right: 1.5rem;
  font-weight: 600;
  color: #374151;
}
input[type="text"],
input[type="date"],
select {
  padding: 0.75rem; /* p-3 */
  border-radius: 0.375rem; /* rounded-md */
  border: 1px solid #d1d5db; /* border-gray-300 */
  background: #fff; /* bg-white */
  color: #111827; /* text-gray-900 */
  margin-bottom: 1.5rem; /* mb-6 */
  outline: none;
  transition: box-shadow 0.2s;
}
input[type="text"]:focus,
input[type="date"]:focus,
select:focus {
  box-shadow: 0 0 0 2px #60a5fa; /* focus:ring-2 focus:ring-blue-400 */
}
button {
  padding: 0.75rem 1.5rem; /* px-6 py-3 */
  background: #2563eb; /* bg-blue-600 */
  color: #fff; /* text-white */
  font-weight: 600; /* font-semibold */
  border-radius: 0.375rem; /* rounded-md */
  border: none;
  transition: background 0.3s;
}
button:hover {
  background: #1d4ed8; /* hover:bg-blue-700 */
}
table {
  width: 100%; /* w-full */
  border-collapse: collapse;
  margin-top: 1.5rem; /* mt-6 */
}
th,
td {
  border: 1px solid #d1d5db; /* border-gray-300 */
  padding: 1rem; /* px-4 py-2 */
  text-align: left;
}
th {
  background: #f3f4f6; /* bg-gray-100 */
  color: #374151; /* text-gray-700 */
}
.summary {
  margin-top: 1.25rem; /* mt-5 */
  font-weight: bold;
}
.export-btn {
  margin-top: 0.5rem; /* mt-2 */
}

/* Responsive styles */
@media (max-width: 600px) {
  body, html {
    overflow-x: hidden;
  }
  .container {
    max-width: 100% !important;
    margin-left: 0.5rem !important;
    margin-right: 0.5rem !important;
    padding: 0.5rem !important;
  }
  input[type="text"],
  input[type="date"],
  button,
  select {
    width: 100%;
    max-width: 100%;
    margin-bottom: 1rem;
    box-sizing: border-box;
  }
  label {
    display: block;
    margin-bottom: 0.25rem;
  }
  canvas {
    width: 100% !important;
    height: auto !important;
  }
}

/* Remove horizontal scroll on tables for small screens */
@media (max-width: 600px) {
  table {
    width: 100%;
    border: 0;
  }
  thead {
    display: none;
  }
  tr {
    display: block;
    margin-bottom: 1rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    padding: 0.5rem;
  }
  td {
    display: block;
    text-align: right;
    border: none;
    border-bottom: 1px solid #eee;
    position: relative;
    padding-left: 50%;
    white-space: normal;
  }
  td:last-child {
    border-bottom: 0;
  }
  td:before {
    content: attr(data-label);
    position: absolute;
    left: 1rem;
    top: 0.5rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    color: #6b7280;
    white-space: nowrap;
  }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function validateForm() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const errorDiv = document.getElementById('error');
    errorDiv.textContent = '';

    const startDate = startDateInput.value.trim();
    const endDate = endDateInput.value.trim();

    const dateRegex = /^\\d{4}-\\d{2}-\\d{2}$/;

    if (!dateRegex.test(startDate)) {
        errorDiv.textContent = 'Start Date must be in yyyy-mm-dd format.';
        return false;
    }
    if (!dateRegex.test(endDate)) {
        errorDiv.textContent = 'End Date must be in yyyy-mm-dd format.';
        return false;
    }
    return true;
}

function exportTableToCSV(filename) {
    const csv = [];
    const rows = document.querySelectorAll("table tr");
    for (const row of rows) {
        const cols = row.querySelectorAll("td, th");
        const rowData = [];
        for (const col of cols) {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(","));
    }
    const csvString = csv.join("\\n");
    const blob = new Blob([csvString], { type: "text/csv" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

window.addEventListener('DOMContentLoaded', () => {
    fetchRevenueReport();

    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // When start date changes, update end date to start_date + 6 days
    startDateInput.addEventListener('change', () => {
        const startDate = new Date(startDateInput.value);
        if (!isNaN(startDate)) {
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + 6);
            const yyyy = endDate.getFullYear();
            const mm = String(endDate.getMonth() + 1).padStart(2, '0');
            const dd = String(endDate.getDate()).padStart(2, '0');
            endDateInput.value = `${yyyy}-${mm}-${dd}`;
            fetchRevenueReport();
        }
    });

    // Since end date is readonly, no need to add event listener for it

    document.getElementById('vehicle_type').addEventListener('change', fetchRevenueReport);
});

async function fetchRevenueReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const vehicleType = document.getElementById('vehicle_type').value;

    const url = `revenue_report.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&vehicle_type=${encodeURIComponent(vehicleType)}`;

    const response = await fetch(url);
    const text = await response.text();

    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');

    document.getElementById('total_revenue').textContent = doc.getElementById('total_revenue').textContent;
    document.getElementById('daily_revenue_table').innerHTML = doc.getElementById('daily_revenue_table').innerHTML;
    document.getElementById('revenue_by_type_table').innerHTML = doc.getElementById('revenue_by_type_table').innerHTML;
    document.getElementById('summary_stats').innerHTML = doc.getElementById('summary_stats').innerHTML;

    updateChart(doc.getElementById('chart_data').textContent);
    updatePeakDaysChart(doc.getElementById('peak_days_chart_data').textContent);
}

function updateChart(chartDataJson) {
    const chartData = JSON.parse(chartDataJson);
    const ctx = document.getElementById('revenueChart').getContext('2d');

    if (window.revenueChartInstance) {
        window.revenueChartInstance.destroy();
    }

    window.revenueChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Daily Revenue (TZS)',
                data: chartData.data,
                borderColor: 'rgba(75, 192, 192, 1)',
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Revenue (TZS)'
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

function updatePeakDaysChart(chartDataJson) {
    const chartData = JSON.parse(chartDataJson);
    const ctx = document.getElementById('peakDaysChart').getContext('2d');

    if (window.peakDaysChartInstance) {
        window.peakDaysChartInstance.destroy();
    }

    window.peakDaysChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Vehicle Count',
                data: chartData.data,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });
}

</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Revenue Report</h2>
    <form method="get" action="revenue_report.php" onsubmit="return validateForm()" class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="form-group">
            <label for="start_date" class="form-label">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($start_date))); ?>" required />
        </div>
        <div class="form-group">
            <label for="end_date" class="form-label">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($end_date))); ?>" required readonly />
        </div>
        <div class="form-group">
            <label for="vehicle_type" class="form-label">Vehicle Type:</label>
            <select id="vehicle_type" name="vehicle_type" class="form-select">
                <option value="All" <?= $vehicle_type_filter === 'All' ? 'selected' : '' ?>>All</option>
                <option value="Motorcycle" <?= $vehicle_type_filter === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                <option value="Bajaj" <?= $vehicle_type_filter === 'Bajaj' ? 'selected' : '' ?>>Bajaj</option>
                <option value="Car" <?= $vehicle_type_filter === 'Car' ? 'selected' : '' ?>>Car</option>
                <option value="Truck" <?= $vehicle_type_filter === 'Truck' ? 'selected' : '' ?>>Truck</option>
                <option value="Other" <?= $vehicle_type_filter === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <!-- Removed filter button for real-time filtering -->
    </form>
    <p>Total Revenue: <strong id="total_revenue">TZS <?php echo number_format($total_revenue, 2); ?></strong></p>

    <h3>Daily Revenue Breakdown</h3>
    <div style="overflow-x:auto;">
      <canvas id="revenueChart"></canvas>
      <table id="daily_revenue_table" class="table table-striped table-bordered">
          <thead class="table-dark">
              <tr>
                  <th>Date</th>
                  <th>Revenue (TZS)</th>
                  <th>Transactions</th>
              </tr>
          </thead>
          <tbody>
            <?php foreach ($daily_revenue_data as $day): ?>
            <tr>
                <td data-label="Date"><?= htmlspecialchars($day['date']) ?></td>
                <td data-label="Revenue (TZS)"><?= number_format($day['daily_revenue'], 2) ?></td>
                <td data-label="Transactions"><?= htmlspecialchars($day['transactions']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
      </table>
    </div>

    <h3>Revenue by Vehicle Type</h3>
    <div style="overflow-x:auto;">
      <table id="revenue_by_type_table" class="table table-striped table-bordered">
          <thead class="table-dark">
              <tr>
                  <th>Vehicle Type</th>
                  <th>Revenue (TZS)</th>
              </tr>
          </thead>
          <tbody>
            <?php foreach ($revenue_by_type as $type): ?>
            <tr>
                <td data-label="Vehicle Type"><?= htmlspecialchars($type['vehicle_type']) ?></td>
                <td data-label="Revenue (TZS)"><?= number_format($type['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
      </table>
    </div>

    <div id="summary_stats" class="summary">
        <p>Average Daily Revenue: TZS <?= number_format($average_daily_revenue, 2) ?></p>
        <p>Highest Revenue Day: <?= htmlspecialchars($highest_revenue_day) ?> (TZS <?= number_format($highest_revenue_amount, 2) ?>)</p>
        <p>Total Transactions: <?= htmlspecialchars($total_transactions) ?></p>
    </div>

    <h3>Peak Days of the Week (Vehicle Count)</h3>
    <canvas id="peakDaysChart" width="800" height="400"></canvas>
    <div id="chart_data" style="display:none;">
        <?php echo json_encode(['labels' => $chart_labels, 'data' => $chart_data]); ?>
    </div>
    <div id="peak_days_chart_data" style="display:none;">
        <?php echo json_encode(['labels' => $peak_labels, 'data' => $peak_counts]); ?>
    </div>

    <h3>First-Time Parked Vehicles</h3>
    <canvas id="firstTimeVehiclesChart" width="800" height="400"></canvas>
    <div id="first_time_chart_data" style="display:none;">
        <?php echo json_encode(['labels' => $first_time_labels, 'data' => $first_time_counts]); ?>
    </div>

    <!-- Weekly Revenue Report Section -->
    <h3>Weekly Revenue Report (Last 12 Weeks)</h3>
    <canvas id="weeklyRevenueChart" width="800" height="400"></canvas>
    <table id="weekly_revenue_table" class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Week</th>
                <th>Revenue (TZS)</th>
                <th>Transactions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weekly_labels as $index => $label): ?>
            <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td><?= number_format($weekly_revenue[$index], 2) ?></td>
                <td><?= htmlspecialchars($weekly_transactions[$index]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Monthly Revenue Report Section -->
    <h3>Monthly Revenue Report (Year <?= htmlspecialchars($report_year) ?>)</h3>
    <canvas id="monthlyRevenueChart" width="800" height="400"></canvas>
    <table id="monthly_revenue_table" class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Month</th>
                <th>Revenue (TZS)</th>
                <th>Transactions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($monthly_labels as $index => $month): ?>
            <tr>
                <td><?= htmlspecialchars($month) ?></td>
                <td><?= number_format($monthly_revenue[$index], 2) ?></td>
                <td><?= htmlspecialchars($monthly_transactions[$index]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function updateFirstTimeVehiclesChart(chartDataJson) {
    const chartData = JSON.parse(chartDataJson);
    const ctx = document.getElementById('firstTimeVehiclesChart').getContext('2d');

    if (window.firstTimeVehiclesChartInstance) {
        window.firstTimeVehiclesChartInstance.destroy();
    }

    window.firstTimeVehiclesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'First-Time Parked Vehicles',
                data: chartData.data,
                backgroundColor: 'rgba(255, 159, 64, 0.7)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });
}

function updateWeeklyRevenueChart(chartDataJson) {
    const chartData = JSON.parse(chartDataJson);
    const ctx = document.getElementById('weeklyRevenueChart').getContext('2d');

    if (window.weeklyRevenueChartInstance) {
        window.weeklyRevenueChartInstance.destroy();
    }

    window.weeklyRevenueChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Weekly Revenue (TZS)',
                data: chartData.data,
                borderColor: 'rgba(153, 102, 255, 1)',
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Week'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Revenue (TZS)'
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

function updateMonthlyRevenueChart(chartDataJson) {
    const chartData = JSON.parse(chartDataJson);
    const ctx = document.getElementById('monthlyRevenueChart').getContext('2d');

    if (window.monthlyRevenueChartInstance) {
        window.monthlyRevenueChartInstance.destroy();
    }

    window.monthlyRevenueChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Monthly Revenue (TZS)',
                data: chartData.data,
                backgroundColor: 'rgba(255, 206, 86, 0.7)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });
}

const originalFetchRevenueReport = fetchRevenueReport;
fetchRevenueReport = async function() {
    await originalFetchRevenueReport();

    const firstTimeChartData = document.getElementById('first_time_chart_data').textContent;
    updateFirstTimeVehiclesChart(firstTimeChartData);

    const weeklyChartData = JSON.stringify({
        labels: <?php echo json_encode($weekly_labels); ?>,
        data: <?php echo json_encode($weekly_revenue); ?>
    });
    updateWeeklyRevenueChart(weeklyChartData);

    const monthlyChartData = JSON.stringify({
        labels: <?php echo json_encode($monthly_labels); ?>,
        data: <?php echo json_encode($monthly_revenue); ?>
    });
    updateMonthlyRevenueChart(monthlyChartData);
};
</script>
</body>
</html>
