<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Chino Parking System</title>
<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white font-sans">
<nav class="sticky top-0 z-50 bg-blue-800 bg-opacity-90 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <div class="flex-shrink-0 cursor-pointer font-extrabold text-xl tracking-wide" tabindex="0" onclick="window.location.href='vehicle_entry.php'">
        Chino Parking System
      </div>
      <div class="hidden md:flex space-x-8" id="navLinks" role="menu">
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="vehicle_entry.php" role="menuitem" tabindex="0" class="hover:bg-blue-700 px-3 py-2 rounded-md font-semibold transition duration-300">Vehicle Entry</a>
          <a href="reporting.php" role="menuitem" tabindex="0" class="hover:bg-blue-700 px-3 py-2 rounded-md font-semibold transition duration-300">Reporting</a>
          <a href="revenue_report.php" role="menuitem" tabindex="0" class="hover:bg-blue-700 px-3 py-2 rounded-md font-semibold transition duration-300">Revenue Report</a>
          <a href="sms_send.php" role="menuitem" tabindex="0" class="hover:bg-blue-700 px-3 py-2 rounded-md font-semibold transition duration-300">Send SMS</a>
          <a href="logout.php" role="menuitem" tabindex="0" class="hover:bg-blue-700 px-3 py-2 rounded-md font-semibold transition duration-300">Logout</a>
        <?php endif; ?>
      </div>
      <div class="md:hidden flex items-center">
        <button id="hamburger" aria-label="Toggle navigation menu" role="button" tabindex="0" aria-expanded="false" aria-controls="navLinks" class="focus:outline-none">
          <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
  <div class="md:hidden bg-blue-800 bg-opacity-90" id="mobileMenu" style="display:none;">
    <div class="px-2 pt-2 pb-3 space-y-1">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="vehicle_entry.php" role="menuitem" tabindex="0" class="block px-3 py-2 rounded-md font-semibold hover:bg-blue-700 transition duration-300">Vehicle Entry</a>
        <a href="reporting.php" role="menuitem" tabindex="0" class="block px-3 py-2 rounded-md font-semibold hover:bg-blue-700 transition duration-300">Reporting</a>
        <a href="revenue_report.php" role="menuitem" tabindex="0" class="block px-3 py-2 rounded-md font-semibold hover:bg-blue-700 transition duration-300">Revenue Report</a>
        <a href="sms_send.php" role="menuitem" tabindex="0" class="block px-3 py-2 rounded-md font-semibold hover:bg-blue-700 transition duration-300">Send SMS</a>
        <a href="logout.php" role="menuitem" tabindex="0" class="block px-3 py-2 rounded-md font-semibold hover:bg-blue-700 transition duration-300">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');

function toggleMenu() {
  const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
  hamburger.setAttribute('aria-expanded', !isExpanded);
  if (mobileMenu.style.display === 'none' || mobileMenu.style.display === '') {
    mobileMenu.style.display = 'block';
  } else {
    mobileMenu.style.display = 'none';
  }
}

hamburger.addEventListener('click', toggleMenu);

// Close menu when clicking outside
document.addEventListener('click', (event) => {
  if (!mobileMenu.contains(event.target) && !hamburger.contains(event.target)) {
    mobileMenu.style.display = 'none';
    hamburger.setAttribute('aria-expanded', 'false');
  }
});

// Keyboard accessibility for hamburger
hamburger.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    toggleMenu();
  }
});
</script>
</body>
</html>
