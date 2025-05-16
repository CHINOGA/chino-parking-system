<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Set default date range to current week (Monday to Sunday) if not provided
if (!$start_date || !$end_date) {
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    $start_date = $monday;
    $end_date = $sunday;
}

$params = [];
$where = '';

if ($start_date && $end_date) {
    $where = 'pe.entry_time BETWEEN ? AND ?';
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
}

$stmt = $pdo->prepare("
    SELECT SUM(amount) AS total_revenue
    FROM revenue r
    JOIN parking_entries pe ON r.parking_entry_id = pe.id
    " . ($where ? "WHERE $where" : "") . "
");
if ($where) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);
$total_revenue = $revenue['total_revenue'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
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
.container { max-width: 600px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 5px; }
h2 { text-align: center; }
label { margin-right: 10px; }
input[type="text"], input[type="date"] {
    padding: 5px; margin-right: 10px; border: 1px solid #ccc; border-radius: 3px;
}
button {
    padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px;
    cursor: pointer;
}
button:hover { background: #0056b3; }

/* Responsive styles */
@media (max-width: 600px) {
    .container {
        margin: 10px;
        padding: 15px;
        max-width: 100%;
    }
    input[type="text"], input[type="date"], button {
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
<script>
function validateForm() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const errorDiv = document.getElementById('error');
    errorDiv.textContent = '';

    const startDate = startDateInput.value.trim();
    const endDate = endDateInput.value.trim();

    const dateRegex = /^\d{2}\/\d{2}\/\d{4}$/;

    if (!dateRegex.test(startDate)) {
        errorDiv.textContent = 'Start Date must be in mm/dd/yyyy format.';
        return false;
    }
    if (!dateRegex.test(endDate)) {
        errorDiv.textContent = 'End Date must be in mm/dd/yyyy format.';
        return false;
    }
    return true;
}
</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Revenue Report</h2>
    <form method="get" action="revenue_report.php">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime($start_date)); ?>" />
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime($end_date)); ?>" />
    </form>
    <p>Total Revenue: <strong>TZS <?php echo number_format($total_revenue, 2); ?></strong></p>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
    // Fetch revenue report initially
    fetchRevenueReport();

    // Fetch revenue report on date change
    document.getElementById('start_date').addEventListener('change', () => {
        fetchRevenueReport();
    });
    document.getElementById('end_date').addEventListener('change', () => {
        fetchRevenueReport();
    });
});

async function fetchRevenueReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    const url = `revenue_report.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

    const response = await fetch(url);
    const text = await response.text();

    // Parse the returned HTML and update the revenue amount
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    const newRevenue = doc.querySelector('p strong').textContent;

    document.querySelector('p strong').textContent = newRevenue;
}
</script>
</body>
</html>
