<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: vehicle_entry.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Chino Parking System - Login</title>
<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
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

  // PWA install prompt handling
  let deferredPrompt;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
      installBtn.style.display = 'block';
    }
  });

  function installPWA() {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          console.log('User accepted the install prompt');
          alert('App installed successfully!');
        } else {
          console.log('User dismissed the install prompt');
          alert('App installation dismissed.');
        }
        deferredPrompt = null;
        const installBtn = document.getElementById('installBtn');
        if (installBtn) {
          installBtn.style.display = 'none';
        }
      });
    }
  }

  // Listen for appinstalled event
  window.addEventListener('appinstalled', (evt) => {
    console.log('PWA was installed');
    alert('Thank you for installing the app!');
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
      installBtn.style.display = 'none';
    }
  });
</script>
<style>
/* Custom styles for login page */
body {
  @apply bg-gradient-to-r from-blue-600 to-indigo-700 font-sans text-white m-0;
}
.topbar {
  @apply bg-blue-800 text-white font-bold text-xl text-center py-4 shadow-md sticky top-0 z-50;
}
.container {
  @apply max-w-sm mx-auto mt-20 bg-white bg-opacity-10 backdrop-blur-md rounded-lg p-8 shadow-lg;
}
h2 {
  @apply text-center text-3xl font-extrabold mb-6;
}
.error {
  @apply text-red-400 mb-4;
}
form label {
  @apply block mb-1 font-semibold;
}
input[type="text"],
input[type="password"] {
  @apply w-full p-3 rounded-md border border-gray-300 bg-white bg-opacity-90 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400 mb-4;
}
button {
  @apply w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 rounded-md transition duration-300;
}
</style>
</head>
<body>
<div class="topbar">Chino Parking System</div>
<div class="container">
    <h2>Login</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" novalidate>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus />
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required />
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
