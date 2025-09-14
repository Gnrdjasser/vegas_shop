<?php
/**
 * Customer Order Tracking Page
 * Allows customers to track their orders and download receipts
 */

define('VEGAS_SHOP_ACCESS', true);

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../src/models/Order.php';

session_start();

$orderModel = new Order();
$order = null;
$error = '';
$orderCode = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_code'])) {
    $orderCode = trim($_POST['order_code']);
    
    if (empty($orderCode)) {
        $error = 'Please enter an order code.';
    } else {
        $order = $orderModel->readByCode($orderCode);
        if (!$order) {
            $error = 'Order not found. Please check your order code and try again.';
        }
    }
}

// Handle receipt download
if (isset($_GET['download']) && isset($_GET['code'])) {
    $orderCode = $_GET['code'];
    $order = $orderModel->readByCode($orderCode);
    
    if ($order) {
        // Redirect to download receipt
        header('Location: download_customer_receipt.php?code=' . urlencode($orderCode));
        exit;
    } else {
        $error = 'Order not found.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Vegas Shop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .search-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .order-details {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .info-section p {
            margin-bottom: 8px;
            color: #666;
        }

        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.pending { background: #fff3cd; color: #856404; }
        .status.processing { background: #cce5ff; color: #004085; }
        .status.shipped { background: #d1ecf1; color: #0c5460; }
        .status.delivered { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .items-table th {
            background: #e9ecef;
            font-weight: 600;
            color: #495057;
        }

        .total-section {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }

        .total-section h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search"></i> Order Tracking</h1>
            <p>Track your order status and download receipts</p>
        </div>

        <div class="content">
            <div class="search-form">
                <h2>Enter Your Order Code</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="order_code">Order Code:</label>
                        <input type="text" id="order_code" name="order_code" 
                               value="<?php echo htmlspecialchars($orderCode); ?>" 
                               placeholder="Enter your order code here..." required>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Track Order
                    </button>
                </form>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="order-details">
                    <h2>Order Details</h2>
                    
                    <div class="order-info">
                        <div class="info-section">
                            <h3>Order Information</h3>
                            <p><strong>Order Code:</strong> <?php echo htmlspecialchars($order['order_code']); ?></p>
                            <p><strong>Status:</strong> <span class="status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></p>
                            <p><strong>Order Date:</strong> <?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?></p>
                            <p><strong>Total Items:</strong> <?php echo count($order['items']); ?></p>
                        </div>
                        
                        <div class="info-section">
                            <h3>Customer Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                        </div>
                    </div>

                    <h3>Order Items</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['unit_price'], 2); ?> DZD</td>
                                    <td><?php echo number_format($item['total_price'], 2); ?> DZD</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="total-section">
                        <h3>Total Amount: <?php echo number_format($order['total_amount'], 2); ?> DZD</h3>
                    </div>

                    <div class="actions">
                        <a href="?download=1&code=<?php echo urlencode($order['order_code']); ?>" class="btn">
                            <i class="fas fa-download"></i> Download Receipt
                        </a>
                        <a href="order_tracking.php" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Track Another Order
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Shop
                </a>
            </div>
        </div>
    </div>
</body>
</html>
