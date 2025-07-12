<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

    // Temporarily remove date filtering to show all data
    // if ($start_date && $end_date) {
    //     $where = 'pe.entry_time BETWEEN ? AND ?';
    //     $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    // }
    $where = '';
    $params = [];

    if ($vehicle_type_filter && $vehicle_type_filter !== 'All') {
        $where .= ($where ? ' AND ' : '') . 'v.vehicle_type = ?';
        $params[] = $vehicle_type_filter;
    }

    if ($_GET['action'] === 'export') {
        // Export CSV for parked or exited vehicles
        $export_type = $_GET['type'] ?? 'parked'; // 'parked' or 'exited'
        if ($export_type === 'parked') {
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
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
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
        }

        // Output CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $export_type . '_vehicles_' . $start_date . '_to_' . $end_date . '.csv"');

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
        'parked' => $parked,
        'parked_count' => count($parked),
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
<title>Chino Parking System - Reporting Dashboard</title>
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
button {
  padding: 0.5rem 1rem;
  background-color: #0d6efd;
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #0b5ed7;
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
.exit-btn {
  background-color: #0d6efd;
  color: white;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
.exit-btn:hover {
  background-color: #0b5ed7;
}

/* Responsive styles */
@media (max-width: 900px) {
  .container {
    margin: 1rem;
    padding: 1rem;
  }
  input[type="text"],
  input[type="date"],
  select,
  button {
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
  .exit-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
  }
}
</style>
<script>
async function fetchReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const vehicleType = document.getElementById('vehicle_type').value;

    const url = `reporting.php?action=filter&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&vehicle_type=${encodeURIComponent(vehicleType)}`;

    const response = await fetch(url);
    const data = await response.json();

    console.log('API response data:', data); // Debug log for API response

    // Show alerts for vehicles parked longer than threshold (e.g., 120 minutes)
    const alertsDiv = document.getElementById('alerts');
    const longParkedVehicles = data.parked.filter(v => {
        const entryDate = new Date(v.entry_time + 'Z');
        const now = new Date();
        const diffMinutes = (now - entryDate) / 60000;
        return diffMinutes > 120; // threshold in minutes
    });
    if (longParkedVehicles.length > 18) {
        alertsDiv.textContent = `Alert: ${longParkedVehicles.length} vehicle(s) have been parked for more than 18 hours.`; 
    } else {
        alertsDiv.textContent = '';
    }

    // Populate parked vehicles table
    const parkedCountElem = document.getElementById('parked_count');
    if (parkedCountElem) {
        parkedCountElem.textContent = `(${data.parked_count})`;
    }
    const parkedTableBody = document.getElementById('parked_tbody');
    parkedTableBody.innerHTML = '';
        data.parked.forEach(v => {
            // Convert entry_time string to Date object in UTC
            const entryDateUTC = new Date(v.entry_time + 'Z');
            // Add 3 hours to convert to East Africa Time (UTC+3)
            const entryDateEAT = new Date(entryDateUTC.getTime() + 3 * 60 * 60 * 1000);
            const entryDateStr = entryDateEAT.toLocaleString('en-GB', { hour12: false });

            const row = `<tr>
                <td data-label="Registration Number">${v.registration_number}</td>
                <td data-label="Vehicle Type">${v.vehicle_type}</td>
                <td data-label="Driver Name">${v.driver_name}</td>
                <td data-label="Phone Number">${v.phone_number}</td>
                <td data-label="Entry Time">${entryDateStr}</td>
                <td data-label="Action"><button class="exit-btn" data-reg="${v.registration_number}">Exit</button></td>
            </tr>`;
            parkedTableBody.insertAdjacentHTML('beforeend', row);
        });

        // Populate exited vehicles table
        const exitedTableBody = document.getElementById('exited_tbody');
        exitedTableBody.innerHTML = '';
        data.exited.forEach(v => {
            // Convert entry_time and exit_time strings to Date objects in UTC
            const entryDateUTC = new Date(v.entry_time + 'Z');
            const exitDateUTC = new Date(v.exit_time + 'Z');
            // Add 3 hours to convert to East Africa Time (UTC+3)
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
}

// Infinite scroll for exited vehicles
let exitedOffset = 10;
let loadingExited = false;

async function loadMoreExited() {
    if (loadingExited) return;
    loadingExited = true;

    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    const url = `reporting.php?action=filter&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&offset=${exitedOffset}`;

    const response = await fetch(url);
    const data = await response.json();

    const exitedTableBody = document.getElementById('exited_tbody');

    if (data.exited.length === 0) {
        // No more data
        window.removeEventListener('scroll', onScroll);
        return;
    }

    data.exited.forEach(v => {
        const row = `<tr>
            <td data-label="Registration Number">${v.registration_number}</td>
            <td data-label="Vehicle Type">${v.vehicle_type}</td>
            <td data-label="Driver Name">${v.driver_name}</td>
            <td data-label="Phone Number">${v.phone_number}</td>
            <td data-label="Entry Time">${v.entry_time}</td>
            <td data-label="Exit Time">${v.exit_time}</td>
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
    // Fetch report initially
    fetchReport();

    // Fetch report on date change or vehicle type change for real-time filtering
    document.getElementById('start_date').addEventListener('change', () => {
        exitedOffset = 10;
        fetchReport();
    });
    document.getElementById('end_date').addEventListener('change', () => {
        exitedOffset = 10;
        fetchReport();
    });
    document.getElementById('vehicle_type').addEventListener('change', () => {
        exitedOffset = 10;
        fetchReport();
    });

    // Delegate click event for exit buttons
    document.getElementById('parked_tbody').addEventListener('click', async (e) => {
        if (e.target && e.target.classList.contains('exit-btn')) {
            const regNum = e.target.getAttribute('data-reg');
            if (confirm(`Confirm exit for vehicle ${regNum}?`)) {
                try {
                    const response = await fetch('vehicle_exit_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ registration_number: regNum })
                    });
                    const result = await response.json();
                    showNotification(result.message);
                    exitedOffset = 10; // Reset offset after exit
                    fetchReport();
                } catch (error) {
                    showNotification('Error processing exit: ' + error.message);
                }
            }
        }
    });

    // Add event listener for Exit All button
    document.getElementById('exit_all_btn').addEventListener('click', async () => {
        if (confirm('Are you sure you want to exit all parked vehicles without sending SMS?')) {
            try {
                const response = await fetch('vehicle_exit_all_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                showNotification(result.message);
                // Reset exitedOffset to 0 to reload exited vehicles from start
                exitedOffset = 0;
                // Refresh the report after exiting all
                fetchReport();
            } catch (error) {
                showNotification('Error processing exit all: ' + error.message);
            }
        }
    });

    // Notification function
    function showNotification(message) {
        let notification = document.getElementById('notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'notification';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.backgroundColor = '#28a745';
            notification.style.color = 'white';
            notification.style.padding = '10px 20px';
            notification.style.borderRadius = '5px';
            notification.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
            notification.style.zIndex = '10000';
            document.body.appendChild(notification);
        }
        notification.textContent = message;
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    }

    // Add scroll event listener for infinite scroll
    window.addEventListener('scroll', onScroll);

    // Real-time search for parked vehicles
    document.getElementById('search_parked').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = document.getElementById('parked_tbody').getElementsByTagName('tr');
        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName('td');
            let match = false;
            for (let i = 0; i < cells.length - 1; i++) { // exclude last column (action)
                if (cells[i].textContent.toLowerCase().includes(filter)) {
                    match = true;
                    break;
                }
            }
            row.style.display = match ? '' : 'none';
        });
    });

    // Real-time search for exited vehicles
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
});
</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h2>Reporting Dashboard</h2>
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
    <div id="alerts" class="text-danger fw-bold mb-3"></div>

    <h3>Parked Vehicles <span id="parked_count"></span></h3>
    <button id="exit_all_btn" class="btn btn-primary mb-3">Exit All</button>
    <input type="text" id="search_parked" placeholder="Search parked vehicles..." class="form-control mb-3" style="max-width: 400px;" />
    <div class="table-responsive">
    <table class="table table-striped table-bordered fixed-header">
        <thead class="table-dark">
            <tr>
                <th scope="col">Registration Number</th>
                <th scope="col">Vehicle Type</th>
                <th scope="col">Driver Name</th>
                <th scope="col">Phone Number</th>
                <th scope="col">Entry Time</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody id="parked_tbody"></tbody>
    </table>
    </div>

    <h3>Exited Vehicles</h3>
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
