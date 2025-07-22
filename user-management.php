<?php
require_once 'auth.php';
require_role([1]); // Admin only

require_once 'config.php';

$error = '';
$success = '';

// Handle form submissions for add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 0);

        if ($username === '' || $email === '' || $phone_number === '' || $password === '' || $role_id === 0) {
            $error = 'Please fill in all fields.';
        } else {
            // Check if username exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, email, phone_number, password_hash, role_id) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$username, $email, $phone_number, $password_hash, $role_id]);
                $success = 'User added successfully.';
            }
        }
    } elseif ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id === 0) {
            $error = 'Invalid user ID.';
        } else {
            // Prevent deleting self
            if ($user_id === $_SESSION['user_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $success = 'User deleted successfully.';
            }
        }
    } elseif ($action === 'edit') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($user_id === 0 || $role_id === 0 || $username === '' || $email === '' || $phone_number === '') {
            $error = 'Invalid input.';
        } else {
            // Check if username is taken by another user
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone_number = ?, role_id = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$username, $email, $phone_number, $role_id, $password_hash, $user_id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone_number = ?, role_id = ? WHERE id = ?');
                    $stmt->execute([$username, $email, $phone_number, $role_id, $user_id]);
                }
                $success = 'User updated successfully.';
            }
        }
    }
}

// Fetch users and roles
$stmt = $pdo->query('SELECT u.id, u.username, u.email, u.phone_number, u.role_id, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.username');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT id, role_name FROM roles ORDER BY id');
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>User Management - Chino Parking System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="custom.css" rel="stylesheet" />
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h2>User Management</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <h4>Add New User</h4>
    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="add" />
        <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" name="username" id="username" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" name="email" id="email" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone Number:</label>
            <input type="text" name="phone_number" id="phone_number" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" name="password" id="password" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="role_id" class="form-label">Role:</label>
            <select name="role_id" id="role_id" class="form-select" required>
                <option value="">Select Role</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Add User</button>
    </form>

    <h4>Existing Users</h4>
    <div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Phone Number</th>
                <th>Role</th>
                <th>Change Role</th>
                <th>Reset Password</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <form method="post" class="d-flex align-items-center flex-wrap gap-2">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                    <input type="hidden" name="action" value="edit" />
            <td>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required />
            </td>
            <td>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required />
            </td>
            <td>
                <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" required />
            </td>
            <td><?= htmlspecialchars($user['role_name']) ?></td>
            <td>
                <select name="role_id" class="form-select" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>" <?= $role['id'] == $user['role_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="password" name="password" class="form-control" placeholder="New password (optional)" />
            </td>
            <td>
                <button type="submit" class="btn btn-sm btn-success">Update</button>
        </form>
        <form method="post" style="display:inline;">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
            <input type="hidden" name="action" value="delete" />
            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">Delete</button>
        </form>
            </td>
        </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>
