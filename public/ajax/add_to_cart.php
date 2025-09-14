<?php
/**
 * Add to Cart AJAX Handler
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../src/models/Product.php';

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_id']) || !isset($input['quantity'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$productId = (int)$input['product_id'];
$quantity = max(1, (int)$input['quantity']);

try {
    $productModel = new Product();
    $product = $productModel->readById($productId);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    if ($product['quantity'] < $quantity) {
        echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
        exit;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add or update cart item
    if (isset($_SESSION['cart'][$productId])) {
        $newQuantity = $_SESSION['cart'][$productId]['quantity'] + $quantity;
        
        if ($newQuantity > $product['quantity']) {
            echo json_encode(['success' => false, 'error' => 'Not enough stock available']);
            exit;
        }
        
        $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'quantity' => $quantity,
            'added_at' => time()
        ];
    }
    
    // Calculate cart count
    $cartCount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cartCount
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>