<?php
// Start session
session_start();

// Include database connection
require_once '../config/db.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard for authenticated users
    header("Location: ../pages/dashboard.php");
    exit();
} else {
    // Redirect to login for unauthenticated users
    header("Location: ../pages/login.php");
    exit();
}
?>