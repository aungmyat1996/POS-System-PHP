<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Currency handling
$exchange_rates = ['USD' => 1, 'THB' => 33, 'MMK' => 2100];
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD';

function format_currency($amount, $currency, $exchange_rates) {
    $amount = $amount * $exchange_rates[$currency];
    return number_format($amount, 2) . ' ' . $currency;
}

// Handle Add Product
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = $_POST['product_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $description = $_POST['description'] ?? '';

    // Handle image upload
    $image = 'default.jpg'; // Default image if none uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/pos_system/public/images/';
        // Check if upload directory exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $message = '<div class="alert alert-danger">Failed to create upload directory: ' . $upload_dir . '</div>';
            }
        }
        // Check if directory is writable
        if (!is_writable($upload_dir)) {
            $message = '<div class="alert alert-danger">Upload directory is not writable. Please check permissions for ' . $upload_dir . '.</div>';
        } else {
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            $image_path = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $image = $image_name;
            } else {
                $message = '<div class="alert alert-danger">Failed to upload image. Error code: ' . $_FILES['image']['error'] . '</div>';
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $message = '<div class="alert alert-danger">Image upload error: ' . $_FILES['image']['error'] . '</div>';
    }

    if ($product_name && $category_id && $price > 0 && $stock_quantity >= 0) {
        $stmt = $pdo->prepare("INSERT INTO products (product_name, category_id, price, stock_quantity, image) VALUES (?, ?, ?, ?, ?)");
        $params = [$product_name, $category_id, $price, $stock_quantity, $image];
        // Add description only if it exists in the table
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'description'")->fetch();
        if ($columns) {
            $stmt = $pdo->prepare("INSERT INTO products (product_name, category_id, price, stock_quantity, description, image) VALUES (?, ?, ?, ?, ?, ?)");
            $params = [$product_name, $category_id, $price, $stock_quantity, $description, $image];
        }
        if ($stmt->execute($params)) {
            $message = '<div class="alert alert-success">Product added successfully! Image: ' . $image . '</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to add product.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $product_id = $_POST['product_id'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $description = $_POST['description'] ?? '';

    // Fetch existing product to get current image
    $stmt = $pdo->prepare("SELECT image FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $current_product = $stmt->fetch();
    $image = $current_product['image'] ?? 'default.jpg';

    // Handle image upload if a new image is provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/pos_system/public/images/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $message = '<div class="alert alert-danger">Failed to create upload directory: ' . $upload_dir . '</div>';
            }
        }
        if (!is_writable($upload_dir)) {
            $message = '<div class="alert alert-danger">Upload directory is not writable. Please check permissions for ' . $upload_dir . '.</div>';
        } else {
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            $image_path = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                // Delete old image if it’s not the default
                if ($image !== 'default.jpg') {
                    @unlink($upload_dir . $image);
                }
                $image = $image_name;
            } else {
                $message = '<div class="alert alert-danger">Failed to upload new image. Error code: ' . $_FILES['image']['error'] . '</div>';
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $message = '<div class="alert alert-danger">Image upload error: ' . $_FILES['image']['error'] . '</div>';
    }

    if ($product_id && $product_name && $category_id && $price > 0 && $stock_quantity >= 0) {
        $stmt = $pdo->prepare("UPDATE products SET product_name = ?, category_id = ?, price = ?, stock_quantity = ?, image = ? WHERE product_id = ?");
        $params = [$product_name, $category_id, $price, $stock_quantity, $image, $product_id];
        // Add description only if it exists in the table
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'description'")->fetch();
        if ($columns) {
            $stmt = $pdo->prepare("UPDATE products SET product_name = ?, category_id = ?, price = ?, stock_quantity = ?, description = ?, image = ? WHERE product_id = ?");
            $params = [$product_name, $category_id, $price, $stock_quantity, $description, $image, $product_id];
        }
        if ($stmt->execute($params)) {
            $message = '<div class="alert alert-success">Product updated successfully! Image: ' . $image . '</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to update product.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}

// Handle Delete Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'] ?? '';
    if ($product_id) {
        $stmt = $pdo->prepare("SELECT image FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        $image = $product['image'] ?? 'default.jpg';

        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        if ($stmt->execute([$product_id])) {
            if ($image !== 'default.jpg') {
                @unlink($_SERVER['DOCUMENT_ROOT'] . '/pos_system/public/images/' . $image);
            }
            $message = '<div class="alert alert-success">Product deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to delete product.</div>';
        }
    }
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Fetch products
$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE 1=1";
$params = [];

if ($search_query) {
    $query .= " AND (p.product_name LIKE ? OR p.product_id LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}
if ($category_filter) {
    $query .= " AND c.category_id = ?";
    $params[] = $category_filter;
}

$query .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BitsTech POS - Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
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
        <h3 class="mb-3">Product Management</h3>

        <!-- Messages -->
        <?php echo $message; ?>

        <!-- Filter Form -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <select class="form-control" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
        </div>

        <!-- Product Table -->
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td>
                            <img src="/pos_system/public/images/<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>" 
                                 class="product-img" 
                                 alt="Product Image" 
                                 onerror="this.onerror=null; this.src='/pos_system/public/images/default.jpg';">
                        </td>
                        <td><?php echo htmlspecialchars($product['product_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo format_currency($product['price'] ?? 0, $currency, $exchange_rates); ?></td>
                        <td><?php echo $product['stock_quantity'] ?? 0; ?></td>
                        <td><?php echo htmlspecialchars($product['description'] ?? ''); ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editProductModal" 
                                    data-id="<?php echo $product['product_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-category="<?php echo $product['category_id']; ?>"
                                    data-price="<?php echo $product['price']; ?>"
                                    data-stock="<?php echo $product['stock_quantity']; ?>"
                                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                    data-image="<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteProductModal" 
                                    data-id="<?php echo $product['product_id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_product" value="1">
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price (USD)</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="edit_product" value="1">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="mb-3">
                            <label for="edit_product_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="edit_product_name" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label">Category</label>
                            <select class="form-control" id="edit_category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price (USD)</label>
                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_stock_quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div>
                                <img id="edit_current_image" src="" class="product-img" alt="Current Image">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_image" class="form-label">New Image (optional)</label>
                            <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Product Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProductModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this product? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="delete_product" value="1">
                        <input type="hidden" name="product_id" id="delete_product_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('#editProductModal').forEach(modalTrigger => {
            modalTrigger.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const category = button.getAttribute('data-category');
                const price = button.getAttribute('data-price');
                const stock = button.getAttribute('data-stock');
                const description = button.getAttribute('data-description');
                const image = button.getAttribute('data-image');

                const modal = document.getElementById('editProductModal');
                modal.querySelector('#edit_product_id').value = id;
                modal.querySelector('#edit_product_name').value = name;
                modal.querySelector('#edit_category_id').value = category;
                modal.querySelector('#edit_price').value = price;
                modal.querySelector('#edit_stock_quantity').value = stock;
                modal.querySelector('#edit_description').value = description;
                modal.querySelector('#edit_current_image').src = '/pos_system/public/images/' + image;
            });
        });

        document.querySelectorAll('#deleteProductModal').forEach(modalTrigger => {
            modalTrigger.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const modal = document.getElementById('deleteProductModal');
                modal.querySelector('#delete_product_id').value = id;
            });
        });
    </script>
</body>
</html>