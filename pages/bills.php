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

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Handle Payment Confirmation
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = $_POST['order_id'] ?? 0;
    $payment_method = $_POST['payment_method'] ?? '';

    if ($order_id && $payment_method) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed', payment_method = ? WHERE order_id = ? AND status = 'pending'");
        if ($stmt->execute([$payment_method, $order_id])) {
            // Redirect to invoice.php to generate PDF
            header("Location: invoice.php?order_id=$order_id");
            exit();
        } else {
            $message = '<div class="alert alert-danger">Failed to confirm payment.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Invalid order or payment method.</div>';
    }
}

// Fetch orders
$orders = $pdo->query("SELECT * FROM orders ORDER BY order_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Bills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-5 pt-5">
        <h3 class="mb-3">Bills</h3>

        <!-- Messages -->
        <?php echo $message; ?>

        <!-- Highlight new order if redirected from checkout -->
        <?php
        $highlight_order_id = $_GET['order_id'] ?? 0;
        ?>

        <!-- Orders Table -->
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Customer Name</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr class="<?php echo $order['order_id'] == $highlight_order_id ? 'table-primary' : ''; ?>">
                        <td><?php echo htmlspecialchars($order['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                        <td><?php echo format_currency($order['total_amount'], $currency, $exchange_rates); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td>
                            <?php if ($order['status'] === 'pending'): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal"
                                        data-id="<?php echo $order['order_id']; ?>">
                                    Pay Now
                                </button>
                            <?php elseif ($order['status'] === 'completed'): ?>
                                <a href="invoice.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-success btn-sm">View Invoice</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No bills found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Confirm Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="confirm_payment" value="1">
                        <input type="hidden" name="order_id" id="payment_order_id">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="mobile_payment">Mobile Payment</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <h5>Customer Information</h5>
                            <?php
                            $order_id = $_GET['order_id'] ?? 0;
                            if ($order_id) {
                                $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
                                $stmt->execute([$order_id]);
                                $order = $stmt->fetch();
                                if ($order) {
                                    echo "<p><strong>Name:</strong> " . htmlspecialchars($order['customer_name']) . "</p>";
                                    if ($order['address']) {
                                        echo "<p><strong>Address:</strong> " . htmlspecialchars($order['address']) . "</p>";
                                    }
                                    echo "<p><strong>Phone:</strong> " . htmlspecialchars($order['phone_no']) . "</p>";
                                }
                            }
                            ?>
                        </div>
                        <button type="submit" class="btn btn-success">Confirm Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('#paymentModal').forEach(modalTrigger => {
            modalTrigger.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const orderId = button.getAttribute('data-id');
                const modal = document.getElementById('paymentModal');
                modal.querySelector('#payment_order_id').value = orderId;
            });
        });
    </script>
</body>
</html>