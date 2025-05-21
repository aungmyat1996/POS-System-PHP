<?php
session_start();
require_once '../config/db.php';

// Initialize message variables
$msg = '';
$msgClass = '';

if (isset($_POST['submit'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username != "" && $password != "") {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'] ?? 'N/A';
                header("Location: profile.php");
                exit;
            } else {
                $msg = "Incorrect username or password!";
                $msgClass = "alert-danger";
            }
        } catch (PDOException $e) {
            $msg = "Database error: " . $e->getMessage();
            $msgClass = "alert-danger";
        }
    } else {
        $msg = "Both fields are required!";
        $msgClass = "alert-danger";
    }
}

// Forgot Password Logic
if (isset($_POST['forgot'])) {
    $email = trim($_POST['email']);

    if ($email != "") {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            $stmt->execute([$token, $email]);

            // Send email via PHPMailer with debugging
            require_once '../vendor/PHPMailer/src/PHPMailer.php';
            require_once '../vendor/PHPMailer/src/SMTP.php';
            require_once '../vendor/PHPMailer/src/Exception.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                // Enable detailed SMTP debugging
                $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
                $mail->Debugoutput = function($str, $level) use (&$msg) {
                    $msg .= "Debug: $str<br>";
                };

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'bitstech2025@gmail.com'; // Your Gmail address
                $mail->Password = 'immc tgsf pxmq mequ';  // Replace with your Gmail App Password
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('bitstech2025@gmail.com', 'BitsTech POS');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Click <a href='http://localhost/pos_system/pages/reset_password.php?token=$token'>here</a> to reset your password. This link expires in 1 hour.";

                $mail->send();
                $msg = "Password reset link has been sent to your email!";
                $msgClass = "alert-success";
            } catch (Exception $e) {
                $msg = "Failed to send reset email: {$mail->ErrorInfo}";
                $msgClass = "alert-danger";
            }
        } else {
            $msg = "No account found with that email!";
            $msgClass = "alert-danger";
        }
    } else {
        $msg = "Please enter your email!";
        $msgClass = "alert-danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        html, body {
            height: 100%;
            background-color: #f4f4f9;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
        }
        .alert {
            margin-bottom: 15px;
            font-size: 0.9rem;
            text-align: center;
        }
        .form-control {
            font-size: 0.9rem;
        }
        .btn {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <?php if (file_exists('../public/images/logo.png')): ?>
                <img src="../public/images/logo.png" alt="BitsTech Logo" class="login-logo">
            <?php else: ?>
                <div class="login-logo bg-primary text-white d-flex align-items-center justify-content-center">BitsTech</div>
            <?php endif; ?>
            <h2 class="h4">BitsTech POS - Login</h2>
        </div>

        <?php if ($msg != ''): ?>
            <div class="alert <?php echo $msgClass; ?>">
                <?php echo nl2br(htmlspecialchars($msg)); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" name="submit" class="btn btn-primary btn-block">Login</button>
            </div>
            <div class="text-center">
                <a href="#" data-toggle="modal" data-target="#forgotPasswordModal">Forgot Password?</a>
            </div>
        </form>

        <!-- Forgot Password Modal -->
        <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" name="forgot" class="btn btn-primary">Send Reset Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>