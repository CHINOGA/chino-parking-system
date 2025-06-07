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

// Set default date range to current week (Monday to Sunday) if not provided
if (!$start_date || !$end_date) {
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    $start_date = $monday;
    $end_date = $sunday;
}

// Temporarily remove date filtering to show all data
$params = [];
$where = '';

if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
    $params[] = $vehicle_type_filter;
}

$whereClause = $where ? "WHERE $where" : "";

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
$daily_revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);



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

// Fetch peak days of week data (Monday to Sunday order)
$stmt = $pdo->prepare("
    SELECT DAYNAME(pe.entry_time) AS day_of_week, COUNT(*) AS vehicle_count
    FROM parking_entries pe
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE pe.entry_time BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(pe.entry_time)
    ORDER BY FIELD(DAYNAME(pe.entry_time), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
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
  @apply bg-gray-50 font-sans text-gray-900 m-0;
}
.container {
  @apply max-w-5xl mx-auto mt-10 bg-white rounded-lg p-10 shadow-md;
}
h2 {
  @apply text-center text-3xl font-extrabold mb-8 text-gray-800;
}
form label {
  @apply mr-6 font-semibold text-gray-700;
}
input[type="text"],
input[type="date"],
select {
  @apply p-3 rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400 mb-6;
}
button {
  @apply px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition duration-300;
}
table {
  @apply w-full border-collapse mt-6;
}
th,
td {
  @apply border border-gray-300 px-4 py-2 text-left;
}
th {
  @apply bg-gray-100 text-gray-700;
}
.summary {
  @apply mt-5 font-bold;
}
.export-btn {
  @apply mt-2;
}

/* Responsive styles */
@media (max-width: 600px) {
  .container {
    @apply mx-4 p-4;
  }
  input[type="text"],
  input[type="date"],
  button,
  select {
    @apply w-full mb-4;
  }
  label {
    @apply block mb-1;
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

    document.getElementById('start_date').addEventListener('change', fetchRevenueReport);
    document.getElementById('end_date').addEventListener('change', fetchRevenueReport);
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
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($end_date))); ?>" required />
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
    <canvas id="revenueChart" width="800" height="400"></canvas>
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
                <td><?= htmlspecialchars($day['date']) ?></td>
                <td><?= number_format($day['daily_revenue'], 2) ?></td>
                <td><?= htmlspecialchars($day['transactions']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
+    <div class="d-flex justify-content-between align-items-center mt-3">
+        <button class="btn btn-primary" id="prevPageBtn" <?= $page <= 1 ? 'disabled' : '' ?>>Previous</button>
+        <span>Page <?= $page ?> of <?= $total_pages ?></span>
+        <button class="btn btn-primary" id="nextPageBtn" <?= $page >= $total_pages ? 'disabled' : '' ?>>Next</button>
+    </div>

    <h3>Revenue by Vehicle Type</h3>
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
                <td><?= htmlspecialchars($type['vehicle_type']) ?></td>
                <td><?= number_format($type['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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

const originalFetchRevenueReport = fetchRevenueReport;
fetchRevenueReport = async function() {
    await originalFetchRevenueReport();

    const firstTimeChartData = document.getElementById('first_time_chart_data').textContent;
    updateFirstTimeVehiclesChart(firstTimeChartData);
};
</script>
<script>
document.getElementById('prevPageBtn').addEventListener('click', () => {
    const urlParams = new URLSearchParams(window.location.search);
    let currentPage = parseInt(urlParams.get('page') || '1');
    if (currentPage > 1) {
        urlParams.set('page', currentPage - 1);
        window.location.search = urlParams.toString();
    }
});

document.getElementById('nextPageBtn').addEventListener('click', () => {
    const urlParams = new URLSearchParams(window.location.search);
    let currentPage = parseInt(urlParams.get('page') || '1');
    const totalPages = <?= $total_pages ?>;
    if (currentPage < totalPages) {
        urlParams.set('page', currentPage + 1);
        window.location.search = urlParams.toString();
    }
});
</script>
</body>
</html>
