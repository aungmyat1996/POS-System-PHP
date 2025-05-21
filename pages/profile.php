<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once 'auth_check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user = null;
$message = '';

// Fetch current user data
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch admins and cashiers (for admin panel only)
if ($user && $user['role'] === 'admin') {
    $adminStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin'");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    $cashierStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'cashier'");
    $cashierStmt->execute();
    $cashiers = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle profile update for current user (both admin and cashier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $profile_image = $_FILES['profile_image'] ?? null;

    $image_path = $user['profile_image'] ?? null;
    if ($profile_image && $profile_image['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $image_path = $upload_dir . basename($profile_image['name']);
        move_uploaded_file($profile_image['tmp_name'], $image_path);
    }

    if ($username && $email) {
        try {
            if ($password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, profile_image = ? WHERE user_id = ?");
                $stmt->execute([$username, $email, $hashed_password, $image_path, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE user_id = ?");
                $stmt->execute([$username, $email, $image_path, $user_id]);
            }
            $message = '<div class="alert alert-success">Profile updated successfully!</div>';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}

// Admin-specific actions (Add, Update, Delete Admin and Cashier)
if ($user && $user['role'] === 'admin') {
    // Handle add new admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
        $username = trim($_POST['new_username'] ?? '');
        $email = trim($_POST['new_email'] ?? '');
        $password = trim($_POST['new_password'] ?? '');
        $role = 'admin'; // Fixed role for admin

        if ($username && $email && $password) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                $message = '<div class="alert alert-success">Admin added successfully!</div>';
                $adminStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin'");
                $adminStmt->execute();
                $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error adding admin: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
        }
    }

    // Handle admin update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
        $admin_id = $_POST['admin_id'] ?? '';
        $username = trim($_POST['admin_username'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $password = trim($_POST['admin_password'] ?? '');
        $admin_image = $_FILES['admin_image'] ?? null;

        $image_path = null;
        if ($admin_image && $admin_image['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $image_path = $upload_dir . basename($admin_image['name']);
            move_uploaded_file($admin_image['tmp_name'], $image_path);
        }

        if ($admin_id && $username && $email) {
            try {
                if ($password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$username, $email, $hashed_password, $image_path, $admin_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$username, $email, $image_path, $admin_id]);
                }
                $message = '<div class="alert alert-success">Admin profile updated successfully!</div>';
                $adminStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin'");
                $adminStmt->execute();
                $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error updating admin: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please fill in all required fields for admin update.</div>';
        }
    }

    // Handle admin deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
        $delete_admin_id = $_POST['delete_admin_id'] ?? '';
        if ($delete_admin_id && $delete_admin_id != $user_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$delete_admin_id]);
                $message = '<div class="alert alert-success">Admin deleted successfully!</div>';
                $adminStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin'");
                $adminStmt->execute();
                $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error deleting admin: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Cannot delete your own account or invalid admin ID.</div>';
        }
    }

    // Handle add new cashier
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cashier'])) {
        $username = trim($_POST['new_username'] ?? '');
        $email = trim($_POST['new_email'] ?? '');
        $password = trim($_POST['new_password'] ?? '');
        $role = 'cashier'; // Fixed role for cashier

        if ($username && $email && $password) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                $message = '<div class="alert alert-success">Cashier added successfully!</div>';
                $cashierStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'cashier'");
                $cashierStmt->execute();
                $cashiers = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error adding cashier: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
        }
    }

    // Handle cashier update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cashier'])) {
        $cashier_id = $_POST['cashier_id'] ?? '';
        $username = trim($_POST['cashier_username'] ?? '');
        $email = trim($_POST['cashier_email'] ?? '');
        $password = trim($_POST['cashier_password'] ?? '');
        $cashier_image = $_FILES['cashier_image'] ?? null;

        $image_path = null;
        if ($cashier_image && $cashier_image['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $image_path = $upload_dir . basename($cashier_image['name']);
            move_uploaded_file($cashier_image['tmp_name'], $image_path);
        }

        if ($cashier_id && $username && $email) {
            try {
                if ($password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$username, $email, $hashed_password, $image_path, $cashier_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$username, $email, $image_path, $cashier_id]);
                }
                $message = '<div class="alert alert-success">Cashier updated successfully!</div>';
                $cashierStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'cashier'");
                $cashierStmt->execute();
                $cashiers = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error updating cashier: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please fill in all required fields for cashier update.</div>';
        }
    }

    // Handle cashier deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cashier'])) {
        $delete_cashier_id = $_POST['delete_cashier_id'] ?? '';
        if ($delete_cashier_id && $delete_cashier_id != $user_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$delete_cashier_id]);
                $message = '<div class="alert alert-success">Cashier deleted successfully!</div>';
                $cashierStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'cashier'");
                $cashierStmt->execute();
                $cashiers = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error deleting cashier: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Cannot delete your own account or invalid cashier ID.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            padding: 20px;
        }
        .profile-container, .admin-container, .cashier-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .mt-5.pt-5 {
            margin-top: 5rem !important;
            padding-top: 5rem !important;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .btn-sm {
            font-size: 0.875rem;
        }
        .profile-img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php
    $navbar_path = '../includes/navbar.php';
    $absolute_path = realpath($navbar_path);
    if ($absolute_path === false || !file_exists($navbar_path)) {
        echo "<!-- Debug: navbar.php not found. Expected path: $navbar_path, Resolved path: " . ($absolute_path ?: 'Not resolved') . " -->";
        echo '<nav class="navbar navbar-light bg-light"><a class="navbar-brand" href="http://localhost/pos_system/pages/home.php">BitsTech POS (Fallback)</a></nav>';
    } else {
        echo "<!-- Debug: Including navbar.php from $navbar_path (Resolved: $absolute_path) -->";
        include $navbar_path;
    }
    ?>

    <div class="container mt-5 pt-5">
        <?php echo $message; ?>

        <!-- Current User Profile (Visible to both Admin and Cashier) -->
        <div class="profile-container">
            <h3 class="mb-3"><?php echo $user['role'] === 'admin' ? 'Your Admin Profile' : 'Your Cashier Profile'; ?></h3>
            <?php if ($user): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-3">
                                <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'https://via.placeholder.com/150'); ?>" alt="Profile Image" class="profile-img">
                            </div>
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password">
                            </div>
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                                <a href="home.php" class="btn btn-secondary">Back to Home</a>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Additional Information for Cashier -->
                <?php if ($user['role'] === 'cashier'): ?>
                    <div class="mt-4">
                        <h4>Your Information</h4>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
                                <p><strong>Account Created:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    User not found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Admin-Specific Sections (Visible only to Admins) -->
        <?php if ($user && $user['role'] === 'admin'): ?>
            <!-- Admin Management -->
            <div class="admin-container">
                <h3 class="mb-3">Admin Management</h3>
                <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="fas fa-plus"></i> Add New Admin
                </button>
                <?php if ($admins): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($admin['profile_image'] ?? 'https://via.placeholder.com/50'); ?>" alt="Admin Image" class="profile-img" style="max-width: 50px; max-height: 50px;">
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['role']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editAdminModal_<?php echo $admin['user_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                                <input type="hidden" name="delete_admin_id" value="<?php echo $admin['user_id']; ?>">
                                                <button type="submit" name="delete_admin" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Edit Admin Modal -->
                                    <div class="modal fade" id="editAdminModal_<?php echo $admin['user_id']; ?>" tabindex="-1" aria-labelledby="editAdminModalLabel_<?php echo $admin['user_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editAdminModalLabel_<?php echo $admin['user_id']; ?>">Edit Admin</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['user_id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="admin_username_<?php echo $admin['user_id']; ?>" class="form-label">Username</label>
                                                            <input type="text" class="form-control" id="admin_username_<?php echo $admin['user_id']; ?>" name="admin_username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="admin_email_<?php echo $admin['user_id']; ?>" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="admin_email_<?php echo $admin['user_id']; ?>" name="admin_email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="admin_password_<?php echo $admin['user_id']; ?>" class="form-label">New Password (leave blank to keep current)</label>
                                                            <input type="password" class="form-control" id="admin_password_<?php echo $admin['user_id']; ?>" name="admin_password" placeholder="Enter new password">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="admin_image_<?php echo $admin['user_id']; ?>" class="form-label">Profile Image</label>
                                                            <input type="file" class="form-control" id="admin_image_<?php echo $admin['user_id']; ?>" name="admin_image" accept="image/*">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_admin" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No admin users found.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cashier Management -->
            <div class="cashier-container">
                <h3 class="mb-3">Cashier Management</h3>
                <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addCashierModal">
                    <i class="fas fa-plus"></i> Add New Cashier
                </button>
                <?php if ($cashiers): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashiers as $cashier): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($cashier['profile_image'] ?? 'https://via.placeholder.com/50'); ?>" alt="Cashier Image" class="profile-img" style="max-width: 50px; max-height: 50px;">
                                        </td>
                                        <td><?php echo htmlspecialchars($cashier['username']); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['role']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editCashierModal_<?php echo $cashier['user_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this cashier?');">
                                                <input type="hidden" name="delete_cashier_id" value="<?php echo $cashier['user_id']; ?>">
                                                <button type="submit" name="delete_cashier" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Edit Cashier Modal -->
                                    <div class="modal fade" id="editCashierModal_<?php echo $cashier['user_id']; ?>" tabindex="-1" aria-labelledby="editCashierModalLabel_<?php echo $cashier['user_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editCashierModalLabel_<?php echo $cashier['user_id']; ?>">Edit Cashier</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="cashier_id" value="<?php echo $cashier['user_id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="cashier_username_<?php echo $cashier['user_id']; ?>" class="form-label">Username</label>
                                                            <input type="text" class="form-control" id="cashier_username_<?php echo $cashier['user_id']; ?>" name="cashier_username" value="<?php echo htmlspecialchars($cashier['username']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="cashier_email_<?php echo $cashier['user_id']; ?>" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="cashier_email_<?php echo $cashier['user_id']; ?>" name="cashier_email" value="<?php echo htmlspecialchars($cashier['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="cashier_password_<?php echo $cashier['user_id']; ?>" class="form-label">New Password (leave blank to keep current)</label>
                                                            <input type="password" class="form-control" id="cashier_password_<?php echo $cashier['user_id']; ?>" name="cashier_password" placeholder="Enter new password">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="cashier_image_<?php echo $cashier['user_id']; ?>" class="form-label">Profile Image</label>
                                                            <input type="file" class="form-control" id="cashier_image_<?php echo $cashier['user_id']; ?>" name="cashier_image" accept="image/*">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_cashier" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No cashiers found.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Admin Modal -->
            <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addAdminModalLabel">Add New Admin</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="new_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="new_username" name="new_username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="new_email" name="new_email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add Cashier Modal -->
            <div class="modal fade" id="addCashierModal" tabindex="-1" aria-labelledby="addCashierModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addCashierModalLabel">Add New Cashier</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="new_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="new_username" name="new_username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="new_email" name="new_email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="add_cashier" class="btn btn-primary">Add Cashier</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>