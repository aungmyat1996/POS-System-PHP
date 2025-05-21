<?php
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logAction($pdo, $user_id, $action) {
    try {
        // Check if the logs table exists, create it if it doesn't
        $checkTable = $pdo->query("SHOW TABLES LIKE 'logs'");
        if ($checkTable->rowCount() == 0) {
            $pdo->exec("CREATE TABLE logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    } catch (PDOException $e) {
        // Log the error to a file or handle it silently (optional)
        error_log("Log action failed: " . $e->getMessage());
    }
}
?>