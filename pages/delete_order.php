<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];

    // Delete order items first
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);

    // Delete the order
    $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);

    // Redirect back to the referring page
    $referer = $_SERVER['HTTP_REFERER'] ?? 'order_list.php';
    header("Location: $referer");
    exit();
} else {
    header("Location: order_list.php");
    exit();
}
?>