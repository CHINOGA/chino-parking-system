<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Chino Parking System</title>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="custom.css" rel="stylesheet" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="vehicle-entry.php">Chino Parking System</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <?php if (isset($_SESSION['user_id'])): ?>
      <?php
        $role_id = $_SESSION['role_id'] ?? 0;
      ?>
      <ul class="navbar-nav ms-auto">
        <?php if (in_array($role_id, [1, 2, 3])): // admin, cashier, security ?>
        <li class="nav-item">
          <a class="nav-link" href="vehicle-entry.php">Vehicle Entry</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="parked-vehicles.php">Parked Vehicles</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="exited-vehicles.php">Exited Vehicles</a>
        </li>
        <?php endif; ?>
        <?php if (in_array($role_id, [1, 2])): // admin, cashier ?>
        <li class="nav-item">
          <a class="nav-link" href="revenue-report.php">Revenue Report</a>
        </li>
        <?php endif; ?>
        <?php if ($role_id === 1): // admin only ?>
        <li class="nav-item">
          <a class="nav-link" href="sms-send.php">Send SMS</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="driver-phone-delete.php">Delete Driver Phone</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="user-management.php">User Management</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin-password-reset.php">Reset Password</a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">Logout</a>
        </li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Loading Screen Overlay -->
<style>
  #loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 1;
    transition: opacity 0.5s ease;
  }
  #loading-overlay.loading-visible {
    display: flex;
  }
  #loading-overlay.fade-out {
    opacity: 0;
    pointer-events: none;
  }
  .loader {
    position: relative;
    width: 120px;
    height: 120px;
  }
  .loader div {
    box-sizing: border-box;
    display: block;
    position: absolute;
    width: 100%;
    height: 100%;
    border: 8px solid #fff;
    border-radius: 50%;
    animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
    border-color: #fff transparent transparent transparent;
  }
  .loader div:nth-child(1) {
    animation-delay: -0.45s;
  }
  .loader div:nth-child(2) {
    animation-delay: -0.3s;
  }
  .loader div:nth-child(3) {
    animation-delay: -0.15s;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>
<div id="loading-overlay" aria-live="polite" aria-busy="true" role="alert" class="loading-visible">
  <div class="loader">
    <div></div>
    <div></div>
    <div></div>
  </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Show loading overlay on page unload/navigation
  window.addEventListener('beforeunload', () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
      overlay.classList.add('loading-visible');
      overlay.classList.remove('fade-out');
    }
  });

  // Hide loading overlay on page load
  window.addEventListener('load', () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
      overlay.classList.add('fade-out');
      overlay.classList.remove('loading-visible');
      overlay.setAttribute('aria-busy', 'false');
    }
  });

  // Optional: Show loading overlay on link clicks for SPA-like experience
  document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', (e) => {
      // Only show overlay for same-origin navigations
      if (link.hostname === window.location.hostname) {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
          overlay.classList.add('loading-visible');
          overlay.classList.remove('fade-out');
        }
      }
    });
  });
</script>
</body>
</html>
