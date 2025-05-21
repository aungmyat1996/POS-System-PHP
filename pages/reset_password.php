<?php
// Include the authentication check
require_once 'auth_check.php';
require_once '../config/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Invalid or missing token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password && $confirm_password) {
        if ($new_password === $confirm_password) {
            // Check if token is valid and not expired
            $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
                $stmt->execute([$hashed_password, $token]);
                $success = 'Password reset successfully! <a href="login.php">Login here</a>';
            } else {
                $error = 'Invalid or expired token.';
            }
        } else {
            $error = 'Passwords do not match.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .reset-container {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .error {
            color: #dc3545;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .success {
            color: #28a745;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .form-control-sm {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="text-center mb-4">
            <h2>Reset Password</h2>
        </div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="reset">
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control form-control-sm" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>