<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Currency handling
$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';
if (isset($_POST['currency']) && in_array($_POST['currency'], ['USD', 'THB', 'MMK'])) {
    $_SESSION['currency'] = $_POST['currency'];
    $currency = $_POST['currency'];
}

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Stock alert count
$stmt = $pdo->prepare("SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= 5");
$stmt->execute();
$low_stock_count = $stmt->fetch()['low_stock'];

// Initialize customer info and order items
if (!isset($_SESSION['customer_info'])) {
    $_SESSION['customer_info'] = ['name' => '', 'address' => '', 'phone' => ''];
}
if (!isset($_SESSION['order_items'])) {
    $_SESSION['order_items'] = [];
}

// Handle customer info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $_SESSION['customer_info'] = [
        'name' => $_POST['customer_name'] ?? '',
        'address' => $_POST['address'] ?? '',
        'phone' => $_POST['phone'] ?? ''
    ];
}

// Handle clear customer info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_customer'])) {
    $_SESSION['customer_info'] = ['name' => '', 'address' => '', 'phone' => ''];
}

// Handle add/remove actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    $action = $_POST['action'];
    $stmt = $pdo->prepare("SELECT stock_quantity, price, product_name FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    $stock = $product['stock_quantity'];

    $response = ['status' => 'success'];

    if ($action === 'add' && $stock > 0 && (!isset($_SESSION['order_items'][$product_id]) || $_SESSION['order_items'][$product_id] < $stock)) {
        $_SESSION['order_items'][$product_id] = ($_SESSION['order_items'][$product_id] ?? 0) + 1;
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - 1 WHERE product_id = ?")->execute([$product_id]);
        $response['quantity'] = $_SESSION['order_items'][$product_id];
    } elseif ($action === 'remove' && isset($_SESSION['order_items'][$product_id]) && $_SESSION['order_items'][$product_id] > 0) {
        $_SESSION['order_items'][$product_id]--;
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + 1 WHERE product_id = ?")->execute([$product_id]);
        if ($_SESSION['order_items'][$product_id] === 0) {
            unset($_SESSION['order_items'][$product_id]);
        }
        $response['quantity'] = $_SESSION['order_items'][$product_id] ?? 0;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Invalid action or stock limit reached.';
    }

    // Prepare order items for response
    $order_items = [];
    $subtotal = 0;
    foreach ($_SESSION['order_items'] as $pid => $quantity) {
        if ($quantity > 0) {
            $stmt = $pdo->prepare("SELECT product_name, price FROM products WHERE product_id = ?");
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();
            if ($prod) {
                $item_total = $prod['price'] * $quantity;
                $subtotal += $item_total;
                $order_items[] = [
                    'product_id' => $pid,
                    'product_name' => $prod['product_name'],
                    'price' => format_currency($prod['price'], $currency, $exchange_rates),
                    'quantity' => $quantity,
                    'total' => format_currency($item_total, $currency, $exchange_rates)
                ];
            }
        }
    }
    $response['order_items'] = $order_items;
    $response['subtotal'] = format_currency($subtotal, $currency, $exchange_rates);

    echo json_encode($response);
    exit();
}

// Process Transaction or Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pay_now'])) {
        if (!empty($_SESSION['order_items'])) {
            $customer_name = $_SESSION['customer_info']['name'];
            $address = $_SESSION['customer_info']['address'];
            $phone = $_SESSION['customer_info']['phone'];
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $subtotal = 0;
            foreach ($_SESSION['order_items'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("SELECT price FROM products WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    $subtotal += $product['price'] * $quantity;
                }
            }
            $tax = isset($_POST['tax']) && is_numeric($_POST['tax']) ? $subtotal * ($_POST['tax'] / 100) : 0;
            $shipping = isset($_POST['shipping']) && is_numeric($_POST['shipping']) ? floatval($_POST['shipping']) : 0;
            $discount = isset($_POST['discount']) && is_numeric($_POST['discount']) ? $subtotal * ($_POST['discount'] / 100) : 0;
            $total = $subtotal + $tax + $shipping - $discount;

            $stmt = $pdo->query("SELECT MAX(order_id) as last_id FROM orders");
            $last_id = $stmt->fetch()['last_id'] ?? 0;
            $order_id = $last_id + 1;
            $invoice_number = 'INV-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO orders (order_id, user_id, customer_name, address, phone_no, invoice_number, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->execute([$order_id, $_SESSION['user_id'] ?? 1, $customer_name, $address, $phone, $invoice_number, $total, $payment_method]);

            foreach ($_SESSION['order_items'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("SELECT price FROM products WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    $price = $stmt->fetch()['price'];
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$order_id, $product_id, $quantity, $price]);
                }
            }

            $_SESSION['order_items'] = [];
            header("Location: invoice.php?order_id=$order_id");
            exit();
        }
    } elseif (isset($_POST['reset_all'])) {
        $_SESSION['order_items'] = [];
    }
}

// Initial products fetch
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$limit = (int)$limit;
$offset = (int)$offset;

$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE 1=1 " 
    . ($search_query ? "AND (p.product_id LIKE ? OR p.product_name LIKE ? OR c.category_name LIKE ?)" : "") 
    . ($category_filter ? "AND c.category_name = ?" : "") 
    . " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$params = [];
if ($search_query) {
    $search_param = "%$search_query%";
    $params = [$search_param, $search_param, $search_param];
}
if ($category_filter) {
    $params[] = $category_filter;
}
$stmt->execute($params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .pos-card {
            height: 400px;
            width: 100%;
            max-width: 250px;
            border: 1px solid #ddd;
            border-radius: 15px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            position: relative;
            transition: transform 0.3s ease;
            cursor: default;
        }
        .pos-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        .card-img-top {
            height: 200px;
            width: 100%;
            object-fit: contain;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .card-body {
            flex-grow: 1;
            text-align: center;
            padding: 10px;
            width: 100%;
        }
        .card-title {
            font-size: 1.1rem;
            margin: 5px 0;
            color: #333;
            font-weight: bold;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.3;
            height: 40px;
        }
        .availability {
            font-size: 0.8rem;
            color: #666;
            margin: 5px 0;
        }
        .price {
            font-size: 1rem;
            font-weight: bold;
            color: #000;
            margin: 10px 0;
        }
        .quantity {
            font-size: 1rem;
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-quantity, .btn-details {
            width: 30px;
            height: 30px;
            padding: 0;
            font-size: 1rem;
            line-height: 1;
            border-radius: 20%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .btn-quantity {
            background: #007bff;
            color: #fff;
        }
        .btn-quantity:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-details {
            background: #17a2b8;
            color: #fff;
        }
        .btn-details:hover {
            background: #138496;
        }
        .customer-info-card, .customer-details-card {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .left-section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .customer-details-card {
            padding: 10px;
            width: 90%;
        }
        .customer-details .form-label {
            width: 150px;
            font-weight: bold;
        }
        .customer-details .form-control {
            flex: 1;
        }
        .order-table th, .order-table td {
            vertical-align: middle;
        }
        .order-table tbody tr {
            transition: opacity 0.5s ease;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        .fade-out {
            animation: fadeOut 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @media (max-width: 768px) {
            .product-item {
                flex: 0 0 50%;
                max-width: 50%;
            }
            .pos-card {
                height: 350px;
                max-width: 200px;
            }
            .card-img-top {
                height: 150px;
            }
        }
    </style>
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
        <div class="row">
            <div class="col-12 mb-3">
                <form method="GET" id="searchForm">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by ID, Name, or Category" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-4">
                            <select class="form-control" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php
                                $cat_stmt = $pdo->query("SELECT DISTINCT category_name FROM categories c LEFT JOIN products p ON c.category_id = p.category_id WHERE c.category_name IS NOT NULL");
                                while ($cat = $cat_stmt->fetch()) {
                                    echo "<option value=\"" . htmlspecialchars($cat['category_name']) . "\" " . ($category_filter == $cat['category_name'] ? 'selected' : '') . ">" . htmlspecialchars($cat['category_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-8">
                <h3 class="mb-3">BitsTech Items</h3>
                <div class="row g-3" id="product-list">
                    <?php 
                    $product_count = 0;
                    while ($product = $stmt->fetch()): 
                        $product_count++;
                    ?>
                        <div class="col-md-3 product-item">
                            <div class="card pos-card">
                                <img src="../public/images/<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['product_name'] ?? 'Product'); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['product_name'] ?? 'Unnamed Product'); ?></h5>
                                    <p class="availability">Category: <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                                    <p class="availability">Available: <?php echo $product['stock_quantity'] ?? 0; ?> Sold: 0</p>
                                    <p class="price"><?php echo format_currency($product['price'] ?? 0, $currency, $exchange_rates); ?></p>
                                    <div class="quantity-controls">
                                        <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="btn-details"><i class="fas fa-info-circle"></i></a>
                                        <span class="quantity" id="quantity_<?php echo $product['product_id']; ?>"><?php echo isset($_SESSION['order_items'][$product['product_id']]) ? $_SESSION['order_items'][$product['product_id']] : 0; ?></span>
                                        <button class="btn-quantity" data-product-id="<?php echo $product['product_id']; ?>" data-stock="<?php echo $product['stock_quantity']; ?>" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php if ($product_count === 0): ?>
                        <div class="col-12 text-center">
                            <p>No products found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="loading" class="text-center my-3" style="display: none;">
                    <p>Loading more products...</p>
                </div>
            </div>

            <div class="col-md-4">
                <h3 class="left-section-title mb-3">Customer Information</h3>
                <div class="card shadow-sm mb-4 customer-info-card">
                    <div class="card-body">
                        <?php if ($_SESSION['customer_info']['name']): ?>
                            <p class="card-text"><i class="fas fa-user me-2"></i><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['customer_info']['name']); ?></p>
                        <?php endif; ?>
                        <?php if ($_SESSION['customer_info']['address']): ?>
                            <p class="card-text"><i class="fas fa-map-marker-alt me-2"></i><strong>Address:</strong> <?php echo htmlspecialchars($_SESSION['customer_info']['address']); ?></p>
                        <?php endif; ?>
                        <?php if ($_SESSION['customer_info']['phone']): ?>
                            <p class="card-text"><i class="fas fa-phone me-2"></i><strong>Phone:</strong> <?php echo htmlspecialchars($_SESSION['customer_info']['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <h3 class="left-section-title mb-3">Enter Customer Details</h3>
                <div class="card shadow-sm mb-4 customer-details-card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_customer" value="1">
                            <div class="mb-2 customer-details">
                                <label for="customer_name" class="form-label mb-0">Customer Name</label>
                                <input type="text" class="form-control form-control-sm" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($_SESSION['customer_info']['name']); ?>">
                            </div>
                            <div class="mb-2 customer-details">
                                <label for="address" class="form-label mb-0">Address</label>
                                <input type="text" class="form-control form-control-sm" id="address" name="address" value="<?php echo htmlspecialchars($_SESSION['customer_info']['address']); ?>">
                            </div>
                            <div class="mb-2 customer-details">
                                <label for="phone" class="form-label mb-0">Phone No</label>
                                <input type="text" class="form-control form-control-sm" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['customer_info']['phone']); ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm w-50">Update</button>
                                <button type="submit" name="clear_customer" value="1" class="btn btn-secondary btn-sm w-50">Clear</button>
                            </div>
                        </form>
                    </div>
                </div>

                <h3 class="left-section-title mb-3">Order Details</h3>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <table class="table table-sm table-bordered order-table">
                            <thead>
                                <tr class="table-primary">
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="order-table-body">
                                <?php
                                $subtotal = 0;
                                foreach ($_SESSION['order_items'] as $product_id => $quantity) {
                                    if ($quantity > 0) {
                                        $stmt = $pdo->prepare("SELECT product_name, price FROM products WHERE product_id = ?");
                                        $stmt->execute([$product_id]);
                                        $product = $stmt->fetch();
                                        if ($product) {
                                            $item_total = $product['price'] * $quantity;
                                            $subtotal += $item_total;
                                            echo "<tr data-product-id='$product_id'>";
                                            echo "<td>" . htmlspecialchars($product['product_name'] ?? 'Unnamed Product') . "</td>";
                                            echo "<td>" . format_currency($product['price'], $currency, $exchange_rates) . "</td>";
                                            echo "<td>$quantity</td>";
                                            echo "<td>" . format_currency($item_total, $currency, $exchange_rates) . "</td>";
                                            echo "<td><form method='POST' style='display:inline;' id='removeOrderForm_$product_id'><input type='hidden' name='action' value='remove'><input type='hidden' name='product_id' value='$product_id'><button type='submit' class='btn btn-danger btn-sm'><i class='fas fa-trash'></i></button></form></td>";
                                            echo "</tr>";
                                        }
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <div class="mb-2">
                                <label for="tax" class="form-label">Order Tax (%)</label>
                                <input type="number" class="form-control form-control-sm" id="tax" name="tax" value="0" step="0.1" min="0">
                            </div>
                            <div class="mb-2">
                                <label for="discount" class="form-label">Discount (%)</label>
                                <input type="number" class="form-control form-control-sm" id="discount" name="discount" value="0" step="0.1" min="0">
                            </div>
                            <div class="mb-2">
                                <label for="shipping" class="form-label">Shipping (MMK)</label>
                                <input type="number" class="form-control form-control-sm" id="shipping" name="shipping" value="0" step="0.01" min="0">
                            </div>
                            <div class="mb-2">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-control form-control-sm" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Kpay">Kpay</option>
                                    <option value="NUGpay">NUGpay</option>
                                    <option value="Thai Baht">Thai Baht</option>
                                    <option value="Cash">Cash</option>
                                </select>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Totals:</span>
                                <span id="total-amount"><?php
                                    $tax = isset($_POST['tax']) && is_numeric($_POST['tax']) ? $subtotal * ($_POST['tax'] / 100) : 0;
                                    $shipping = isset($_POST['shipping']) && is_numeric($_POST['shipping']) ? floatval($_POST['shipping']) : 0;
                                    $discount = isset($_POST['discount']) && is_numeric($_POST['discount']) ? $subtotal * ($_POST['discount'] / 100) : 0;
                                    $total = $subtotal + $tax + $shipping - $discount;
                                    echo format_currency($total, $currency, $exchange_rates);
                                ?></span>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <form method="POST" class="w-50">
                                    <input type="hidden" name="reset_all" value="1">
                                    <button type="submit" class="btn btn-danger w-100">Reset All</button>
                                </form>
                                <form method="POST" class="w-50">
                                    <input type="hidden" name="pay_now" value="1">
                                    <button type="submit" class="btn btn-success w-100">Pay Now <i class="fas fa-money-bill-wave"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isLoggedIn()): ?>
            <div class="text-center mt-4">
                <a href="profile.php" class="btn btn-primary">Go to Profile</a>
            </div>
        <?php else: ?>
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-primary">Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let loading = false;
        let page = 1;

        // Function to update the order table dynamically with animation
        function updateOrderTable(orderItems) {
            const tbody = document.getElementById('order-table-body');
            tbody.innerHTML = ''; // Clear existing rows

            if (orderItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No items in the order.</td></tr>';
                return;
            }

            orderItems.forEach(item => {
                const row = document.createElement('tr');
                row.setAttribute('data-product-id', item.product_id);
                row.classList.add('fade-in');
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>${item.price}</td>
                    <td>${item.quantity}</td>
                    <td>${item.total}</td>
                    <td>
                        <form method="POST" style="display:inline;" id="removeOrderForm_${item.product_id}">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="${item.product_id}">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                `;
                tbody.appendChild(row);
                // Remove fade-in class after animation
                setTimeout(() => row.classList.remove('fade-in'), 500);
            });

            // Rebind remove button event listeners
            document.querySelectorAll('.order-table .btn-danger').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const form = this.closest('form');
                    const productId = form.querySelector('input[name="product_id"]').value;
                    const row = this.closest('tr');
                    row.classList.add('fade-out');

                    setTimeout(() => {
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: new FormData(form),
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                const data = JSON.parse(response);
                                if (data.status === 'success') {
                                    // Update quantity display
                                    document.getElementById('quantity_' + productId).textContent = data.quantity;
                                    // Update order table
                                    updateOrderTable(data.order_items);
                                    updateTotalAmount(data.subtotal);
                                }
                            },
                            error: function() {
                                alert('Error removing product from order.');
                            }
                        });
                    }, 500); // Match animation duration
                });
            });
        }

        // Function to update the total amount
        function updateTotalAmount(subtotal) {
            const taxInput = document.getElementById('tax').value || 0;
            const discountInput = document.getElementById('discount').value || 0;
            const shippingInput = document.getElementById('shipping').value || 0;

            // Extract numeric value from subtotal string (e.g., "100.00 USD")
            const subtotalValue = parseFloat(subtotal.split(' ')[0]);
            const tax = subtotalValue * (taxInput / 100);
            const discount = subtotalValue * (discountInput / 100);
            const shipping = parseFloat(shippingInput);
            const total = subtotalValue + tax + shipping - discount;

            document.getElementById('total-amount').textContent = `${total.toFixed(2)} <?php echo $currency; ?>`;
        }

        // Handle Add button with AJAX
        document.querySelectorAll('.btn-quantity').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default form submission
                const productId = this.getAttribute('data-product-id');
                const stock = parseInt(this.getAttribute('data-stock'));
                const currentQuantity = parseInt(document.getElementById('quantity_' + productId).textContent);

                if (stock > 0 && (currentQuantity < stock)) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: { action: 'add', product_id: productId },
                        success: function(response) {
                            const data = JSON.parse(response);
                            if (data.status === 'success') {
                                // Update quantity display
                                document.getElementById('quantity_' + productId).textContent = data.quantity;
                                // Update order table
                                updateOrderTable(data.order_items);
                                updateTotalAmount(data.subtotal);
                            }
                        },
                        error: function() {
                            alert('Error adding product.');
                        }
                    });
                } else {
                    this.disabled = true;
                }
            });
        });

        // Handle Remove from Order Table
        document.querySelectorAll('.order-table .btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const productId = form.querySelector('input[name="product_id"]').value;
                const row = this.closest('tr');
                row.classList.add('fade-out');

                setTimeout(() => {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: new FormData(form),
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            const data = JSON.parse(response);
                            if (data.status === 'success') {
                                // Update quantity display
                                document.getElementById('quantity_' + productId).textContent = data.quantity;
                                // Update order table
                                updateOrderTable(data.order_items);
                                updateTotalAmount(data.subtotal);
                            }
                        },
                        error: function() {
                            alert('Error removing product from order.');
                        }
                    });
                }, 500); // Match animation duration
            });
        });

        // Scroll loading
        window.addEventListener('scroll', function() {
            if (window.scrollY + window.innerHeight > document.documentElement.scrollHeight - 100 && !loading) {
                loading = true;
                document.getElementById('loading').style.display = 'block';
                page++;

                fetch(`fetch_products.php?page=${page}&search=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>`)
                    .then(response => response.text())
                    .then(data => {
                        if (data.trim() !== '') {
                            document.getElementById('product-list').insertAdjacentHTML('beforeend', data);
                            // Re-bind event listeners for new elements
                            document.querySelectorAll('.btn-quantity').forEach(button => {
                                if (!button.onclick) {
                                    button.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const productId = this.getAttribute('data-product-id');
                                        const stock = parseInt(this.getAttribute('data-stock'));
                                        const currentQuantity = parseInt(document.getElementById('quantity_' + productId).textContent);

                                        if (stock > 0 && (currentQuantity < stock)) {
                                            $.ajax({
                                                url: window.location.href,
                                                type: 'POST',
                                                data: { action: 'add', product_id: productId },
                                                success: function(response) {
                                                    const data = JSON.parse(response);
                                                    if (data.status === 'success') {
                                                        document.getElementById('quantity_' + productId).textContent = data.quantity;
                                                        updateOrderTable(data.order_items);
                                                        updateTotalAmount(data.subtotal);
                                                    }
                                                },
                                                error: function() {
                                                    alert('Error adding product.');
                                                }
                                            });
                                        } else {
                                            this.disabled = true;
                                        }
                                    });
                                }
                            });
                        } else {
                            loading = true;
                        }
                        document.getElementById('loading').style.display = 'none';
                        loading = false;
                    })
                    .catch(() => {
                        document.getElementById('loading').style.display = 'none';
                        loading = false;
                    });
            }
        });

        // Update total on input change
        document.getElementById('tax').addEventListener('input', function() {
            updateTotalAmount(document.getElementById('total-amount').textContent.split(' ')[0]);
        });
        document.getElementById('discount').addEventListener('input', function() {
            updateTotalAmount(document.getElementById('total-amount').textContent.split(' ')[0]);
        });
        document.getElementById('shipping').addEventListener('input', function() {
            updateTotalAmount(document.getElementById('total-amount').textContent.split(' ')[0]);
        });

        document.getElementById('searchForm')?.addEventListener('submit', function(e) {
            const currency = document.getElementById('currencySelect')?.value;
            if (currency) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'currency';
                input.value = currency;
                this.appendChild(input);
            }
        });
    </script>
</body>
</html>