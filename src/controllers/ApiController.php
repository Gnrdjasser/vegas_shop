<?php
/**
 * API Controller
 * Handles HTTP requests and uses Product and Order models
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Order.php';

class ApiController
{
    private $productModel;
    private $orderModel;
    
    public function __construct()
    {
        $this->productModel = new Product();
        $this->orderModel = new Order();
        
        // Set JSON response header
        header('Content-Type: application/json');
        
        // Enable CORS for API access
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Route requests to appropriate methods
     */
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        try {
            // Remove 'api' from path if present
            if ($pathParts[0] === 'api') {
                array_shift($pathParts);
            }
            
            $resource = $pathParts[0] ?? '';
            $id = $pathParts[1] ?? null;
            $action = $pathParts[2] ?? null;
            
            switch ($resource) {
                case 'products':
                    $this->handleProductRequests($method, $id, $action);
                    break;
                    
                case 'orders':
                    $this->handleOrderRequests($method, $id, $action);
                    break;
                    
                case 'dashboard':
                    $this->handleDashboardRequests($method, $id);
                    break;
                    
                default:
                    $this->sendResponse(['error' => 'Invalid endpoint'], 404);
            }
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Handle product-related requests
     */
    private function handleProductRequests($method, $id, $action)
    {
        switch ($method) {
            case 'GET':
                if ($id) {
                    if ($action === 'stats') {
                        $this->getProductStats($id);
                    } else {
                        $this->getProduct($id);
                    }
                } else {
                    $this->getProducts();
                }
                break;
                
            case 'POST':
                $this->createProduct();
                break;
                
            case 'PUT':
                if ($id) {
                    $this->updateProduct($id);
                } else {
                    $this->sendResponse(['error' => 'Product ID required'], 400);
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteProduct($id);
                } else {
                    $this->sendResponse(['error' => 'Product ID required'], 400);
                }
                break;
                
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Handle order-related requests
     */
    private function handleOrderRequests($method, $id, $action)
    {
        switch ($method) {
            case 'GET':
                if ($id) {
                    if ($action === 'by-code') {
                        $this->getOrderByCode($id);
                    } else {
                        $this->getOrder($id);
                    }
                } else {
                    $this->getOrders();
                }
                break;
                
            case 'POST':
                $this->createOrder();
                break;
                
            case 'PUT':
                if ($id) {
                    $this->updateOrder($id);
                } else {
                    $this->sendResponse(['error' => 'Order ID required'], 400);
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteOrder($id);
                } else {
                    $this->sendResponse(['error' => 'Order ID required'], 400);
                }
                break;
                
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Handle dashboard requests
     */
    private function handleDashboardRequests($method, $type)
    {
        if ($method !== 'GET') {
            $this->sendResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        switch ($type) {
            case 'stats':
                $this->getDashboardStats();
                break;
            case 'sales':
                $this->getSalesStats();
                break;
            default:
                $this->sendResponse(['error' => 'Invalid dashboard endpoint'], 404);
        }
    }
    
    // ========================================
    // PRODUCT METHODS
    // ========================================
    
    private function getProducts()
    {
        $limit = $_GET['limit'] ?? null;
        $offset = $_GET['offset'] ?? 0;
        $search = $_GET['search'] ?? null;
        $inStock = $_GET['in_stock'] ?? null;
        $minPrice = $_GET['min_price'] ?? null;
        $maxPrice = $_GET['max_price'] ?? null;
        
        if ($search) {
            $products = $this->productModel->search($search);
        } elseif ($inStock) {
            $products = $this->productModel->readInStock();
        } elseif ($minPrice && $maxPrice) {
            $products = $this->productModel->getByPriceRange($minPrice, $maxPrice);
        } else {
            $products = $this->productModel->readAll($limit, $offset);
        }
        
        // Process images for each product
        foreach ($products as &$product) {
            $product['image'] = $product['image'] ?: null;
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $products,
            'count' => count($products)
        ]);
    }
    
    private function getProduct($id)
    {
        $product = $this->productModel->readById($id);
        
        if (!$product) {
            $this->sendResponse(['error' => 'Product not found'], 404);
            return;
        }
        
        $product['image'] = $product['image'] ?: null;
        
        $this->sendResponse([
            'success' => true,
            'data' => $product
        ]);
    }
    
    private function getProductStats($id)
    {
        $stats = $this->productModel->getStats($id);
        
        if (!$stats) {
            $this->sendResponse(['error' => 'Product not found'], 404);
            return;
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    private function createProduct()
    {
        $input = $this->getJsonInput();
        
        // Validate input
        $errors = $this->productModel->validate($input);
        if (!empty($errors)) {
            $this->sendResponse(['error' => 'Validation failed', 'details' => $errors], 400);
            return;
        }
        
        try {
            $productId = $this->productModel->create($input);
            $product = $this->productModel->readById($productId);
            $product['image'] = $product['image'] ?: null;
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
        }
    }
    
    private function updateProduct($id)
    {
        if (!$this->productModel->exists($id)) {
            $this->sendResponse(['error' => 'Product not found'], 404);
            return;
        }
        
        $input = $this->getJsonInput();
        
        try {
            $updated = $this->productModel->update($id, $input);
            
            if ($updated > 0) {
                $product = $this->productModel->readById($id);
                $product['image'] = $product['image'] ?: null;
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Product updated successfully',
                    'data' => $product
                ]);
            } else {
                $this->sendResponse(['error' => 'No changes made'], 400);
            }
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to update product: ' . $e->getMessage()], 500);
        }
    }
    
    private function deleteProduct($id)
    {
        if (!$this->productModel->exists($id)) {
            $this->sendResponse(['error' => 'Product not found'], 404);
            return;
        }
        
        try {
            $deleted = $this->productModel->delete($id);
            
            if ($deleted > 0) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ]);
            } else {
                $this->sendResponse(['error' => 'Failed to delete product'], 500);
            }
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to delete product: ' . $e->getMessage()], 500);
        }
    }
    
    // ========================================
    // ORDER METHODS
    // ========================================
    
    private function getOrders()
    {
        $limit = $_GET['limit'] ?? null;
        $offset = $_GET['offset'] ?? 0;
        $search = $_GET['search'] ?? null;
        $phone = $_GET['phone'] ?? null;
        $productId = $_GET['product_id'] ?? null;
        
        if ($search) {
            $orders = $this->orderModel->search($search);
        } elseif ($phone) {
            $orders = $this->orderModel->getByPhone($phone);
        } elseif ($productId) {
            $orders = $this->orderModel->getByProduct($productId);
        } else {
            $orders = $this->orderModel->readAll($limit, $offset);
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $orders,
            'count' => count($orders)
        ]);
    }
    
    private function getOrder($id)
    {
        $order = $this->orderModel->readById($id);
        
        if (!$order) {
            $this->sendResponse(['error' => 'Order not found'], 404);
            return;
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $order
        ]);
    }
    
    private function getOrderByCode($code)
    {
        $order = $this->orderModel->readByCode($code);
        
        if (!$order) {
            $this->sendResponse(['error' => 'Order not found'], 404);
            return;
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $order
        ]);
    }
    
    private function createOrder()
    {
        $input = $this->getJsonInput();
        
        // Validate input
        $errors = $this->orderModel->validate($input);
        if (!empty($errors)) {
            $this->sendResponse(['error' => 'Validation failed', 'details' => $errors], 400);
            return;
        }
        
        try {
            $orderId = $this->orderModel->create($input);
            $order = $this->orderModel->readById($orderId);
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }
    
    private function updateOrder($id)
    {
        if (!$this->orderModel->exists($id)) {
            $this->sendResponse(['error' => 'Order not found'], 404);
            return;
        }
        
        $input = $this->getJsonInput();
        
        try {
            $updated = $this->orderModel->update($id, $input);
            
            if ($updated > 0) {
                $order = $this->orderModel->readById($id);
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Order updated successfully',
                    'data' => $order
                ]);
            } else {
                $this->sendResponse(['error' => 'No changes made'], 400);
            }
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }
    
    private function deleteOrder($id)
    {
        if (!$this->orderModel->exists($id)) {
            $this->sendResponse(['error' => 'Order not found'], 404);
            return;
        }
        
        try {
            $deleted = $this->orderModel->delete($id);
            
            if ($deleted > 0) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Order deleted successfully'
                ]);
            } else {
                $this->sendResponse(['error' => 'Failed to delete order'], 500);
            }
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to delete order: ' . $e->getMessage()], 500);
        }
    }
    
    // ========================================
    // DASHBOARD METHODS
    // ========================================
    
    private function getDashboardStats()
    {
        $totalProducts = $this->productModel->getCount();
        $totalOrders = $this->orderModel->getCount();
        $lowStockProducts = count($this->productModel->getLowStock());
        $topProducts = $this->orderModel->getTopSellingProducts(5);
        
        $this->sendResponse([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'low_stock_products' => $lowStockProducts,
                'top_selling_products' => $topProducts
            ]
        ]);
    }
    
    private function getSalesStats()
    {
        $period = $_GET['period'] ?? 'month';
        $stats = $this->orderModel->getSalesStats($period);
        
        $this->sendResponse([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    // ========================================
    // HELPER METHODS
    // ========================================
    
    private function getJsonInput()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        return $input ?: [];
    }
    
    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
}

// Usage example - uncomment to use as API endpoint
/*
$api = new ApiController();
$api->handleRequest();
*/