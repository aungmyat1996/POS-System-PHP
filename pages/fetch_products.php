<?php
   require_once '../config/db.php';

   $limit = 8;
   $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
   $offset = ($page - 1) * $limit;
   $search_query = $_GET['search'] ?? '';
   $query = "SELECT p.*, c.category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.category_id 
             WHERE 1=1 " 
       . ($search_query ? "AND (p.product_id LIKE ? OR p.product_name LIKE ?)" : "") 
       . " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
   $stmt = $pdo->prepare($query);
   $params = [];
   if ($search_query) {
       $search_param = "%$search_query%";
       $params = [$search_param, $search_param];
   }
   $stmt->execute($params);

   $exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
   $currency = $_SESSION['currency'] ?? 'USD';
   function format_currency($amount, $currency, $exchange_rates) {
       $amount = $amount * $exchange_rates[$currency];
       return number_format($amount, 2) . ' ' . $currency;
   }

   while ($product = $stmt->fetch()) {
       echo '<div class="col-md-3 product-item">';
       echo '<div class="card pos-card">';
       echo '<img src="../public/images/' . htmlspecialchars($product['image'] ?? 'default.jpg') . '" class="card-img-top" alt="' . htmlspecialchars($product['product_name'] ?? 'Product') . '">';
       echo '<div class="card-body">';
       echo '<h5 class="card-title">' . htmlspecialchars($product['product_name'] ?? 'Unnamed Product') . '</h5>';
       echo '<p class="availability">ID: ' . htmlspecialchars($product['product_id']) . '</p>';
       echo '<p class="availability">Category: ' . htmlspecialchars($product['category_name'] ?? 'Uncategorized') . '</p>';
       echo '<p class="availability">Available: ' . ($product['stock_quantity'] ?? 0) . '</p>';
       echo '<p class="price">' . format_currency($product['price'] ?? 0, $currency, $exchange_rates) . '</p>';
       echo '<div class="quantity-controls">';
       echo '<form method="POST" style="display:inline;">';
       echo '<input type="hidden" name="action" value="remove">';
       echo '<input type="hidden" name="product_id" value="' . $product['product_id'] . '">';
       echo '<button type="submit" class="btn-quantity-outline" ' . ($product['stock_quantity'] <= 0 || !isset($_SESSION['order_items'][$product['product_id']]) || $_SESSION['order_items'][$product['product_id']] <= 0 ? 'disabled' : '') . '><i class="fas fa-minus"></i></button>';
       echo '</form>';
       echo '<span class="quantity">' . (isset($_SESSION['order_items'][$product['product_id']]) ? $_SESSION['order_items'][$product['product_id']] : 0) . '</span>';
       echo '<form method="POST" style="display:inline;">';
       echo '<input type="hidden" name="action" value="add">';
       echo '<input type="hidden" name="product_id" value="' . $product['product_id'] . '">';
       echo '<button type="submit" class="btn-quantity" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '><i class="fas fa-plus"></i></button>';
       echo '</form>';
       echo '<form method="POST" style="display:inline;">';
       echo '<input type="hidden" name="buy_now" value="1">';
       echo '<input type="hidden" name="product_id" value="' . $product['product_id'] . '">';
       echo '<input type="hidden" name="quantity" value="1">';
       echo '<button type="submit" class="btn-buy-now" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '><i class="fas fa-shopping-cart"></i></button>';
       echo '</form>';
       echo '</div>';
       echo '</div>';
       echo '</div>';
       echo '</div>';
   }
   ?>