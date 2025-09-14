<?php
/**
 * Admin Products Management Page
 */

define('VEGAS_SHOP_ACCESS', true);

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/utils/FileUpload.php';

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$productModel = new Product();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Handle file uploads
                    $uploadedImages = [];
                    $fileUpload = new FileUpload();
                    
                    // Debug: Check if files were uploaded
                    if (!empty($_FILES['imageFiles']['name'][0])) {
                        $uploadedImages = $fileUpload->uploadMultiple($_FILES['imageFiles']);
                        
                        if (!empty($fileUpload->getErrors())) {
                            $message = "Upload errors: " . implode(', ', $fileUpload->getErrors());
                            $messageType = "error";
                            break;
                        }
                    } else {
                        // Debug: Log when no files are uploaded
                        error_log("No files uploaded in ADD case - FILES array: " . print_r($_FILES, true));
                    }
                    
                    // Use single image - take first uploaded image or existing image
                    $finalImage = '';
                    if (!empty($uploadedImages)) {
                        $finalImage = $uploadedImages[0]; // Take first uploaded image
                    } elseif (!empty($_POST['images'])) {
                        $finalImage = trim($_POST['images']);
                    }
                    
                    $productData = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'],
                        'prix_original' => !empty($_POST['prix_original']) ? (float)$_POST['prix_original'] : null,
                        'prix_sold' => !empty($_POST['prix_sold']) ? (float)$_POST['prix_sold'] : null,
                        'quantity' => (int)$_POST['quantity'],
                        'image' => $finalImage
                    ];
                    
                    $errors = $productModel->validate($productData);
                    if (empty($errors)) {
                        $productId = $productModel->create($productData);
                        $message = "Product added successfully!";
                        if (!empty($uploadedImages)) {
                            $message .= " " . count($uploadedImages) . " image(s) uploaded.";
                        }
                        $messageType = "success";
                    } else {
                        $message = "Validation errors: " . implode(', ', $errors);
                        $messageType = "error";
                    }
                } catch (Exception $e) {
                    $message = "Error adding product: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'update':
                try {
                    $productId = (int)$_POST['product_id'];
                    
                    // Handle file uploads
                    $uploadedImages = [];
                    $fileUpload = new FileUpload();
                    
                    if (!empty($_FILES['imageFiles']['name'][0])) {
                        $uploadedImages = $fileUpload->uploadMultiple($_FILES['imageFiles']);
                        
                        if (!empty($fileUpload->getErrors())) {
                            $message = "Upload errors: " . implode(', ', $fileUpload->getErrors());
                            $messageType = "error";
                            break;
                        }
                    }
                    
                    $updateData = [];
                    
                    if (!empty($_POST['nom'])) $updateData['nom'] = $_POST['nom'];
                    if (!empty($_POST['description'])) $updateData['description'] = $_POST['description'];
                    if (isset($_POST['prix_original'])) $updateData['prix_original'] = !empty($_POST['prix_original']) ? (float)$_POST['prix_original'] : null;
                    if (isset($_POST['prix_sold'])) $updateData['prix_sold'] = !empty($_POST['prix_sold']) ? (float)$_POST['prix_sold'] : null;
                    if (isset($_POST['quantity'])) $updateData['quantity'] = (int)$_POST['quantity'];
                    
                    // Handle image - use new upload if available, otherwise keep existing
                    if (!empty($uploadedImages)) {
                        $updateData['image'] = $uploadedImages[0]; // Take first uploaded image
                    } elseif (isset($_POST['images']) && !empty($_POST['images'])) {
                        $updateData['image'] = trim($_POST['images']);
                    }
                    
                    $updated = $productModel->update($productId, $updateData);
                    if ($updated > 0) {
                        $message = "Product updated successfully!";
                        if (!empty($uploadedImages)) {
                            $message .= " " . count($uploadedImages) . " new image(s) uploaded.";
                        }
                        $messageType = "success";
                    } else {
                        $message = "No changes made to the product.";
                        $messageType = "info";
                    }
                } catch (Exception $e) {
                    $message = "Error updating product: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete':
                try {
                    $productId = (int)$_POST['product_id'];
                    $deleted = $productModel->delete($productId);
                    if ($deleted > 0) {
                        $message = "Product deleted successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Product not found.";
                        $messageType = "error";
                    }
                } catch (Exception $e) {
                    $message = "Error deleting product: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get products with search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if ($search) {
    $products = $productModel->search($search);
    $totalProducts = count($products);
    $products = array_slice($products, $offset, $limit);
} else {
    $products = $productModel->readAll($limit, $offset);
    $totalProducts = $productModel->getCount();
}

$totalPages = ceil($totalProducts / $limit);

// Get product for editing if requested
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = $productModel->readById((int)$_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Vegas Shop Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: black;
            color: white;
            padding: 20px 0;
            z-index: 1000;
        }

        .sidebar .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar .logo h2 {
            font-size: 24px;
            font-weight: bold;
        }

        .sidebar .nav-menu {
            list-style: none;
        }

        .sidebar .nav-menu li {
            margin: 5px 0;
        }

        .sidebar .nav-menu a {
            display: block;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-menu a:hover,
        .sidebar .nav-menu a.active {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #fff;
        }

        .sidebar .nav-menu i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-bar input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            color: #333;
            font-size: 20px;
        }

        .card-body {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        .stock-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-status.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .stock-status.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .stock-status.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        /* Image Upload Styles */
        .image-upload-container {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            background: #fafafa;
        }

        .image-upload-area {
            text-align: center;
            padding: 40px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .image-upload-area:hover {
            background: #f0f0f0;
            border-color: black;
        }

        .image-upload-area i {
            font-size: 48px;
            color: black;
            margin-bottom: 15px;
        }

        .image-upload-area p {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .image-upload-area small {
            color: #666;
            font-size: 12px;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            border: 2px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            transition: all 0.3s ease;
        }

        .image-preview-item:hover {
            border-color: black;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .image-preview-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            display: block;
        }

        .image-preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-preview-item:hover .remove-image {
            opacity: 1;
        }

        .image-preview-item .remove-image:hover {
            background: #ff3742;
        }

        .image-preview-item .image-name {
            display: block;
            padding: 8px;
            font-size: 11px;
            color: #666;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .drag-over {
            border-color: black !important;
            background: #f0f0f0 !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                flex-direction: column;
                gap: 20px;
            }

            .search-bar {
                width: 100%;
            }

            .search-bar input {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 12px;
            }

            .image-preview-container {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2><i class="fas fa-store"></i> Vegas Shop</h2>
        </div>
        <ul class="nav-menu">
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Customers</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Products Management</h1>
            <div class="search-bar">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search): ?>
                    <a href="products.php" class="btn btn-warning">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Add/Edit Product Form -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h3>
                <?php if ($editProduct): ?>
                <a href="products.php" class="btn btn-warning">Cancel Edit</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'add'; ?>">
                    <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Product Name *</label>
                            <input type="text" id="nom" name="nom" required maxlength="30" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['nom']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity *</label>
                            <input type="number" id="quantity" name="quantity" required min="0" 
                                   value="<?php echo $editProduct ? $editProduct['quantity'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required><?php echo $editProduct ? htmlspecialchars($editProduct['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prix_original">Original Price</label>
                            <input type="number" id="prix_original" name="prix_original" step="0.01" min="0" 
                                   value="<?php echo $editProduct ? $editProduct['prix_original'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="prix_sold">Sale Price</label>
                            <input type="number" id="prix_sold" name="prix_sold" step="0.01" min="0" 
                                   value="<?php echo $editProduct ? $editProduct['prix_sold'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_images">Product Images</label>
                        <div class="image-upload-container">
                            <div class="image-upload-area" onclick="document.getElementById('imageFiles').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to select images or drag and drop</p>
                                <small>Supports: JPG, PNG, GIF (Max 5MB each)</small>
                            </div>
                            <input type="file" id="imageFiles" name="imageFiles[]" multiple accept="image/*" style="display: none;">
                            <input type="hidden" id="images" name="images" value="<?php echo $editProduct ? htmlspecialchars($editProduct['image']) : ''; ?>">
                            
                            <div id="imagePreview" class="image-preview-container">
                                <?php if ($editProduct && !empty($editProduct['image'])): ?>
                                    <div class="image-preview-item" data-filename="<?php echo htmlspecialchars($editProduct['image']); ?>">
                                        <img src="uploads/products/<?php echo htmlspecialchars($editProduct['image']); ?>" alt="Product Image" onerror="this.style.display='none';">
                                        <button type="button" class="remove-image" onclick="removeImage(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <span class="image-name"><?php echo htmlspecialchars($editProduct['image']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo $editProduct ? 'Update Product' : 'Add Product'; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Products List -->
        <div class="card">
            <div class="card-header">
                <h3>Products List (<?php echo $totalProducts; ?> total)</h3>
                <button onclick="openModal('addProductModal')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Quick Add
                </button>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Original Price</th>
                            <th>Sale Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <?php 
                                if (!empty($product['image'])) {
                                    $imagePath = 'uploads/products/' . $product['image'];
                                    if (file_exists(__DIR__ . '/' . $imagePath)): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>" class="product-image">
                                <?php else: ?>
                                    <i class="fas fa-image" style="font-size: 24px; color: #ddd;"></i>
                                <?php endif; 
                                } else { ?>
                                    <i class="fas fa-image" style="font-size: 24px; color: #ddd;"></i>
                                <?php } ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($product['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></td>
                            <td><?php echo $product['prix_original'] ? '$' . number_format($product['prix_original'], 2) : '-'; ?></td>
                            <td><?php echo $product['prix_sold'] ? '$' . number_format($product['prix_sold'], 2) : '-'; ?></td>
                            <td><?php echo $product['quantity']; ?></td>
                            <td>
                                <?php
                                if ($product['quantity'] > 10) {
                                    echo '<span class="stock-status in-stock">In Stock</span>';
                                } elseif ($product['quantity'] > 0) {
                                    echo '<span class="stock-status low-stock">Low Stock</span>';
                                } else {
                                    echo '<span class="stock-status out-of-stock">Out of Stock</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['nom']); ?>')" 
                                        class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="product_id" id="deleteProductId">
    </form>

    <script>
        // Image upload functionality
        let selectedImages = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            const imageFiles = document.getElementById('imageFiles');
            const imagePreview = document.getElementById('imagePreview');
            const uploadArea = document.querySelector('.image-upload-area');
            const imagesInput = document.getElementById('images');
            
            // Load existing image into selectedImages array
            if (imagesInput.value) {
                selectedImages = [imagesInput.value.trim()].filter(img => img);
            }
            
            // File input change event
            imageFiles.addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });
            
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                handleFiles(e.dataTransfer.files);
            });
            
            function handleFiles(files) {
                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        if (file.size > 5 * 1024 * 1024) { // 5MB limit
                            alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            addImagePreview(file.name, e.target.result, true);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            function addImagePreview(filename, src, isNew = false) {
                // Clear existing images and add new one
                selectedImages = [filename];
                updateImagesInput();
                
                // Clear existing previews
                imagePreview.innerHTML = '';
                
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item';
                previewItem.setAttribute('data-filename', filename);
                
                previewItem.innerHTML = `
                    <img src="${src}" alt="Product Image">
                    <button type="button" class="remove-image" onclick="removeImage(this)">
                        <i class="fas fa-times"></i>
                    </button>
                    <span class="image-name">${filename}</span>
                `;
                
                imagePreview.appendChild(previewItem);
            }
            
            function updateImagesInput() {
                imagesInput.value = selectedImages[0] || '';
            }
        });
        
        function removeImage(button) {
            const previewItem = button.closest('.image-preview-item');
            const filename = previewItem.getAttribute('data-filename');
            
            // Clear selectedImages array
            selectedImages = [];
            
            // Update hidden input
            document.getElementById('images').value = '';
            
            // Remove preview item
            previewItem.remove();
        }
        
        function deleteProduct(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteForm').submit();
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const description = document.getElementById('description').value.trim();
            const quantity = document.getElementById('quantity').value;
            
            if (!nom) {
                alert('Product name is required');
                e.preventDefault();
                return;
            }
            
            if (!description) {
                alert('Product description is required');
                e.preventDefault();
                return;
            }
            
            if (quantity < 0) {
                alert('Quantity cannot be negative');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>