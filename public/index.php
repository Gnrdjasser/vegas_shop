<?php
/**
 * Vegas Shop - Customer Frontend
 */

define('VEGAS_SHOP_ACCESS', true);

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/utils/SecurityHeaders.php';

// Set security headers
\VegasShop\Utils\SecurityHeaders::setAll();

// Force HTTPS in production
if (($_ENV['ENVIRONMENT'] ?? 'development') === 'production') {
    \VegasShop\Utils\SecurityHeaders::forceHttps();
}

session_start();

$productModel = new Product();

// Get products by category
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

if ($search) {
    $products = $productModel->search($search);
} else {
    $products = $productModel->readInStock();
}

// Filter products by category (bags or caps)
$bags = [];
$caps = [];

foreach ($products as $product) {
    $productName = strtolower($product['nom']);
    $productDescription = strtolower($product['description']);
    
    if (strpos($productName, 'bag') !== false || strpos($productDescription, 'bag') !== false) {
        $bags[] = $product;
    } elseif (strpos($productName, 'cap') !== false || strpos($productName, 'hat') !== false || 
              strpos($productDescription, 'cap') !== false || strpos($productDescription, 'hat') !== false) {
        $caps[] = $product;
    }
}

// If category filter is applied
if ($category === 'bags') {
    $products = $bags;
} elseif ($category === 'caps') {
    $products = $caps;
}

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vegas Shop - Premium Bags & Caps</title>
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

        /* Header & Navigation */
        .header {
            background: black;
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
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

        .logo i {
            font-size: 32px;
        }

        .search-bar {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
        }

        .search-bar button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: black;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-bar button:hover {
            background: #333;
        }

        .cart-icon {
            position: relative;
            color: white;
            text-decoration: none;
            font-size: 24px;
            padding: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            transition: background 0.3s ease;
        }

        .cart-icon:hover {
            background: rgba(255,255,255,0.2);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Category Navigation */
        .category-nav {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .category-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .category-btn {
            padding: 12px 30px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .category-btn:hover,
        .category-btn.active {
            background: black;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23000000" width="1200" height="600"/><circle fill="%23333333" cx="200" cy="150" r="100" opacity="0.3"/><circle fill="%23000000" cx="800" cy="400" r="150" opacity="0.2"/></svg>');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 80px 20px;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 50px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-header h2 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }

        .section-header p {
            font-size: 18px;
            color: #666;
        }

        /* Product Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .product-image {
            height: 250px;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #999;
            position: relative;
            overflow: hidden;
        }

        .product-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image::before {
            transform: translateX(100%);
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .current-price {
            font-size: 24px;
            font-weight: bold;
            color: black;
        }

        .original-price {
            font-size: 18px;
            color: #999;
            text-decoration: line-through;
        }

        .discount-badge {
            background: #ff4757;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: black;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: black;
            border: 2px solid black;
        }

        .btn-outline:hover {
            background: black;
            color: white;
        }

        .stock-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .in-stock {
            background: #2ed573;
            color: white;
        }

        .low-stock {
            background: #ffa502;
            color: white;
        }

        .out-of-stock {
            background: #ff4757;
            color: white;
        }

        /* Categories Section */
        .categories-section {
            background: white;
            padding: 60px 20px;
            margin: 50px 0;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .category-card {
            text-align: center;
            padding: 40px 20px;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .category-card.bags {
            background: linear-gradient(135deg, black 0%, #333 100%);
            color: white;
        }

        .category-card.caps {
            background: linear-gradient(135deg, black 0%, #333 100%);
            color: white;
        }

        .category-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .category-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .category-count {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px 20px;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: black;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-direction: column;
                gap: 10px;
            }

            .search-bar {
                order: 3;
                max-width: 100%;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 18px;
            }

            .hero-stats {
                gap: 30px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .categories-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .category-container {
                gap: 15px;
            }

            .category-btn {
                padding: 10px 20px;
                font-size: 14px;
            }
        }

        /* Loading Animation */
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid black;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 16px;
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
            
            <div class="search-bar">
                <form method="GET" action="index.php">
                    <input type="text" name="search" placeholder="Search for bags, caps, and more..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <div class="nav-links">
                <a href="order_tracking.php" class="nav-link">
                    <i class="fas fa-search"></i> Track Order
                </a>
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-count"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>

    <!-- Category Navigation -->
    <nav class="category-nav">
        <div class="category-container">
            <a href="index.php" class="category-btn <?php echo !$category ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> All Products
            </a>
            <a href="index.php?category=bags" class="category-btn <?php echo $category === 'bags' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i> Bags (<?php echo count($bags); ?>)
            </a>
            <a href="index.php?category=caps" class="category-btn <?php echo $category === 'caps' ? 'active' : ''; ?>">
                <i class="fas fa-hat-cowboy"></i> Caps (<?php echo count($caps); ?>)
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <?php if (!$search && !$category): ?>
    <section class="hero">
        <h1>Premium Bags & Caps</h1>
        <p>Discover our exclusive collection of high-quality bags and stylish caps. Perfect for every occasion and lifestyle.</p>
        
        <div class="hero-stats">
            <div class="stat">
                <span class="stat-number"><?php echo count($products); ?></span>
                <span class="stat-label">Products Available</span>
            </div>
            <div class="stat">
                <span class="stat-number"><?php echo count($bags); ?></span>
                <span class="stat-label">Premium Bags</span>
            </div>
            <div class="stat">
                <span class="stat-number"><?php echo count($caps); ?></span>
                <span class="stat-label">Stylish Caps</span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($search): ?>
        <div class="section-header">
            <h2>Search Results</h2>
            <p>Found <?php echo count($products); ?> products for "<?php echo htmlspecialchars($search); ?>"</p>
        </div>
        <?php elseif ($category): ?>
        <div class="section-header">
            <h2><?php echo ucfirst($category); ?></h2>
            <p>Explore our collection of premium <?php echo $category; ?></p>
        </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php 
                    // Handle single image string
                    if (!empty($product['image'])) {
                        $imagePath = 'uploads/products/' . $product['image'];
                        if (file_exists(__DIR__ . '/' . $imagePath)): 
                    ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-image"></i>
                    <?php endif; 
                    } else { ?>
                        <i class="fas fa-image"></i>
                    <?php } ?>
                </div>
                
                <div class="stock-status <?php 
                    if ($product['quantity'] > 10) echo 'in-stock';
                    elseif ($product['quantity'] > 0) echo 'low-stock';
                    else echo 'out-of-stock';
                ?>">
                    <?php 
                    if ($product['quantity'] > 10) echo 'In Stock';
                    elseif ($product['quantity'] > 0) echo 'Low Stock';
                    else echo 'Out of Stock';
                    ?>
                </div>
                
                <div class="product-info">
                    <h3 class="product-name"><?php echo htmlspecialchars($product['nom']); ?></h3>
                    <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                    
                    <div class="product-price">
                        <?php if ($product['prix_sold'] && $product['prix_sold'] < $product['prix_original']): ?>
                            <span class="current-price"><?php echo number_format($product['prix_sold'], 0); ?> DZD</span>
                            <span class="original-price"><?php echo number_format($product['prix_original'], 0); ?> DZD</span>
                            <?php 
                            $discount = round((($product['prix_original'] - $product['prix_sold']) / $product['prix_original']) * 100);
                            ?>
                            <span class="discount-badge">-<?php echo $discount; ?>%</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo number_format($product['prix_sold'] ?: $product['prix_original'], 0); ?> DZD</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-actions">
                        <?php if ($product['quantity'] > 0): ?>
                        <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['nom']); ?>', <?php echo $product['prix_sold'] ?: $product['prix_original']; ?>)" class="btn btn-primary">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            <i class="fas fa-times"></i> Out of Stock
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Products Found</h3>
            <p><?php echo $search ? 'Try searching with different keywords.' : 'No products available in this category.'; ?></p>
        </div>
        <?php endif; ?>

        <!-- Categories Section (only show on homepage) -->
        <?php if (!$search && !$category): ?>
        <section class="categories-section">
            <div class="section-header">
                <h2>Shop by Category</h2>
                <p>Find exactly what you're looking for</p>
            </div>
            
            <div class="categories-grid">
                <a href="index.php?category=bags" class="category-card bags">
                    <div class="category-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3 class="category-title">Premium Bags</h3>
                    <p class="category-count"><?php echo count($bags); ?> products available</p>
                </a>
                
                <a href="index.php?category=caps" class="category-card caps">
                    <div class="category-icon">
                        <i class="fas fa-hat-cowboy"></i>
                    </div>
                    <h3 class="category-title">Stylish Caps</h3>
                    <p class="category-count"><?php echo count($caps); ?> products available</p>
                </a>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="#about">About Us</a>
                <a href="#contact">Contact</a>
                <a href="#privacy">Privacy Policy</a>
                <a href="#terms">Terms of Service</a>
                <a href="admin/login.php">Admin</a>
            </div>
            <p>&copy; 2024 Vegas Shop. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Add to cart functionality
        function addToCart(productId, productName, price) {
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    updateCartCount();
                    
                    // Show success message
                    showNotification(`${productName} added to cart!`, 'success');
                } else {
                    showNotification(data.error || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding to cart', 'error');
            });
        }

        // Update cart count
        function updateCartCount() {
            fetch('ajax/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartIcon = document.querySelector('.cart-icon');
                    const existingCount = cartIcon.querySelector('.cart-count');
                    
                    if (data.count > 0) {
                        if (existingCount) {
                            existingCount.textContent = data.count;
                        } else {
                            const countSpan = document.createElement('span');
                            countSpan.className = 'cart-count';
                            countSpan.textContent = data.count;
                            cartIcon.appendChild(countSpan);
                        }
                    } else if (existingCount) {
                        existingCount.remove();
                    }
                });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                ${type === 'success' ? 'background: #2ed573;' : 'background: #ff4757;'}
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-bar input');
            
            // Auto-focus search on '/' key
            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && !searchInput.matches(':focus')) {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        });
    </script>
</body>
</html>