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

// Filtering parameters
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$query = "SELECT o.*, u.username 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.user_id 
          WHERE 1=1";
$params = [];

if ($date_filter) {
    $query .= " AND DATE(o.order_date) = ?";
    $params[] = $date_filter;
}
if ($status_filter) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY o.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Fetch distinct statuses for filter dropdown
$status_stmt = $pdo->query("SELECT DISTINCT status FROM orders");
$statuses = $status_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Order List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php
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
        <h3 class="mb-3">Order List</h3>
        
        <!-- Filter Form -->
        <form method="GET" class="mb-4">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="order_list.php" class="btn btn-secondary w-100">Clear Filters</a>
                </div>
            </div>
        </form>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Name</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo format_currency($order['total_amount'] ?? 0, $currency, $exchange_rates); ?></td>
                        <td><?php echo $order['order_date'] ? date('Y-m-d H:i:s', strtotime($order['order_date'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($order['status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['username'] ?? 'Unknown'); ?></td>
                        <td>
                            <a href="invoice.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>