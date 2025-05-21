<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit;
}
?>