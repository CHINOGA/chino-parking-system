<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tracking_id = $_GET['tracking_id'] ?? 'N/A';
$merchant_ref = $_GET['merchant_ref'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto mt-10 p-5">
        <div class="bg-white shadow-md rounded-lg p-8 max-w-md mx-auto">
            <h2 class="text-2xl font-bold text-center text-green-600 mb-4">Payment Successful!</h2>
            <p class="text-center text-gray-700">Thank you for your payment. Your transaction has been processed.</p>
            <div class="mt-6 text-sm text-gray-600">
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($tracking_id); ?></p>
                <p><strong>Merchant Reference:</strong> <?php echo htmlspecialchars($merchant_ref); ?></p>
            </div>
            <div class="mt-8 text-center">
                <a href="parked-vehicles.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
