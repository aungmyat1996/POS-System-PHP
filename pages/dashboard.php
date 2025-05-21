<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_role = $user['role'] ?? 'cashier';

// Currency handling
$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Handle profile updates for cashier/admin
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username && $email) {
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$username, $email, $hashed_password, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$username, $email, $user_id]);
        }
        $message = '<div class="alert alert-success">Profile updated successfully!</div>';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}

// Admin-specific data
if ($user_role === 'admin') {
    // Total sales
    $stmt = $pdo->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'completed'");
    $total_sales = $stmt->fetch()['total_sales'] ?? 0;

    // Recent orders (last 5)
    $stmt = $pdo->query("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    // Low stock products
    $stmt = $pdo->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.stock_quantity <= 5");
    $low_stock_products = $stmt->fetchAll();

    // Top selling products
    $stmt = $pdo->query("SELECT p.product_name, SUM(oi.quantity) as total_sold FROM order_items oi JOIN products p ON oi.product_id = p.product_id GROUP BY p.product_id ORDER BY total_sold DESC LIMIT 5");
    $top_selling = $stmt->fetchAll();

    // Low selling products
    $stmt = $pdo->query("SELECT p.product_name, COALESCE(SUM(oi.quantity), 0) as total_sold FROM products p LEFT JOIN order_items oi ON p.product_id = oi.product_id GROUP BY p.product_id ORDER BY total_sold ASC LIMIT 5");
    $low_selling = $stmt->fetchAll();

    // Sales by cashier
    $stmt = $pdo->query("SELECT u.username, COUNT(o.order_id) as order_count, SUM(o.total_amount) as total_amount FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.status = 'completed' GROUP BY u.user_id");
    $sales_by_cashier = $stmt->fetchAll();

    // Sales data for graph (last 7 days)
    $sales_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT SUM(total_amount) as daily_sales FROM orders WHERE DATE(order_date) = ? AND status = 'completed'");
        $stmt->execute([$date]);
        $sales_data[$date] = $stmt->fetch()['daily_sales'] ?? 0;
    }

    // Monthly sales comparison
    $month = date('m');
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as monthly_sales FROM orders WHERE MONTH(order_date) = ? AND YEAR(order_date) = ? AND status = 'completed'");
    $stmt->execute([$month, $year]);
    $current_month_sales = $stmt->fetch()['monthly_sales'] ?? 0;

    $prev_month = date('m', strtotime('-1 month'));
    $prev_year = date('Y', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as prev_monthly_sales FROM orders WHERE MONTH(order_date) = ? AND YEAR(order_date) = ? AND status = 'completed'");
    $stmt->execute([$prev_month, $prev_year]);
    $prev_month_sales = $stmt->fetch()['prev_monthly_sales'] ?? 0;

    // Fetch all cashiers
    $stmt = $pdo->query("SELECT user_id, username, email FROM users WHERE role = 'cashier'");
    $cashiers = $stmt->fetchAll();

    // Handle cashier password reset
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $cashier_id = $_POST['cashier_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        if ($cashier_id && $new_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $cashier_id]);
            $message = '<div class="alert alert-success">Cashier password reset successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Invalid input!</div>';
        }
    }

    // Supplier analysis
    $top_supplier = ['supplier_name' => 'N/A', 'product_count' => 0];
    $total_suppliers = 0;
    $stmt = $pdo->query("SHOW TABLES LIKE 'suppliers'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SELECT s.supplier_name, COUNT(p.product_id) as product_count 
                             FROM suppliers s 
                             LEFT JOIN products p ON s.supplier_id = p.supplier_id 
                             GROUP BY s.supplier_id 
                             ORDER BY product_count DESC LIMIT 1");
        $top_supplier = $stmt->fetch() ?: $top_supplier;

        $stmt = $pdo->query("SELECT COUNT(*) as total_suppliers FROM suppliers");
        $total_suppliers = $stmt->fetch()['total_suppliers'] ?? 0;
    }

    // Most popular product
    $stmt = $pdo->query("SELECT p.product_name, SUM(oi.quantity) as total_sold 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.product_id 
                         GROUP BY p.product_id 
                         ORDER BY total_sold DESC LIMIT 1");
    $most_popular_product = $stmt->fetch();

    // Fetch all products for viewing
    $stmt = $pdo->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id");
    $all_products = $stmt->fetchAll();

    // Fetch sold items
    $stmt = $pdo->query("SELECT p.product_name, SUM(oi.quantity) as total_sold, o.order_date 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.product_id 
                         JOIN orders o ON oi.order_id = o.order_id 
                         WHERE o.status = 'completed' 
                         GROUP BY p.product_id, o.order_date");
    $sold_items = $stmt->fetchAll();

    // Export to CSV for Admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_sales'])) {
        $stmt = $pdo->query("SELECT o.order_id, o.customer_name, o.total_amount, o.order_date, u.username 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.user_id 
                             WHERE o.status = 'completed'");
        $orders = $stmt->fetchAll();

        $filename = "sales_report_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Customer Name', 'Total Amount', 'Order Date', 'Cashier']);
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['customer_name'] ?? 'N/A',
                $order['total_amount'],
                $order['order_date'],
                $order['username']
            ]);
        }
        fclose($output);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_low_stock'])) {
        $filename = "low_stock_report_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Product Name', 'Category', 'Stock Quantity']);
        foreach ($low_stock_products as $product) {
            fputcsv($output, [
                $product['product_name'],
                $product['category_name'] ?? 'N/A',
                $product['stock_quantity']
            ]);
        }
        fclose($output);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_cashier_performance'])) {
        $filename = "cashier_performance_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Cashier Username', 'Order Count', 'Total Amount']);
        foreach ($sales_by_cashier as $cashier) {
            fputcsv($output, [
                $cashier['username'],
                $cashier['order_count'],
                $cashier['total_amount']
            ]);
        }
        fclose($output);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_sold_items'])) {
        $filename = "sold_items_report_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Product Name', 'Total Sold', 'Last Sold Date']);
        foreach ($sold_items as $item) {
            fputcsv($output, [
                $item['product_name'],
                $item['total_sold'],
                $item['order_date']
            ]);
        }
        fclose($output);
        exit();
    }
}

// Low stock alert (trigger once)
$low_stock_alert = '';
if ($user_role === 'admin' || $user_role === 'cashier') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 2");
    $low_stock_count = $stmt->fetchColumn();
    if ($low_stock_count > 0 && !isset($_SESSION['low_stock_alert_shown'])) {
        $low_stock_alert = "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
            $low_stock_count product(s) with low stock (≤ 2 units)!
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close' onclick='sessionStorage.setItem(\"low_stock_alert_shown\", \"true\")'></button>
        </div>";
        $_SESSION['low_stock_alert_shown'] = true;
    }
}

// Monthly summary
$month = date('m');
$year = date('Y');
$stmt = $pdo->prepare("SELECT SUM(total_amount) as monthly_sales FROM orders WHERE MONTH(order_date) = ? AND YEAR(order_date) = ? AND status = 'completed'");
$stmt->execute([$month, $year]);
$monthly_sales = $stmt->fetch()['monthly_sales'] ?? 0;

$prev_month = date('m', strtotime('-1 month'));
$prev_year = date('Y', strtotime('-1 month'));
$stmt = $pdo->prepare("SELECT SUM(total_amount) as prev_monthly_sales FROM orders WHERE MONTH(order_date) = ? AND YEAR(order_date) = ? AND status = 'completed'");
$stmt->execute([$prev_month, $prev_year]);
$prev_monthly_sales = $stmt->fetch()['prev_monthly_sales'] ?? 0;

// Yearly summary
$stmt = $pdo->prepare("SELECT SUM(total_amount) as yearly_sales FROM orders WHERE YEAR(order_date) = ? AND status = 'completed'");
$stmt->execute([$year]);
$yearly_sales = $stmt->fetch()['yearly_sales'] ?? 0;

$prev_year = date('Y', strtotime('-1 year'));
$stmt = $pdo->prepare("SELECT SUM(total_amount) as prev_yearly_sales FROM orders WHERE YEAR(order_date) = ? AND status = 'completed'");
$stmt->execute([$prev_year]);
$prev_yearly_sales = $stmt->fetch()['prev_yearly_sales'] ?? 0;

// Recent orders for cashier
if ($user_role === 'cashier') {
    $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE o.user_id = ? ORDER BY o.order_date DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();

    // Low stock products for cashier
    $stmt = $pdo->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.stock_quantity <= 2");
    $low_stock_products = $stmt->fetchAll();

    // All products for cashier to view
    $stmt = $pdo->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id");
    $all_products = $stmt->fetchAll();

    // Sold items for cashier
    $stmt = $pdo->query("SELECT p.product_name, SUM(oi.quantity) as total_sold, o.order_date 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.product_id 
                         JOIN orders o ON oi.order_id = o.order_id 
                         WHERE o.status = 'completed' AND o.user_id = ? 
                         GROUP BY p.product_id, o.order_date", [$user_id]);
    $sold_items = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laptops Store POS - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="../public/js/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .navbar {
            background: linear-gradient(90deg, #1e3a8a, #3b82f6);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .nav-link:hover {
            color: #dbeafe !important;
        }
        .container {
            padding-top: 5rem;
        }
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: white;
            margin-bottom: 20px;
            padding: 20px;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .card-title {
            color: #1e3a8a;
            font-weight: 600;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .card-text, p {
            font-size: 1.1rem;
            color: #4b5563;
        }
        .btn-primary {
            background: linear-gradient(90deg, #3b82f6, #1e3a8a);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #1e3a8a, #3b82f6);
        }
        .btn-success {
            background: linear-gradient(90deg, #22c55e, #15803d);
            border: none;
            border-radius: 8px;
        }
        .btn-success:hover {
            background: linear-gradient(90deg, #15803d, #22c55e);
        }
        .btn-warning {
            background: linear-gradient(90deg, #facc15, #ca8a04);
            border: none;
            border-radius: 8px;
        }
        .btn-warning:hover {
            background: linear-gradient(90deg, #ca8a04, #facc15);
        }
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background: #1e3a8a;
            color: white;
        }
        .table tbody tr:hover {
            background: #f1f5f9;
        }
        .list-group-item {
            border: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 0;
            color: #4b5563;
        }
        .alert {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
        }
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            background: #1e3a8a;
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
    </style>
</head>
<body>
    <?php
    $navbar_path = '../includes/navbar.php';
    $absolute_path = realpath($navbar_path);
    if ($absolute_path === false || !file_exists($navbar_path)) {
        echo "<!-- Debug: navbar.php not found. Expected path: $navbar_path, Resolved path: " . ($absolute_path ?: 'Not resolved') . " -->";
        echo '<nav class="navbar navbar-expand-lg"><div class="container-fluid"><a class="navbar-brand" href="http://localhost/pos_system/pages/home.php">Laptops Store POS (Fallback)</a></div></nav>';
    } else {
        echo "<!-- Debug: Including navbar.php from $navbar_path (Resolved: $absolute_path) -->";
        include $navbar_path;
    }
    ?>

    <div class="container mt-5 pt-5">
        <?php echo $low_stock_alert; ?>
        <?php echo $message; ?>

        <?php if ($user_role === 'cashier'): ?>
            <h3 class="mb-4 text-center" style="color: #1e3a8a;">Cashier Dashboard</h3>

            <!-- My Profile -->
            <div class="row">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">My Profile</h5>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password (Leave blank to keep current)</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password">
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Low Stock Alerts (≤ 2 Units)</h5>
                        <ul class="list-group">
                            <?php foreach ($low_stock_products as $product): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($product['product_name']); ?> (Stock: <?php echo $product['stock_quantity']; ?>)</li>
                            <?php endforeach; ?>
                            <?php if (empty($low_stock_products)): ?>
                                <li class="list-group-item">No low stock products (≤ 2 units).</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- All Products -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5 class="card-title">All Products</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Stock Quantity</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                        <td><?php echo format_currency($product['price'], $currency, $exchange_rates); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_products)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No products available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sold Items -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5 class="card-title">Sold Items</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Total Sold</th>
                                    <th>Last Sold Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sold_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['total_sold']); ?></td>
                                        <td><?php echo htmlspecialchars($item['order_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sold_items)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No sold items yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5 class="card-title">Recent Orders</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo format_currency($order['total_amount'], $currency, $exchange_rates); ?></td>
                                        <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent orders.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($user_role === 'admin'): ?>
            <h3 class="mb-4 text-center" style="color: #1e3a8a;">Admin Dashboard - Laptops Store</h3>

            <!-- Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h5 class="card-title">Total Sales</h5>
                        <p class="card-text"><?php echo format_currency($total_sales, $currency, $exchange_rates); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h5 class="card-title">Current Month Sales</h5>
                        <p class="card-text"><?php echo format_currency($current_month_sales, $currency, $exchange_rates); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h5 class="card-title">Most Popular Product</h5>
                        <p><strong>Product:</strong> <?php echo htmlspecialchars($most_popular_product['product_name'] ?? 'N/A'); ?></p>
                        <p><strong>Total Sold:</strong> <?php echo $most_popular_product['total_sold'] ?? 0; ?> units</p>
                    </div>
                </div>
            </div>

            <!-- Supplier and Sales Charts -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Supplier Analysis</h5>
                        <p><strong>Total Suppliers:</strong> <?php echo $total_suppliers; ?></p>
                        <p><strong>Top Supplier:</strong> <?php echo htmlspecialchars($top_supplier['supplier_name']); ?> (<?php echo $top_supplier['product_count']; ?> products)</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Sales (Last 7 Days)</h5>
                        <canvas id="salesChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- All Products and Monthly Sales Comparison -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">All Products</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Stock Quantity</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                        <td><?php echo format_currency($product['price'], $currency, $exchange_rates); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_products)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No products available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Monthly Sales Comparison</h5>
                        <canvas id="monthlySalesChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sold Items and Low Stock -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Sold Items</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Total Sold</th>
                                    <th>Last Sold Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sold_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['total_sold']); ?></td>
                                        <td><?php echo htmlspecialchars($item['order_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sold_items)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No sold items yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <form method="POST" class="mt-3">
                            <button type="submit" name="export_sold_items" class="btn btn-success">Export to Excel</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Low Stock Alerts (≤ 5 Units)</h5>
                        <canvas id="lowStockChart" height="150"></canvas>
                        <form method="POST" class="mt-3">
                            <button type="submit" name="export_low_stock" class="btn btn-success">Export to Excel</button>
                        </form>
                        <a href="#low-stock-details" class="btn btn-link mt-2">View Details</a>
                        <div id="low-stock-details" class="mt-3">
                            <h6>Low Stock Products</h6>
                            <ul class="list-group">
                                <?php foreach ($low_stock_products as $product): ?>
                                    <li class="list-group-item"><?php echo htmlspecialchars($product['product_name']); ?> (Stock: <?php echo $product['stock_quantity']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                            <h6 class="mt-3">Least Sold Products</h6>
                            <ul class="list-group">
                                <?php foreach ($low_selling as $product): ?>
                                    <li class="list-group-item"><?php echo htmlspecialchars($product['product_name']); ?> (Sold: <?php echo $product['total_sold']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales by Cashier and Financial Summaries -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Sales by Cashier</h5>
                        <canvas id="salesByCashierChart" height="150"></canvas>
                        <form method="POST" class="mt-3">
                            <button type="submit" name="export_cashier_performance" class="btn btn-success">Export to Excel</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Financial Summaries</h5>
                        <h6>Monthly Summary</h6>
                        <p>Current Month (<?php echo date('F Y'); ?>): <?php echo format_currency($monthly_sales, $currency, $exchange_rates); ?></p>
                        <p>Previous Month (<?php echo date('F Y', strtotime('-1 month')); ?>): <?php echo format_currency($prev_monthly_sales, $currency, $exchange_rates); ?></p>
                        <h6>Yearly Summary</h6>
                        <p>Current Year (<?php echo date('Y'); ?>): <?php echo format_currency($yearly_sales, $currency, $exchange_rates); ?></p>
                        <p>Previous Year (<?php echo date('Y', strtotime('-1 year')); ?>): <?php echo format_currency($prev_yearly_sales, $currency, $exchange_rates); ?></p>
                        <form method="POST" action="generate_summary.php" class="mt-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <select name="period" class="form-control">
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="month" class="form-control" placeholder="Month (1-12)" value="<?php echo date('m'); ?>" min="1" max="12">
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="year" class="form-control" placeholder="Year" value="<?php echo date('Y'); ?>" min="2000" max="2100">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3 w-100">Generate Summary</button>
                        </form>
                        <form method="POST" class="mt-3">
                            <button type="submit" name="export_sales" class="btn btn-success w-100">Export Sales to Excel</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Orders and Cashier Management -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Recent Orders</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo format_currency($order['total_amount'], $currency, $exchange_rates); ?></td>
                                        <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent orders.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="card-title">Cashier Management</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashiers as $cashier): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cashier['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['username']); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['email']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-cashier-id="<?php echo $cashier['user_id']; ?>">Reset Password</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($cashiers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No cashiers found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reset Password Modal -->
            <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="resetPasswordModalLabel">Reset Cashier Password</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="cashier_id" id="reset_cashier_id">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($user_role === 'admin'): ?>
            // Sales Chart (Last 7 Days)
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo "'" . implode("','", array_keys($sales_data)) . "'"; ?>],
                    datasets: [{
                        label: 'Sales (<?php echo $currency; ?>)',
                        data: [<?php echo implode(',', array_values($sales_data)); ?>],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });

            // Top Selling Products Chart
            const topSellingCtx = document.getElementById('topSellingChart').getContext('2d');
            new Chart(topSellingCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($top_selling, 'product_name')) . "'"; ?>],
                    datasets: [{
                        label: 'Units Sold',
                        data: [<?php echo implode(',', array_column($top_selling, 'total_sold')); ?>],
                        backgroundColor: '#22c55e'
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });

            // Monthly Sales Comparison Chart
            const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
            new Chart(monthlySalesCtx, {
                type: 'bar',
                data: {
                    labels: ['Previous Month', 'Current Month'],
                    datasets: [{
                        label: 'Sales (<?php echo $currency; ?>)',
                        data: [<?php echo $prev_month_sales; ?>, <?php echo $current_month_sales; ?>],
                        backgroundColor: ['#facc15', '#3b82f6']
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });

            // Low Stock Chart
            const lowStockCtx = document.getElementById('lowStockChart').getContext('2d');
            new Chart(lowStockCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($low_stock_products, 'product_name')) . "'"; ?>],
                    datasets: [{
                        label: 'Stock Levels',
                        data: [<?php echo implode(',', array_column($low_stock_products, 'stock_quantity')); ?>],
                        backgroundColor: ['#dc3545', '#facc15', '#17a2b8', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Sales by Cashier Chart
            const salesByCashierCtx = document.getElementById('salesByCashierChart').getContext('2d');
            new Chart(salesByCashierCtx, {
                type: 'pie',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($sales_by_cashier, 'username')) . "'"; ?>],
                    datasets: [{
                        label: 'Total Sales (<?php echo $currency; ?>)',
                        data: [<?php echo implode(',', array_column($sales_by_cashier, 'total_amount')); ?>],
                        backgroundColor: ['#3b82f6', '#22c55e', '#facc15', '#dc3545', '#17a2b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Modal data population
            document.querySelectorAll('#resetPasswordModal').forEach(modalTrigger => {
                modalTrigger.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const cashierId = button.getAttribute('data-cashier-id');
                    const modal = document.getElementById('resetPasswordModal');
                    modal.querySelector('#reset_cashier_id').value = cashierId;
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>