<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Log the logout action if user is logged in
if (isLoggedIn()) {
    logAction($pdo, $_SESSION['user_id'], "User logged out: {$_SESSION['username']}");
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>