<?php
/**
 * Shopping Cart Page
 */

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Order.php';

session_start();

$productModel = new Product();
$orderModel = new Order();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$messageType = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $productId = (int)$_POST['product_id'];
                $quantity = max(0, (int)$_POST['quantity']);
                
                if ($quantity > 0) {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                } else {
                    unset($_SESSION['cart'][$productId]);
                }
                break;
                
            case 'remove':
                $productId = (int)$_POST['product_id'];
                unset($_SESSION['cart'][$productId]);
                break;
                
            case 'clear':
                $_SESSION['cart'] = [];
                break;
                
            case 'checkout':
                try {
                    // Get customer information
                    $customerName = $_POST['customer_name'] ?? '';
                    $customerPhone = $_POST['customer_phone'] ?? '';
                    $customerAddress = $_POST['customer_address'] ?? '';
                    
                    if (empty($customerName) || empty($customerPhone) || empty($customerAddress)) {
                        $message = 'Please fill in all customer information fields';
                        $messageType = 'error';
                        break;
                    }
                    
                    if (empty($_SESSION['cart'])) {
                        $message = 'Your cart is empty';
                        $messageType = 'error';
                        break;
                    }
                    
                    // Prepare order data
                    $orderData = [
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'customer_address' => $customerAddress,
                        'status' => 'pending'
                    ];
                    
                    // Prepare order items
                    $orderItems = [];
                    foreach ($_SESSION['cart'] as $productId => $cartItem) {
                        $product = $productModel->readById($productId);
                        if ($product) {
                            $price = $product['prix_sold'] ?: $product['prix_original'];
                            $orderItems[] = [
                                'product_id' => $productId,
                                'quantity' => $cartItem['quantity'],
                                'unit_price' => $price
                            ];
                        }
                    }
                    
                    // Create the order
                    $orderId = $orderModel->create($orderData, $orderItems);
                    
                    // Clear the cart
                    $_SESSION['cart'] = [];
                    
                    $message = "Order placed successfully! Order ID: " . $orderId;
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $message = 'Error placing order: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get cart items with product details
$cartItems = [];
$cartTotal = 0;

foreach ($_SESSION['cart'] as $productId => $cartItem) {
    $product = $productModel->readById($productId);
    if ($product) {
        $price = $product['prix_sold'] ?: $product['prix_original'];
        $itemTotal = $price * $cartItem['quantity'];
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $cartItem['quantity'],
            'price' => $price,
            'total' => $itemTotal
        ];
        
        $cartTotal += $itemTotal;
    }
}

// Get cart count
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Vegas Shop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        /* Header */
        .header {
            background: black;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 18px;
            color: #666;
        }

        /* Cart Content */
        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .cart-items {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .cart-header h2 {
            font-size: 24px;
            color: #333;
        }

        .clear-cart-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .clear-cart-btn:hover {
            background: #ff3742;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto auto auto;
            gap: 20px;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #999;
        }

        .item-details h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }

        .item-details p {
            color: #666;
            font-size: 14px;
        }

        .item-price {
            font-size: 18px;
            font-weight: 600;
            color: black;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: black;
            color: white;
            border-color: black;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
            font-size: 16px;
        }

        .item-total {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .remove-btn {
            background: #ff4757;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        .remove-btn:hover {
            background: #ff3742;
        }

        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-header {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
            margin-top: 20px;
        }

        .checkout-btn {
            width: 100%;
            background: black;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.3s ease;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
        }

        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .continue-shopping {
            display: block;
            text-align: center;
            color: black;
            text-decoration: none;
            margin-top: 15px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .continue-shopping:hover {
            color: #333;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .empty-cart i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 15px;
        }

        .empty-cart p {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }

        .shop-now-btn {
            background: black;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .shop-now-btn:hover {
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .cart-item {
                grid-template-columns: 60px 1fr;
                gap: 15px;
                text-align: left;
            }

            .item-image {
                width: 60px;
                height: 60px;
                font-size: 20px;
            }

            .item-price,
            .quantity-controls,
            .item-total,
            .remove-btn {
                grid-column: 2;
                justify-self: start;
                margin-top: 10px;
            }

            .quantity-controls {
                margin-top: 15px;
            }

            .item-total {
                margin-top: 10px;
                font-size: 18px;
            }

            .remove-btn {
                margin-top: 15px;
                width: auto;
                height: auto;
                padding: 8px 15px;
                border-radius: 5px;
            }

            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .loading.show {
            display: flex;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid black;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal Styles */
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
            max-width: 500px;
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
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-store"></i>
                Vegas Shop
            </a>
            
            <div class="nav-links">
                <a href="index.php">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="index.php?category=bags">
                    <i class="fas fa-shopping-bag"></i> Bags
                </a>
                <a href="index.php?category=caps">
                    <i class="fas fa-hat-cowboy"></i> Caps
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>Shopping Cart</h1>
            <p><?php echo count($cartItems); ?> item(s) in your cart</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="max-width: 1200px; margin: 0 auto 20px; padding: 15px; border-radius: 8px; text-align: center;">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($cartItems)): ?>
        <div class="cart-container">
            <!-- Cart Items -->
            <div class="cart-items">
                <div class="cart-header">
                    <h2>Your Items</h2>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="clear-cart-btn" onclick="return confirm('Are you sure you want to clear your cart?')">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </form>
                </div>

                <?php foreach ($cartItems as $item): ?>
                <div class="cart-item">
                    <div class="item-image">
                        <?php 
                        // Handle single image string
                        if (!empty($item['product']['image'])) {
                            $imagePath = 'uploads/products/' . $item['product']['image'];
                            if (file_exists(__DIR__ . '/' . $imagePath)): 
                        ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($item['product']['nom']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; 
                        } else { ?>
                            <i class="fas fa-image"></i>
                        <?php } ?>
                    </div>
                    
                    <div class="item-details">
                        <h3><?php echo htmlspecialchars($item['product']['nom']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($item['product']['description'], 0, 80)) . '...'; ?></p>
                        <p><strong>Stock:</strong> <?php echo $item['product']['quantity']; ?> available</p>
                    </div>
                    
                    <div class="item-price">
                        <?php echo number_format($item['price'], 0); ?> DZD
                    </div>
                    
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                               min="1" max="<?php echo $item['product']['quantity']; ?>"
                               onchange="updateQuantity(<?php echo $item['product']['id']; ?>, this.value)">
                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="item-total">
                        <?php echo number_format($item['total'], 0); ?> DZD
                    </div>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                        <button type="submit" class="remove-btn" onclick="return confirm('Remove this item from cart?')">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Cart Summary -->
            <div class="cart-summary">
                <h3 class="summary-header">Order Summary</h3>
                
                <div class="summary-row">
                    <span>Subtotal (<?php echo $cartCount; ?> items):</span>
                    <span><?php echo number_format($cartTotal, 0); ?> DZD</span>
                </div>
                
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>Free</span>
                </div>
                
                <div class="summary-row total">
                    <span>Total:</span>
                    <span><?php echo number_format($cartTotal, 0); ?> DZD</span>
                </div>
                
                <button type="button" onclick="showCheckoutForm()" class="checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
                
                <a href="index.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Empty Cart -->
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added any items to your cart yet.</p>
            <a href="index.php" class="shop-now-btn">
                <i class="fas fa-shopping-bag"></i> Start Shopping
            </a>
        </div>
        <?php endif; ?>
    </main>

    <!-- Checkout Form Modal -->
    <div id="checkoutModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Complete Your Order</h3>
                <span class="close" onclick="closeCheckoutForm()">&times;</span>
            </div>
            <form method="POST" id="checkoutForm">
                <input type="hidden" name="action" value="checkout">
                
                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_phone">Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_address">Delivery Address *</label>
                    <textarea id="customer_address" name="customer_address" required rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeCheckoutForm()" class="btn" style="background: #6c757d; flex: 1;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1;">Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading" class="loading">
        <div class="spinner"></div>
    </div>

    <script>
        // Update quantity
        function updateQuantity(productId, newQuantity) {
            if (newQuantity < 1) {
                if (confirm('Remove this item from cart?')) {
                    removeItem(productId);
                }
                return;
            }

            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('product_id', productId);
            formData.append('quantity', newQuantity);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(() => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
            });
        }

        // Remove item
        function removeItem(productId) {
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('product_id', productId);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(() => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
            });
        }

        // Show checkout form
        function showCheckoutForm() {
            document.getElementById('checkoutModal').style.display = 'block';
        }

        // Close checkout form
        function closeCheckoutForm() {
            document.getElementById('checkoutModal').style.display = 'none';
        }

        // Show/hide loading
        function showLoading() {
            document.getElementById('loading').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loading').classList.remove('show');
        }

        // Auto-update quantity on input change
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                let timeout;
                
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    
                    timeout = setTimeout(() => {
                        const productId = this.closest('.cart-item').querySelector('input[name="product_id"]')?.value;
                        if (productId) {
                            updateQuantity(parseInt(productId), parseInt(this.value));
                        }
                    }, 1000); // Wait 1 second after user stops typing
                });
            });
        });
    </script>
</body>
</html>