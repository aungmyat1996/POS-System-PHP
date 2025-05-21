<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Currency handling
$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Fetch order history
$stmt = $pdo->query("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC");
$history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php
    // Debug: Check if navbar.php exists
    $navbar_path = '../includes/navbar.php';
    $absolute_path = realpath($navbar_path);
    if ($absolute_path === false || !file_exists($navbar_path)) {
        echo "<!-- Debug: navbar.php not found. Expected path: $navbar_path, Resolved path: " . ($absolute_path ?: 'Not resolved') . " -->";
        echo '<nav class="navbar navbar-light bg-light"><a class="navbar-brand" href="http://localhost/pos_system/pages/home.php">BitsTech POS (Fallback)</a></nav>';
    } else {
        echo "<!-- Debug: Including navbar.php from $navbar_path (Resolved: $absolute_path) -->";
        include $navbar_path;
    }
    ?>

    <div class="container mt-5 pt-5">
        <h3 class="mb-3">Transaction History</h3>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Name</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['order_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($record['customer_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo format_currency($record['total_amount'] ?? 0, $currency, $exchange_rates); ?></td>
                        <td><?php echo $record['order_date'] ? date('Y-m-d H:i:s', strtotime($record['order_date'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($record['status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($record['username'] ?? 'Unknown'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>