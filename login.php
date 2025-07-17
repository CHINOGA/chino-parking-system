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

        // Remove password hash verification for now
        if ($user) {
            // Directly check if password matches the plain text password stored in password_hash column
            if ($password === $user['password_hash']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                header('Location: vehicle-entry.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
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
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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
  background: linear-gradient(to right, #2563eb, #4f46e5);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: white;
  margin: 0;
  padding: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.topbar {
  background-color: #1e40af;
  color: white;
  font-weight: 700;
  font-size: 1.25rem;
  text-align: center;
  padding: 1rem 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  position: sticky;
  top: 0;
  z-index: 1050;
}
.container {
  max-width: 400px;
  margin: 4rem auto 2rem;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border-radius: 0.5rem;
  padding: 2rem;
  box-shadow: 0 0 15px rgba(0,0,0,0.2);
}
h2 {
  text-align: center;
  font-weight: 700;
  font-size: 2rem;
  margin-bottom: 1.5rem;
}
.error {
  color: #f87171;
  margin-bottom: 1rem;
  font-weight: 600;
}
form label {
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 600;
}
input[type="text"],
input[type="password"] {
  width: 100%;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid #d1d5db;
  background-color: rgba(255, 255, 255, 0.9);
  color: #111827;
  margin-bottom: 1rem;
  font-size: 1rem;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
input[type="text"]:focus,
input[type="password"]:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}
button {
  width: 100%;
  background-color: #2563eb;
  color: white;
  font-weight: 700;
  padding: 0.75rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  font-size: 1.125rem;
  transition: background-color 0.3s ease;
}
button:hover {
  background-color: #1e40af;
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
