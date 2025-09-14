<?php

/**
 * Admin Orders Management Page
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../src/models/Order.php';
require_once __DIR__ . '/../../src/models/Product.php';

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$orderModel = new Order();
$productModel = new Product();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                try {
                    $orderId = (int)$_POST['order_id'];
                    $newStatus = $_POST['status'];

                    $updated = $orderModel->update($orderId, ['status' => $newStatus]);
                    if ($updated > 0) {
                        $message = "Order status updated successfully!";
                        $messageType = "success";
                    } else {
                        $message = "No changes made to the order.";
                        $messageType = "info";
                    }
                } catch (Exception $e) {
                    $message = "Error updating order: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete':
                try {
                    $orderId = (int)$_POST['order_id'];
                    $deleted = $orderModel->delete($orderId);
                    if ($deleted > 0) {
                        $message = "Order deleted successfully! Stock has been restored.";
                        $messageType = "success";
                    } else {
                        $message = "Order not found.";
                        $messageType = "error";
                    }
                } catch (Exception $e) {
                    $message = "Error deleting order: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get orders with search and filtering
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if ($search) {
    $allOrders = $orderModel->search($search);
    $totalOrders = count($allOrders);
    $orders = array_slice($allOrders, $offset, $limit);
} elseif ($status) {
    $allOrders = $orderModel->getByStatus($status);
    $totalOrders = count($allOrders);
    $orders = array_slice($allOrders, $offset, $limit);
} else {
    $orders = $orderModel->readAll($limit, $offset);
    $totalOrders = $orderModel->getCount();
}

$totalPages = ceil($totalOrders / $limit);

// Get order details if requested
$orderDetails = null;
if (isset($_GET['view'])) {
    $orderDetails = $orderModel->readById((int)$_GET['view']);
}

// Get statistics
$statusStats = [
    'pending' => count($orderModel->getByStatus('pending')),
    'processing' => count($orderModel->getByStatus('processing')),
    'shipped' => count($orderModel->getByStatus('shipped')),
    'delivered' => count($orderModel->getByStatus('delivered')),
    'cancelled' => count($orderModel->getByStatus('cancelled'))
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Vegas Shop Admin</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            z-index: 1000;
        }

        .sidebar .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            background-color: rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .header-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-bar input,
        .search-bar select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .search-bar input {
            width: 250px;
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

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card .icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .stat-card.pending .icon {
            color: #ffc107;
        }

        .stat-card.processing .icon {
            color: #17a2b8;
        }

        .stat-card.shipped .icon {
            color: #007bff;
        }

        .stat-card.delivered .icon {
            color: #28a745;
        }

        .stat-card.cancelled .icon {
            color: #dc3545;
        }

        .stat-card h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #333;
        }

        .stat-card p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.processing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status.shipped {
            background: #cce5ff;
            color: #004085;
        }

        .status.delivered {
            background: #d4edda;
            color: #155724;
        }

        .status.cancelled {
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
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
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

        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .order-info h4 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }

        .order-info p {
            margin-bottom: 8px;
            color: #666;
        }

        .order-info strong {
            color: #333;
        }

        .order-items {
            margin-top: 20px;
        }

        .order-items h4 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #28a745;
        }

        .item-list {
            list-style: none;
        }

        .item-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .item-list li:last-child {
            border-bottom: none;
        }

        .item-info h5 {
            color: #333;
            margin-bottom: 5px;
        }

        .item-info p {
            color: #666;
            font-size: 14px;
        }

        .item-price {
            text-align: right;
        }

        .item-price .unit-price {
            color: #666;
            font-size: 14px;
        }

        .item-price .total-price {
            color: #333;
            font-weight: 600;
            font-size: 16px;
        }

        .order-total {
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }

        .order-total h3 {
            color: #333;
            font-size: 24px;
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
                align-items: stretch;
            }

            .header-controls {
                justify-content: center;
            }

            .search-bar {
                flex-direction: column;
                width: 100%;
            }

            .search-bar input {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 12px;
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
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a></li>
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
            <h1>Orders Management</h1>
            <div class="header-controls">
                <div class="search-bar">
                    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search || $status): ?>
                            <a href="orders.php" class="btn btn-warning">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Status Statistics -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3><?php echo $statusStats['pending']; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card processing">
                <div class="icon"><i class="fas fa-cog"></i></div>
                <h3><?php echo $statusStats['processing']; ?></h3>
                <p>Processing</p>
            </div>
            <div class="stat-card shipped">
                <div class="icon"><i class="fas fa-shipping-fast"></i></div>
                <h3><?php echo $statusStats['shipped']; ?></h3>
                <p>Shipped</p>
            </div>
            <div class="stat-card delivered">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3><?php echo $statusStats['delivered']; ?></h3>
                <p>Delivered</p>
            </div>
            <div class="stat-card cancelled">
                <div class="icon"><i class="fas fa-times-circle"></i></div>
                <h3><?php echo $statusStats['cancelled']; ?></h3>
                <p>Cancelled</p>
            </div>
        </div>

        <!-- Orders List -->
        <div class="card">
            <div class="card-header">
                <h3>Orders List (<?php echo $totalOrders; ?> total)</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                    <td><?php echo count($order['items']); ?> items</td>
                                    <td><strong><?php echo number_format($order['total_amount'], 0); ?> DZD</strong></td>
                                    <td><span class="status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="download_receipt.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm" title="Download Receipt">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button onclick="viewOrder(<?php echo $order['id']; ?>)" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="updateStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_code']); ?>')" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: #ddd;"></i>
                                    <h4>No orders found</h4>
                                    <p><?php echo $search ? 'No orders match your search criteria.' : 'No orders available.'; ?></p>
                                    <?php if ($search || $status): ?>
                                        <a href="orders.php" class="btn btn-primary" style="margin-top: 15px;">
                                            <i class="fas fa-refresh"></i> Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details</h3>
                <span class="close" onclick="closeModal('orderModal')">&times;</span>
            </div>
            <div id="orderModalContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId">

                <div style="margin-bottom: 20px;">
                    <label for="statusSelect" style="display: block; margin-bottom: 10px; font-weight: 600;">New Status:</label>
                    <select name="status" id="statusSelect" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('statusModal')" class="btn btn-warning" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="order_id" id="deleteOrderId">
    </form>

    <script>
        function viewOrder(orderId) {
            // Make AJAX request to get order details
            fetch(`ajax/get_order.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderDetails(data.order);
                        openModal('orderModal');
                    } else {
                        alert('Error loading order details: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading order details');
                });
        }

        function displayOrderDetails(order) {
            let itemsHtml = '';
            let totalAmount = 0;

            order.items.forEach(item => {
                itemsHtml += `
                    <li>
                        <div class="item-info">
                            <h5>${item.product_name}</h5>
                            <p>Quantity: ${item.quantity}</p>
                        </div>
                        <div class="item-price">
                            <div class="unit-price">${parseFloat(item.unit_price).toFixed(0)} DZD each</div>
                            <div class="total-price">${parseFloat(item.total_price).toFixed(0)} DZD</div>
                        </div>
                    </li>
                `;
                totalAmount += parseFloat(item.total_price);
            });

            const orderDetailsHtml = `
                <div class="order-details">
                    <div class="order-info">
                        <h4>Order Information</h4>
                        <p><strong>Order Code:</strong> ${order.order_code}</p>
                        <p><strong>Status:</strong> <span class="status ${order.status}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></p>
                        <p><strong>Order Date:</strong> ${new Date(order.created_at).toLocaleDateString()}</p>
                        <p><strong>Total Amount:</strong> <strong>${parseFloat(order.total_amount).toFixed(0)} DZD</strong></p>
                        ${order.notes ? `<p><strong>Notes:</strong> ${order.notes}</p>` : ''}
                    </div>
                    
                    <div class="order-info">
                        <h4>Customer Information</h4>
                        <p><strong>Name:</strong> ${order.customer_name}</p>
                        <p><strong>Phone:</strong> ${order.customer_phone}</p>
                        <p><strong>Address:</strong> ${order.customer_address}</p>
                    </div>
                </div>
                
                <div class="order-items">
                    <h4>Order Items (${order.items.length} items)</h4>
                    <ul class="item-list">
                        ${itemsHtml}
                    </ul>
                    <div class="order-total">
                        <h3>Total: ${parseFloat(order.total_amount).toFixed(0)} DZD</h3>
                    </div>
                </div>
            `;

            document.getElementById('orderModalContent').innerHTML = orderDetailsHtml;
        }

        function updateStatus(orderId, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('statusSelect').value = currentStatus;
            openModal('statusModal');
        }

        function deleteOrder(orderId, orderCode) {
            if (confirm(`Are you sure you want to delete order "${orderCode}"? This will restore the product stock. This action cannot be undone.`)) {
                document.getElementById('deleteOrderId').value = orderId;
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
    </script>
</body>

</html>