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

// Fetch order details
$order_id = $_GET['order_id'] ?? null;
$order = null;
$order_items = [];
if ($order_id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("SELECT oi.*, p.product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
    }
}

// Redirect if order not found or not completed
if (!$order || $order['status'] !== 'completed') {
    header("Location: bills.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .invoice-container {
            width: 540px; /* Adjusted to fit A4 with safe margins */
            margin: 0 auto;
            padding: 1px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            font-size: 18px;
            line-height: 70px;
            text-align: center;
        }
        .invoice-title {
            color: #007bff;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        .details, .bill-to {
            margin-bottom: 1px;
            padding: 1px;
            background: #fff;
            border-radius: 0;
        }
        .details p, .bill-to p {
            margin: 0;
            font-size: 14px;
        }
        .details strong, .bill-to strong {
            font-weight: 600;
            color: #444;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1px;
            table-layout: fixed;
        }
        .table th, .table td {
            padding: 2px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            font-size: 12px;
            word-wrap: break-word;
        }
        .table th {
            background-color: #007bff;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .table tbody tr:hover {
            background-color: #f1f3f5;
        }
        .table td {
            color: #555;
        }
        .total {
            text-align: right;
            margin-top: 1px;
            padding: 1px;
            background: #fff;
            border-radius: 0;
        }
        .total p {
            margin: 0;
            font-size: 14px;
        }
        .total strong {
            font-size: 15px;
            color: #007bff;
        }
        .footer {
            text-align: center;
            margin-top: 1px;
            color: #666;
            font-size: 13px;
        }
        .no-print {
            display: block;
        }
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                padding: 0;
            }
            .invoice-container {
                margin: 0;
                padding: 1px;
                box-shadow: none;
                border: none;
                width: 540px;
                min-height: 842px;
            }
            .details, .total {
                background: #fff;
                padding: 1px;
            }
            .no-print {
                display: none;
            }
            .table th {
                background-color: #007bff !important;
                color: #fff !important;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-0 pt-0">
        <div class="invoice-container" id="invoice">
            <?php if ($order): ?>
                <div class="text-center">
                    <?php if (file_exists('../public/images/logo.png')): ?>
                        <img src="../public/images/logo.png" alt="BitsTech Logo" class="logo">
                    <?php else: ?>
                        <div class="logo">BitsTech</div>
                    <?php endif; ?>
                    <div class="invoice-title">INVOICE</div>
                </div>

                <div class="row details">
                    <div class="col-md-6">
                        <p><strong>Invoice #</strong> <?php echo htmlspecialchars($order['invoice_number']); ?></p>
                        <p><strong>Date of Issue</strong> <?php echo date('d/m/Y', strtotime($order['order_date'])); ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p><strong>BitsTech POS</strong></p>
                        <p>123 Business St, Yangon<br>Phone: +959123456789<br>bitstech.com</p>
                    </div>
                </div>

                <div class="row details bill-to">
                    <div class="col-md-6">
                        <p><strong>Bill To</strong></p>
                        <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <?php if ($order['address']): ?>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                        <?php endif; ?>
                        <p><strong>Phone Numbers:</strong> <?php echo htmlspecialchars($order['phone_no']); ?></p>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">No</th>
                            <th style="width: 35%;">Description</th>
                            <th style="width: 20%;">Unit Cost (<?php echo $currency; ?>)</th>
                            <th style="width: 15%;">Qty</th>
                            <th style="width: 20%;">Amount (<?php echo $currency; ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $subtotal = 0;
                        $index = 1;
                        foreach ($order_items as $item):
                            $item_total = $item['price'] * $item['quantity'];
                            $subtotal += $item_total;
                        ?>
                            <tr>
                                <td><?php echo $index++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></td>
                                <td><?php echo format_currency($item['price'], $currency, $exchange_rates); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo format_currency($item_total, $currency, $exchange_rates); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total">
                    <?php
                    $discount = $subtotal * 0.10;
                    $tax = $subtotal * 0.07;
                    $grand_total = $subtotal + $tax - $discount;
                    ?>
                    <p>Subtotal: <?php echo format_currency($subtotal, $currency, $exchange_rates); ?></p>
                    <p>Discount: -<?php echo format_currency($discount, $currency, $exchange_rates); ?> (10%)</p>
                    <p>Tax: <?php echo format_currency($tax, $currency, $exchange_rates); ?> (7%)</p>
                    <p><strong>Grand Total: <?php echo format_currency($grand_total, $currency, $exchange_rates); ?></strong></p>
                </div>

                <div class="footer">
                    <p>Thank you for your business!</p>
                    <?php if (file_exists('../public/images/logo.png')): ?>
                        <img src="../public/images/logo.png" alt="BitsTech Logo" style="width: 40px; height: 40px; border-radius: 50%; margin-top: 1px;">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background-color: #007bff; color: #fff; font-weight: bold; font-size: 12px; line-height: 40px; text-align: center; margin: 1px auto;">BitsTech</div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    <p>Order not found!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-3 no-print">
            <button class="btn btn-primary me-2" onclick="downloadPDF()">Download PDF</button>
            <a href="bills.php" class="btn btn-secondary">Back to Bills</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('invoice');
            const a4WidthPx = 595; // A4 width in pixels at 72 DPI
            const a4HeightPx = 842; // A4 height in pixels at 72 DPI
            const opt = {
                margin: [1, 10, 10, 10],
                filename: 'invoice_<?php echo $order['invoice_number'] ?? 'unknown'; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    width: a4WidthPx,
                    height: a4HeightPx,
                    y: 0
                },
                jsPDF: {
                    unit: 'px',
                    format: [a4WidthPx, a4HeightPx],
                    orientation: 'portrait',
                    putOnlyUsedFonts: true,
                    floatPrecision: 16
                },
                pagebreak: { 
                    mode: ['css', 'legacy'], 
                    avoid: ['.total', '.footer', 'tr', 'tbody'], 
                    before: ['.page-break'] 
                }
            };
            html2pdf().set(opt).from(element).toPdf().get('pdf').then(function(pdf) {
                const totalPages = pdf.internal.getNumberOfPages();
                if (totalPages > 1) {
                    for (let i = 1; i <= totalPages; i++) {
                        pdf.setPage(i);
                        pdf.setFontSize(8); // Small font size
                        pdf.setTextColor(64, 64, 64); // Dark gray color
                        pdf.text(`Voucher ${i} / Voucher ${totalPages}`, 540 - 50, 832); // Bottom-right corner
                    }
                }
                pdf.save();
            });
        }
    </script>
</body>
</html>