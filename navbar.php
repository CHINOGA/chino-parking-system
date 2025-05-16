<?php
// Navigation bar component
?>
<style>
/* Navbar styles */
.navbar {
    background-color: #0056b3;
    color: white;
    padding: 12px 24px;
    position: sticky;
    top: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.navbar .brand {
    font-weight: 700;
    font-size: 1.4em;
    letter-spacing: 1px;
}

.navbar .nav-links {
    display: flex;
    gap: 20px;
}

.navbar .nav-links a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.navbar .nav-links a:hover,
.navbar .nav-links a:focus {
    background-color: rgba(255, 255, 255, 0.2);
    outline: none;
}

.hamburger {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 5px;
}

.hamburger div {
    width: 28px;
    height: 3px;
    background-color: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

/* Responsive */
@media (max-width: 600px) {
    .navbar .nav-links {
        display: none;
        flex-direction: column;
        background-color: #0056b3;
        position: absolute;
        top: 56px;
        right: 0;
        width: 180px;
        border-radius: 0 0 0 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        padding: 10px 0;
    }
    .navbar .nav-links.active {
        display: flex;
    }
    .hamburger {
        display: flex;
    }
}
</style>

<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="brand" tabindex="0">Chino Parking System</div>
    <div class="nav-links" id="navLinks" role="menu">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="vehicle_entry.php" role="menuitem" tabindex="0">Vehicle Entry</a>
            <!-- Removed Vehicle Exit link as requested -->
            <a href="reporting.php" role="menuitem" tabindex="0">Reporting</a>
            <a href="revenue_report.php" role="menuitem" tabindex="0">Revenue Report</a>
            <a href="logout.php" role="menuitem" tabindex="0">Logout</a>
        <?php endif; ?>
    </div>
    <div class="hamburger" id="hamburger" aria-label="Toggle navigation menu" role="button" tabindex="0" aria-expanded="false" aria-controls="navLinks">
        <div></div>
        <div></div>
        <div></div>
    </div>
</nav>

<script>
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');

function closeMenu() {
    navLinks.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
}

function toggleMenu() {
    const isActive = navLinks.classList.toggle('active');
    hamburger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
}

hamburger.addEventListener('click', toggleMenu);

// Close menu when clicking outside
document.addEventListener('click', (event) => {
    if (!navLinks.contains(event.target) && !hamburger.contains(event.target)) {
        closeMenu();
    }
});

// Close menu when clicking a nav link
navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        closeMenu();
    });
});

// Keyboard accessibility for hamburger
hamburger.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleMenu();
    }
});
</script>
