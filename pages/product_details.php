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

$product_id = $_GET['id'] ?? null;
$product = null;
if ($product_id) {
    $stmt = $pdo->prepare("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .product-image { width: 300px; height: 300px; object-fit: cover; }
    </style>
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

    <div class="container mt-5 pt-3">
        <?php if ($product): ?>
            <h3 class="mb-3">Product Details</h3>
            <div class="row">
                <div class="col-md-4">
                    <img src="../public/images/<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>" class="product-image img-thumbnail" alt="<?php echo htmlspecialchars($product['product_name'] ?? 'Product'); ?>">
                </div>
                <div class="col-md-8">
                    <h4><?php echo htmlspecialchars($product['product_name'] ?? 'Unnamed Product'); ?></h4>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description'] ?? 'No description'); ?></p>
                    <p><strong>Price:</strong> <?php echo format_currency($product['price'] ?? 0, $currency, $exchange_rates); ?></p>
                    <p><strong>Stock:</strong> <?php echo $product['stock_quantity'] ?? 0; ?></p>
                    <p><strong>Barcode:</strong> <?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></p>
                    <p><strong>Added On:</strong> <?php echo $product['created_at'] ? date('Y-m-d H:i:s', strtotime($product['created_at'])) : 'N/A'; ?></p>
                    <a href="home.php" class="btn btn-primary mt-3">Back to Home</a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Product not found!</div>
            <a href="home.php" class="btn btn-primary">Back to Home</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>