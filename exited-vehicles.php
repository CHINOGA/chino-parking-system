<?php
require_once 'auth.php';
require_role([1, 2, 3]); // Allow admin(1), cashier(2), security(3)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Initialize $start_date and $end_date to avoid undefined variable notices
$start_date = '';
$end_date = '';

if (isset($_GET['action']) && ($_GET['action'] === 'filter' || $_GET['action'] === 'export')) {
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $vehicle_type_filter = $_GET['vehicle_type'] ?? '';

    // Initialize start_date and end_date if empty or invalid
    if (empty($start_date) || strtotime($start_date) === false) {
        $start_date = date('Y-m-d', strtotime('monday this week'));
    }
    if (empty($end_date) || strtotime($end_date) === false) {
        $end_date = date('Y-m-d', strtotime('sunday this week'));
    }

    $params = [];
    $where = '';

    if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
        $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
        $params[] = $vehicle_type_filter;
    }

    if ($_GET['action'] === 'export') {
        // Export CSV for exited vehicles only
        $stmt = $pdo->prepare("
            SELECT v.registration_number, v.vehicle_type, v.driver_name, v.phone_number, 
            CONVERT_TZ(pe.entry_time, '+00:00', '+03:00') AS entry_time, 
            CONVERT_TZ(pe.exit_time, '+00:00', '+03:00') AS exit_time,
            TIMESTAMPDIFF(MINUTE, pe.entry_time, pe.exit_time) AS stay_duration_minutes
            FROM parking_entries pe
            JOIN vehicles v ON pe.vehicle_id = v.id
            WHERE pe.exit_time IS NOT NULL
            " . ($where ? "AND $where" : "") . "
            ORDER BY pe.exit_time DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="exited_vehicles_' . $start_date . '_to_' . $end_date . '.csv"');

        $output = fopen('php://output', 'w');

        if (!empty($data)) {
            // Output header row
            fputcsv($output, array_keys($data[0]));
            // Output data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    // Exited vehicles with pagination support and stay duration calculation
    $limit = 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

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
        'exited' => $exited,
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Chino Parking System - Exited Vehicles</title>
<link rel="manifest" href="manifest.json" />
<!-- Tailwind CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js')
      .then(function(registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      })
      .catch(function(error) {
        console.error('ServiceWorker registration failed:', error);
      });
    });
  }
</script>
<style>
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
  color: #212529;
  margin: 0;
  padding: 0;
}
.container {
  max-width: 960px;
  margin: 3rem auto;
  background: white;
  border-radius: 0.5rem;
  padding: 2rem;
  box-shadow: 0 0 15px rgba(0,0,0,0.1);
}
h2 {
  text-align: center;
  font-weight: 700;
  margin-bottom: 2rem;
  color: #212529;
}
form label {
  margin-right: 1rem;
  font-weight: 600;
  color: #495057;
}
input[type="text"],
input[type="date"],
select {
  padding: 0.5rem;
  border-radius: 0.375rem;
  border: 1px solid #ced4da;
  margin-bottom: 1rem;
  width: auto;
  min-width: 150px;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}
th,
td {
  border: 1px solid #dee2e6;
  padding: 0.75rem;
  text-align: left;
  vertical-align: middle;
  word-wrap: break-word;
}
th {
  background-color: #e9ecef;
  color: #495057;
}

/* Responsive styles */
@media (max-width: 900px) {
  .container {
    margin: 1rem;
    padding: 1rem;
  }
  input[type="text"],
  input[type="date"],
  select {
    width: 100%;
    margin-bottom: 1rem;
  }
  label {
    display: block;
    margin-bottom: 0.5rem;
  }
  table {
    overflow-x: auto;
    white-space: nowrap;
  }
}

/* Mobile styles */
@media (max-width: 600px) {
  body {
    font-size: 0.875rem;
  }
}
</style>
<script>
let exitedOffset = 10;
let loadingExited = false;

async function fetchExitedVehicles() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const vehicleType = document.getElementById('vehicle_type').value;

    const url = `exited-vehicles.php?action=filter&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&vehicle_type=${encodeURIComponent(vehicleType)}&offset=0`;

    const response = await fetch(url);
    const data = await response.json();

    const exitedTableBody = document.getElementById('exited_tbody');
    exitedTableBody.innerHTML = '';
    data.exited.forEach(v => {
        const entryDateUTC = new Date(v.entry_time + 'Z');
        const exitDateUTC = new Date(v.exit_time + 'Z');
        const entryDateEAT = new Date(entryDateUTC.getTime() + 3 * 60 * 60 * 1000);
        const exitDateEAT = new Date(exitDateUTC.getTime() + 3 * 60 * 60 * 1000);
        const entryDateStr = entryDateEAT.toLocaleString('en-GB', { hour12: false });
        const exitDateStr = exitDateEAT.toLocaleString('en-GB', { hour12: false });

        const row = `<tr>
            <td data-label="Registration Number">${v.registration_number}</td>
            <td data-label="Vehicle Type">${v.vehicle_type}</td>
            <td data-label="Driver Name">${v.driver_name}</td>
            <td data-label="Phone Number">${v.phone_number}</td>
            <td data-label="Entry Time">${entryDateStr}</td>
            <td data-label="Exit Time">${exitDateStr}</td>
        </tr>`;
        exitedTableBody.insertAdjacentHTML('beforeend', row);
    });
    exitedOffset = data.exited.length;
}

async function loadMoreExited() {
    if (loadingExited) return;
    loadingExited = true;

    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const vehicleType = document.getElementById('vehicle_type').value;

    const url = `exited-vehicles.php?action=filter&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&vehicle_type=${encodeURIComponent(vehicleType)}&offset=${exitedOffset}`;

    const response = await fetch(url);
    const data = await response.json();

    if (data.exited.length === 0) {
        window.removeEventListener('scroll', onScroll);
        return;
    }

    const exitedTableBody = document.getElementById('exited_tbody');
    data.exited.forEach(v => {
        const entryDateUTC = new Date(v.entry_time + 'Z');
        const exitDateUTC = new Date(v.exit_time + 'Z');
        const entryDateEAT = new Date(entryDateUTC.getTime() + 3 * 60 * 60 * 1000);
        const exitDateEAT = new Date(exitDateUTC.getTime() + 3 * 60 * 60 * 1000);
        const entryDateStr = entryDateEAT.toLocaleString('en-GB', { hour12: false });
        const exitDateStr = exitDateEAT.toLocaleString('en-GB', { hour12: false });

        const row = `<tr>
            <td data-label="Registration Number">${v.registration_number}</td>
            <td data-label="Vehicle Type">${v.vehicle_type}</td>
            <td data-label="Driver Name">${v.driver_name}</td>
            <td data-label="Phone Number">${v.phone_number}</td>
            <td data-label="Entry Time">${entryDateStr}</td>
            <td data-label="Exit Time">${exitDateStr}</td>
        </tr>`;
        exitedTableBody.insertAdjacentHTML('beforeend', row);
    });

    exitedOffset += data.exited.length;
    loadingExited = false;
}

function onScroll() {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
        loadMoreExited();
    }
}

window.addEventListener('DOMContentLoaded', () => {
    fetchExitedVehicles();

    document.getElementById('start_date').addEventListener('change', () => {
        exitedOffset = 0;
        fetchExitedVehicles();
    });
    document.getElementById('end_date').addEventListener('change', () => {
        exitedOffset = 0;
        fetchExitedVehicles();
    });
    document.getElementById('vehicle_type').addEventListener('change', () => {
        exitedOffset = 0;
        fetchExitedVehicles();
    });

    document.getElementById('search_exited').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = document.getElementById('exited_tbody').getElementsByTagName('tr');
        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName('td');
            let match = false;
            for (let i = 0; i < cells.length; i++) {
                if (cells[i].textContent.toLowerCase().includes(filter)) {
                    match = true;
                    break;
                }
            }
            row.style.display = match ? '' : 'none';
        });
    });

    window.addEventListener('scroll', onScroll);
});
</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Exited Vehicles</h2>
<form id="filter_form" class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="form-group">
            <label for="start_date" class="form-label">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?: date('Y-m-d', strtotime('monday this week'))); ?>" />
        </div>
        <div class="form-group">
            <label for="end_date" class="form-label">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?: date('Y-m-d', strtotime('sunday this week'))); ?>" />
        </div>
        <div class="form-group">
            <label for="vehicle_type" class="form-label">Vehicle Type:</label>
            <select id="vehicle_type" name="vehicle_type" class="form-select">
                <option value="All" <?php echo (!isset($_GET['vehicle_type']) || $_GET['vehicle_type'] === 'All') ? 'selected' : ''; ?>>All</option>
                <option value="Motorcycle" <?php echo (isset($_GET['vehicle_type']) && $_GET['vehicle_type'] === 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle</option>
                <option value="Bajaj" <?php echo (isset($_GET['vehicle_type']) && $_GET['vehicle_type'] === 'Bajaj') ? 'selected' : ''; ?>>Bajaj</option>
                <option value="Car" <?php echo (isset($_GET['vehicle_type']) && $_GET['vehicle_type'] === 'Car') ? 'selected' : ''; ?>>Car</option>
                <option value="Truck" <?php echo (isset($_GET['vehicle_type']) && $_GET['vehicle_type'] === 'Truck') ? 'selected' : ''; ?>>Truck</option>
                <option value="Other" <?php echo (isset($_GET['vehicle_type']) && $_GET['vehicle_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
    </form>
    <input type="text" id="search_exited" placeholder="Search exited vehicles..." class="form-control mb-3" style="max-width: 400px;" />
    <div class="table-responsive">
    <table class="table table-striped table-bordered fixed-header">
        <thead class="table-dark">
            <tr>
                <th scope="col">Registration Number</th>
                <th scope="col">Vehicle Type</th>
                <th scope="col">Driver Name</th>
                <th scope="col">Phone Number</th>
                <th scope="col">Entry Time</th>
                <th scope="col">Exit Time</th>
            </tr>
        </thead>
        <tbody id="exited_tbody"></tbody>
    </table>
    </div>
</div>
</body>
</html>
