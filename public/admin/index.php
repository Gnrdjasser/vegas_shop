<?php
/**
 * Admin Dashboard - Main Page
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/models/Order.php';

// Simple authentication check (in production, use proper session management)
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$productModel = new Product();
$orderModel = new Order();

// Get dashboard statistics
$totalProducts = $productModel->getCount();
$totalOrders = $orderModel->getCount();
$lowStockProducts = count($productModel->getLowStock(10));
$todayStats = $orderModel->getSalesStats('today');
$monthStats = $orderModel->getSalesStats('month');
$topProducts = $orderModel->getTopSellingProducts(5);
$recentOrders = $orderModel->readAll(5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vegas Shop - Admin Dashboard</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-card.products .icon { color: #4CAF50; }
        .stat-card.orders .icon { color: #2196F3; }
        .stat-card.revenue .icon { color: #FF9800; }
        .stat-card.low-stock .icon { color: #f44336; }

        .stat-card h3 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }

        .stat-card p {
            color: #666;
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
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

        .status.pending { background: #fff3cd; color: #856404; }
        .status.processing { background: #d1ecf1; color: #0c5460; }
        .status.shipped { background: #d4edda; color: #155724; }
        .status.delivered { background: #d1ecf1; color: #0c5460; }
        .status.cancelled { background: #f8d7da; color: #721c24; }

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

        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .top-products-list {
            list-style: none;
        }

        .top-products-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .top-products-list li:last-child {
            border-bottom: none;
        }

        .product-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .product-info p {
            color: #666;
            font-size: 14px;
        }

        .product-stats {
            text-align: right;
        }

        .product-stats .revenue {
            color: #28a745;
            font-weight: 600;
        }

        .product-stats .orders {
            color: #666;
            font-size: 14px;
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

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
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
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>Welcome, Admin</span>
                <i class="fas fa-user-circle" style="font-size: 24px; color: #666;"></i>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="products.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Product
            </a>
            <a href="orders.php" class="btn btn-success">
                <i class="fas fa-eye"></i> View Orders
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="icon"><i class="fas fa-box"></i></div>
                <h3><?php echo $totalProducts; ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card orders">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <h3><?php echo $totalOrders; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card revenue">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <h3>$<?php echo number_format($monthStats['total_revenue'] ?? 0, 0); ?></h3>
                <p>Monthly Revenue</p>
            </div>
            <div class="stat-card low-stock">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3><?php echo $lowStockProducts; ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Orders</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><span class="status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="orders.php" class="btn btn-primary">View All Orders</a>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="card">
                <div class="card-header">
                    <h3>Top Selling Products</h3>
                </div>
                <div class="card-body">
                    <ul class="top-products-list">
                        <?php foreach ($topProducts as $product): ?>
                        <li>
                            <div class="product-info">
                                <h4><?php echo htmlspecialchars($product['nom']); ?></h4>
                                <p><?php echo $product['order_count']; ?> orders</p>
                            </div>
                            <div class="product-stats">
                                <div class="revenue">$<?php echo number_format($product['total_revenue'], 2); ?></div>
                                <div class="orders"><?php echo $product['total_quantity_sold']; ?> sold</div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>