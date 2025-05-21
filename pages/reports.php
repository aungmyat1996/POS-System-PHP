<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: home.php");
    exit();
}

// Currency handling
$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Total sales
$stmt = $pdo->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'completed'");
$total_sales = $stmt->fetch()['total_sales'] ?? 0;

// Low stock products
$stmt = $pdo->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.stock_quantity <= 5");
$low_stock_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Reports</title>
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
        <h3 class="mb-3">Reports</h3>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Total Sales</h5>
                <p class="card-text"><?php echo format_currency($total_sales, $currency, $exchange_rates); ?></p>
            </div>
        </div>

        <h5 class="mb-3">Low Stock Products</h5>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Stock Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_name'] ?? 'Unnamed Product'); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo $product['stock_quantity'] ?? 0; ?></td>
                        <td><?php echo format_currency($product['price'] ?? 0, $currency, $exchange_rates); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>