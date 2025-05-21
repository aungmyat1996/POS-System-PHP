<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check login and admin status
check_login();
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

$action = $_GET['action'] ?? '';
$productId = $_GET['id'] ?? 0;

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize_db_input($conn, $_POST['name'] ?? '');
        $barcode = sanitize_db_input($conn, $_POST['barcode'] ?? '');
        $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT);
        $image = sanitize_db_input($conn, $_POST['image'] ?? '');

        // Validate inputs
        $errors = [];
        if (empty($name)) $errors[] = "Product name is required.";
        if (empty($barcode)) $errors[] = "Barcode is required.";
        if ($price === false || $price < 0) $errors[] = "Invalid price.";
        if ($stock === false || $stock < 0) $errors[] = "Invalid stock level.";
        if (empty($image)) $errors[] = "Image file name is required.";

        if (empty($errors)) {
            $query = "INSERT INTO products (name, barcode, price, stock, image) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ssdss", $name, $barcode, $price, $stock, $image);
                if ($stmt->execute()) {
                    header("Location: product.php?message=Product added successfully");
                    exit;
                } else {
                    $errorMessage = "Error adding product: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Prepare statement error: " . $conn->error;
            }
        } else {
            $errorMessage = implode("<br>", $errors);
        }

        // If there's an error, redirect back to the add form with an error message
        header("Location: product_action.php?action=add&error=" . urlencode($errorMessage));
        exit;
    }

    // Display the add product form
    include 'product_form.php';
    exit;

} elseif ($action === 'edit' && $productId > 0) {
    // Fetch product data for editing
    $query = "SELECT id, name, barcode, price, stock, image FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if (!$product) {
            header("Location: product.php?error=Product not found");
            exit;
        }
    } else {
        header("Location: product.php?error=Database error");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize_db_input($conn, $_POST['name'] ?? '');
        $barcode = sanitize_db_input($conn, $_POST['barcode'] ?? '');
        $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT);
        $image = sanitize_db_input($conn, $_POST['image'] ?? '');

        // Validate inputs
        $errors = [];
        if (empty($name)) $errors[] = "Product name is required.";
        if (empty($barcode)) $errors[] = "Barcode is required.";
        if ($price === false || $price < 0) $errors[] = "Invalid price.";
        if ($stock === false || $stock < 0) $errors[] = "Invalid stock level.";
        if (empty($image)) $errors[] = "Image file name is required.";

        if (empty($errors)) {
            $query = "UPDATE products SET name = ?, barcode = ?, price = ?, stock = ?, image = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ssdssi", $name, $barcode, $price, $stock, $image, $productId);
                if ($stmt->execute()) {
                    header("Location: product.php?message=Product updated successfully");
                    exit;
                } else {
                    $errorMessage = "Error updating product: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Prepare statement error: " . $conn->error;
            }
        } else {
            $errorMessage = implode("<br>", $errors);
        }

        // If there's an error, redirect back to the edit form with an error message
        header("Location: product_action.php?action=edit&id=$productId&error=" . urlencode($errorMessage));
        exit;
    }

    // Display the edit product form
    include 'product_form.php';
    exit;

} elseif ($action === 'delete' && $productId > 0) {
    $query = "DELETE FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $productId);
        if ($stmt->execute()) {
            header("Location: product.php?message=Product deleted successfully");
            exit;
        } else {
            header("Location: product.php?error=Error deleting product: " . urlencode($stmt->error));
            exit;
        }
        $stmt->close();
    } else {
        header("Location: product.php?error=Database error");
        exit;
    }

} else {
    // Redirect to the product list page if no valid action is provided
    header("Location: product.php");
    exit;
}
?>

<?php
// product_form.php (Separate file for the form)
$title = ucfirst($action) . ' Product';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech - <?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>
<body>
    <div class="container mt-5">
        <h2><?php echo $title; ?></h2>
        <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($product['name']) ? htmlspecialchars($product['name']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="barcode" class="form-label">Barcode</label>
                <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo isset($product['barcode']) ? htmlspecialchars($product['barcode']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo isset($product['price']) ? $product['price'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="stock" class="form-label">Stock</label>
                <input type="number" class="form-control" id="stock" name="stock" value="<?php echo isset($product['stock']) ? $product['stock'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Image File Name</label>
                <input type="text" class="form-control" id="image" name="image" value="<?php echo isset($product['image']) ? htmlspecialchars($product['image']) : ''; ?>" placeholder="e.g., product1.jpg" required>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $title; ?></button>
            <a href="product.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>