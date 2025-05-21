<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'quantity' => 0];

if (!isLoggedIn()) {
    $response['message'] = 'Please log in';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['change'])) {
    $product_id = (int)$_POST['product_id'];
    $change = (int)$_POST['change'];

    try {
        // Get product details
        $stmt = $pdo->prepare("SELECT price, stock_quantity FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            $response['message'] = 'Product not found';
            echo json_encode($response);
            exit;
        }

        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Get current quantity
        $current_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['quantity'] : 0;
        $new_quantity = $current_quantity + $change;

        if ($new_quantity < 0) {
            $response['message'] = 'Quantity cannot be negative';
            echo json_encode($response);
            exit;
        }

        if ($new_quantity > $product['stock_quantity']) {
            $response['message'] = 'Insufficient stock';
            echo json_encode($response);
            exit;
        }

        // Update cart
        if ($new_quantity == 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            $_SESSION['cart'][$product_id] = [
                'quantity' => $new_quantity,
                'price' => $product['price']
            ];
        }

        $response['success'] = true;
        $response['quantity'] = $new_quantity;
    } catch (PDOException $e) {
        $response['message'] = 'Error updating cart: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
?>