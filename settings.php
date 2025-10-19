<?php
require_once 'auth.php';
require_role([1]); // Admin only

require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_config') {
        $provider = trim($_POST['provider'] ?? '');
        $config_key = trim($_POST['config_key'] ?? '');
        $config_value = trim($_POST['config_value'] ?? '');

        if ($provider === '' || $config_key === '') {
            $error = 'Provider and config key are required.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO api_configs (provider, config_key, config_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE config_value = ?');
                $stmt->execute([$provider, $config_key, $config_value, $config_value]);
                $success = 'Configuration updated successfully.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'set_active_provider') {
        $provider = trim($_POST['active_provider'] ?? '');

        if ($provider === '') {
            $error = 'Provider is required.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO active_sms_provider (provider) VALUES (?) ON DUPLICATE KEY UPDATE provider = ?');
                $stmt->execute([$provider, $provider]);
                $success = 'Active SMS provider updated successfully.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current configs
$stmt = $pdo->query('SELECT provider, config_key, config_value FROM api_configs ORDER BY provider, config_key');
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group configs by provider
$grouped_configs = [];
foreach ($configs as $config) {
    $grouped_configs[$config['provider']][$config['config_key']] = $config['config_value'];
}

// Fetch active provider
$stmt = $pdo->query('SELECT provider FROM active_sms_provider LIMIT 1');
$active_provider = $stmt->fetch(PDO::FETCH_ASSOC)['provider'] ?? 'nextsms';

// Define available providers
$providers = ['nextsms', 'mobishastra'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>API Settings - Chino Parking System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="custom.css" rel="stylesheet" />
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h2>API Configuration Management</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <h4>Active SMS Provider</h4>
    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="set_active_provider" />
        <div class="mb-3">
            <label for="active_provider" class="form-label">Select Active SMS Provider:</label>
            <select name="active_provider" id="active_provider" class="form-select" required>
                <?php foreach ($providers as $prov): ?>
                    <option value="<?= $prov ?>" <?= $prov === $active_provider ? 'selected' : '' ?>>
                        <?= ucfirst($prov) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Set Active Provider</button>
    </form>

    <h4>API Configurations</h4>
    <?php foreach ($providers as $provider): ?>
        <h5><?= ucfirst($provider) ?> Configurations</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Config Key</th>
                        <th>Config Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $provider_configs = $grouped_configs[$provider] ?? [];
                    $default_configs = [
                        'nextsms' => ['username' => '', 'password' => '', 'sender_id' => '', 'api_url' => 'https://messaging-service.co.tz/api/sms/v1/text/single'],
                        'mobishastra' => ['user' => '', 'pwd' => '', 'senderid' => '', 'api_url' => 'http://mshastra.com/sendsms_api_json.aspx']
                    ];
                    $configs_to_show = array_merge($default_configs[$provider], $provider_configs);
                    foreach ($configs_to_show as $key => $value): ?>
                    <tr>
                        <form method="post">
                            <input type="hidden" name="action" value="update_config" />
                            <input type="hidden" name="provider" value="<?= $provider ?>" />
                            <input type="hidden" name="config_key" value="<?= $key ?>" />
                            <td><?= htmlspecialchars($key) ?></td>
                            <td>
                                <input type="text" name="config_value" class="form-control" value="<?= htmlspecialchars($value) ?>" required />
                            </td>
                            <td>
                                <button type="submit" class="btn btn-sm btn-success">Update</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
