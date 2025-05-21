<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total = 0;

ob_start();
?>
<div class="order-summary" id="order-summary">
    <?php foreach ($cart as $product_id => $item):
        $stmt = $pdo->prepare("SELECT product_name, price FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
    ?>
        <div class="d-flex justify-content-between mb-2" data-id="<?php echo $product_id; ?>">
            <span><?php echo htmlspecialchars($product['product_name']); ?> x <?php echo $item['quantity']; ?></span>
            <span><?php echo format_currency($item['price'] * $item['quantity'], $currency, $exchange_rates); ?></span>
        </div>
    <?php $total += $item['price'] * $item['quantity']; endforeach; ?>
    <hr>
    <div class="d-flex justify-content-between">
        <span>Subtotal:</span>
        <span><?php echo format_currency($total, $currency, $exchange_rates); ?></span>
    </div>
    <div class="d-flex justify-content-between">
        <span>Tax (10%):</span>
        <span><?php echo format_currency($total * 0.10, $currency, $exchange_rates); ?></span>
    </div>
    <hr>
    <div class="d-flex justify-content-between fw-bold">
        <span>Total:</span>
        <span><?php echo format_currency($total * 1.10, $currency, $exchange_rates); ?></span>
    </div>
    <button type="button" class="btn btn-primary w-100 mt-2" onclick="printInvoice()">Print Invoice</button>
</div>
<?php
echo ob_get_clean();
?>