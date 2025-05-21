<?php
echo "Admin hash: " . password_hash('adminpass', PASSWORD_BCRYPT) . "\n";
echo "Cashier hash: " . password_hash('cashierpass', PASSWORD_BCRYPT) . "\n";
?>