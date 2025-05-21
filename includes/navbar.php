<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!-- Debug: navbar.php loaded successfully at " . __FILE__ . " -->";

$low_stock_count = 0; // Placeholder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currency'])) {
    $_SESSION['currency'] = $_POST['currency'];
}
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';
?>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50; padding: 10px 0;">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="http://localhost/pos_system/pages/home.php">
            <img src="../public/images/logo.png" alt="BitsTech Logo" class="logo me-2" style="height: 60px;">
            <span style="font-weight: bold; color: #ecf0f1;">BitsTech POS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="home.php" style="font-weight: bold; color: #ecf0f1;">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="product.php" style="font-weight: bold; color: #ecf0f1;">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="bills.php" style="font-weight: bold; color: #ecf0f1;">Bills</a></li>
                <li class="nav-item"><a class="nav-link" href="order_list.php" style="font-weight: bold; color: #ecf0f1;">Order List</a></li>
                <li class="nav-item"><a class="nav-link" href="history.php" style="font-weight: bold; color: #ecf0f1;">History</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php" style="font-weight: bold; color: #ecf0f1;">Reports</a></li>
            </ul>
            <form method="POST" class="d-flex me-3" id="currencyForm">
                <select name="currency" id="currencySelect" class="form-select form-select-sm" style="background-color: #34495e; color: #ecf0f1; border: 1px solid #ecf0f1;" onchange="this.form.submit()">
                    <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD</option>
                    <option value="THB" <?php echo $currency === 'THB' ? 'selected' : ''; ?>>THB</option>
                    <option value="MMK" <?php echo $currency === 'MMK' ? 'selected' : ''; ?>>MMK</option>
                </select>
            </form>
            <a href="dashboard.php" class="nav-link me-3 position-relative" style="color: #ecf0f1;">
                <i class="fas fa-bell"></i>
                <?php if ($low_stock_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $low_stock_count; ?></span>
                <?php endif; ?>
            </a>
            <span id="real-time" class="me-3" style="color: #ecf0f1;"></span>
            <div class="dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #ecf0f1;">
                    <i class="fas fa-user"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="background-color: #2c3e50; border: 1px solid #ecf0f1;">
                    <li><a class="dropdown-item" href="profile.php" style="color: #ecf0f1;">Profile</a></li>
                    <li><a class="dropdown-item" href="dashboard.php" style="color: #ecf0f1;">Dashboard</a></li>
                    <li><a class="dropdown-item" href="logout.php" style="color: #ecf0f1;">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currencySelect = document.getElementById('currencySelect');
        if (currencySelect) {
            currencySelect.addEventListener('change', function() {
                this.form.submit();
            });
        }

        function updateRealTime() {
            const now = new Date();
            const options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            const timeString = now.toLocaleTimeString('en-US', options);
            const dateString = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const realTimeElement = document.getElementById('real-time');
            if (realTimeElement) {
                realTimeElement.textContent = `${dateString} ${timeString}`;
            }
        }
        updateRealTime();
        setInterval(updateRealTime, 1000);
    });
</script>