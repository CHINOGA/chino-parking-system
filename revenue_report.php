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

// Set default date range to current week (Monday to Sunday) if not provided
if (!$start_date || !$end_date) {
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    $start_date = $monday;
    $end_date = $sunday;
}

$params = [];
$where = 'pe.entry_time BETWEEN ? AND ?';
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
    $where .= ' AND v.vehicle_type = ?';
    $params[] = $vehicle_type_filter;
}

// Fetch total revenue
$stmt = $pdo->prepare("
    SELECT SUM(r.amount) AS total_revenue
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE $where
");
$stmt->execute($params);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);
$total_revenue = $revenue['total_revenue'] ?? 0;

// Fetch daily revenue breakdown
$stmt = $pdo->prepare("
    SELECT DATE(pe.entry_time) AS date, SUM(r.amount) AS daily_revenue, COUNT(*) AS transactions
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE $where
    GROUP BY DATE(pe.entry_time)
    ORDER BY DATE(pe.entry_time) ASC
");
$stmt->execute($params);
$daily_revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch revenue by vehicle type
$stmt = $pdo->prepare("
    SELECT v.vehicle_type, SUM(r.amount) AS revenue
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    JOIN vehicles v ON pe.vehicle_id = v.id
    WHERE $where
    GROUP BY v.vehicle_type
");
$stmt->execute($params);
$revenue_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_days = count($daily_revenue_data);
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
");
$stmt->execute(array_merge($first_time_params, [$start_date . ' 00:00:00']));
$first_time_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare first-time vehicles chart data
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
body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; }
.container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 5px; }
h2 { text-align: center; }
label { margin-right: 10px; }
input[type="text"], input[type="date"], select {
    padding: 5px; margin-right: 10px; border: 1px solid #ccc; border-radius: 3px;
}
button {
    padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px;
    cursor: pointer;
}
button:hover { background: #0056b3; }
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}
th {
    background-color: #f2f2f2;
}
.summary {
    margin-top: 20px;
    font-weight: bold;
}
.export-btn {
    margin-top: 10px;
}
@media (max-width: 600px) {
    .container {
        margin: 10px;
        padding: 15px;
        max-width: 100%;
    }
    input[type="text"], input[type="date"], button, select {
        font-size: 1em;
        margin-bottom: 10px;
        width: 100%;
    }
    label {
        display: block;
        margin-bottom: 5px;
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
    <form method="get" action="revenue_report.php" onsubmit="return validateForm()">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($start_date))); ?>" required />
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($end_date))); ?>" required />
        <label for="vehicle_type">Vehicle Type:</label>
        <select id="vehicle_type" name="vehicle_type">
            <option value="All" <?= $vehicle_type_filter === 'All' ? 'selected' : '' ?>>All</option>
            <option value="Motorcycle" <?= $vehicle_type_filter === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
            <option value="Bajaj" <?= $vehicle_type_filter === 'Bajaj' ? 'selected' : '' ?>>Bajaj</option>
            <option value="Car" <?= $vehicle_type_filter === 'Car' ? 'selected' : '' ?>>Car</option>
            <option value="Truck" <?= $vehicle_type_filter === 'Truck' ? 'selected' : '' ?>>Truck</option>
            <option value="Other" <?= $vehicle_type_filter === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
        <!-- Removed filter button for real-time filtering -->
    </form>
    <p>Total Revenue: <strong id="total_revenue">TZS <?php echo number_format($total_revenue, 2); ?></strong></p>

    <h3>Daily Revenue Breakdown</h3>
    <canvas id="revenueChart" width="800" height="400"></canvas>
    <table id="daily_revenue_table">
        <thead>
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

    <h3>Revenue by Vehicle Type</h3>
    <table id="revenue_by_type_table">
        <thead>
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
</body>
</html>
