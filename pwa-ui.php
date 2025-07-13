<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Chino Parking System - PWA UI</title>
<link rel="manifest" href="manifest.json" />
<link href="custom.css" rel="stylesheet" />
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
</style>
<script>
async function fetchData() {
    try {
        const response = await fetch('reporting.php?action=filter');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        renderData(data);
    } catch (error) {
        console.error('Fetch error:', error);
        document.getElementById('error').textContent = 'Failed to load data. Please try again later.';
    }
}

function renderData(data) {
    const parkedTbody = document.getElementById('parked_tbody');
    parkedTbody.innerHTML = '';
    data.parked.forEach(v => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${v.registration_number}</td>
            <td>${v.vehicle_type}</td>
            <td>${v.driver_name}</td>
            <td>${v.phone_number}</td>
            <td>${v.entry_time}</td>
            <td><button class="exit-btn" data-reg="${v.registration_number}">Exit</button></td>
        `;
        parkedTbody.appendChild(row);
    });

    const exitedTbody = document.getElementById('exited_tbody');
    exitedTbody.innerHTML = '';
    data.exited.forEach(v => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${v.registration_number}</td>
            <td>${v.vehicle_type}</td>
            <td>${v.driver_name}</td>
            <td>${v.phone_number}</td>
            <td>${v.entry_time}</td>
            <td>${v.exit_time}</td>
        `;
        exitedTbody.appendChild(row);
    });
}

window.addEventListener('DOMContentLoaded', () => {
    fetchData();

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
                    alert(result.message);
                    fetchData();
                } catch (error) {
                    alert('Error processing exit: ' + error.message);
                }
            }
        }
    });
});
</script>
</head>
<body>
<div class="container">
    <h2>Chino Parking System - Dashboard</h2>
    <div id="error" style="color: red;"></div>
    <h3>Parked Vehicles</h3>
    <table>
        <thead>
            <tr>
                <th>Registration Number</th>
                <th>Vehicle Type</th>
                <th>Driver Name</th>
                <th>Phone Number</th>
                <th>Entry Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="parked_tbody"></tbody>
    </table>
    <h3>Exited Vehicles</h3>
    <table>
        <thead>
            <tr>
                <th>Registration Number</th>
                <th>Vehicle Type</th>
                <th>Driver Name</th>
                <th>Phone Number</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
            </tr>
        </thead>
        <tbody id="exited_tbody"></tbody>
    </table>
</div>
</body>
</html>
