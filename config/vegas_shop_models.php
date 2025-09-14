<?php
/**
 * Vegas Shop Models
 * PHP classes for working with the Produits and Command tables
 */

require_once __DIR__ . '/connection.php';

/**
 * Product Model - Handles operations for the 'produits' table
 */
class ProductModel
{
    private $db;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }
    
    /**
     * Get all products
     * 
     * @param int $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array
     */
    public function getAllProducts($limit = null, $offset = 0)
    {
        $sql = "SELECT * FROM produits ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            return $this->db->select($sql, ['limit' => $limit, 'offset' => $offset]);
        }
        
        return $this->db->select($sql);
    }
    
    /**
     * Get products that are in stock
     * 
     * @return array
     */
    public function getAvailableProducts()
    {
        return $this->db->select("SELECT * FROM produits WHERE quantity > 0 ORDER BY nom ASC");
    }
    
    /**
     * Get product by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getProductById($id)
    {
        return $this->db->selectOne("SELECT * FROM produits WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Search products by name or description
     * 
     * @param string $searchTerm
     * @return array
     */
    public function searchProducts($searchTerm)
    {
        $searchTerm = '%' . $searchTerm . '%';
        return $this->db->select(
            "SELECT * FROM produits 
             WHERE nom LIKE :search OR description LIKE :search 
             ORDER BY nom ASC",
            ['search' => $searchTerm]
        );
    }
    
    /**
     * Add new product
     * 
     * @param array $productData
     * @return string Last insert ID
     */
    public function addProduct($productData)
    {
        // Validate required fields
        $required = ['nom', 'description', 'quantity'];
        foreach ($required as $field) {
            if (empty($productData[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Sanitize input
        $data = [
            'nom' => SecurityHelper::sanitizeInput($productData['nom']),
            'description' => SecurityHelper::sanitizeInput($productData['description']),
            'prix_original' => $productData['prix_original'] ?? null,
            'prix_sold' => $productData['prix_sold'] ?? null,
            'quantity' => (int)$productData['quantity'],
            'image' => $productData['image'] ?? null
        ];
        
        return $this->db->insert(
            "INSERT INTO produits (nom, description, prix_original, prix_sold, quantity, image) 
             VALUES (:nom, :description, :prix_original, :prix_sold, :quantity, :image)",
            $data
        );
    }
    
    /**
     * Update product
     * 
     * @param int $id
     * @param array $productData
     * @return int Number of affected rows
     */
    public function updateProduct($id, $productData)
    {
        $updateFields = [];
        $params = ['id' => $id];
        
        // Build dynamic update query
        $allowedFields = ['nom', 'description', 'prix_original', 'prix_sold', 'quantity', 'image'];
        
        foreach ($allowedFields as $field) {
            if (isset($productData[$field])) {
                $updateFields[] = "$field = :$field";
                
                if ($field === 'image') {
                    $params[$field] = json_encode($productData[$field]);
                } elseif (in_array($field, ['nom', 'description'])) {
                    $params[$field] = SecurityHelper::sanitizeInput($productData[$field]);
                } else {
                    $params[$field] = $productData[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        }
        
        $sql = "UPDATE produits SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete product
     * 
     * @param int $id
     * @return int Number of affected rows
     */
    public function deleteProduct($id)
    {
        return $this->db->update("DELETE FROM produits WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Update product stock
     * 
     * @param int $id
     * @param int $newQuantity
     * @return int Number of affected rows
     */
    public function updateStock($id, $newQuantity)
    {
        return $this->db->update(
            "UPDATE produits SET quantity = :quantity WHERE id = :id",
            ['id' => $id, 'quantity' => $newQuantity]
        );
    }
    
    /**
     * Decrease product stock (for orders)
     * 
     * @param int $id
     * @param int $quantity
     * @return bool Success
     */
    public function decreaseStock($id, $quantity = 1)
    {
        $affected = $this->db->update(
            "UPDATE produits SET quantity = quantity - :quantity 
             WHERE id = :id AND quantity >= :quantity",
            ['id' => $id, 'quantity' => $quantity]
        );
        
        return $affected > 0;
    }
    
    /**
     * Get products with low stock
     * 
     * @param int $threshold
     * @return array
     */
    public function getLowStockProducts($threshold = 5)
    {
        return $this->db->select(
            "SELECT * FROM produits WHERE quantity <= :threshold ORDER BY quantity ASC",
            ['threshold' => $threshold]
        );
    }
    
    /**
     * Get product sales statistics
     * 
     * @param int $productId
     * @return array
     */
    public function getProductStats($productId)
    {
        return $this->db->selectOne(
            "SELECT 
                p.nom,
                p.quantity as current_stock,
                COUNT(c.id) as total_orders,
                SUM(c.price) as total_revenue,
                AVG(c.price) as average_order_value,
                MIN(c.created_at) as first_order,
                MAX(c.created_at) as last_order
             FROM produits p
             LEFT JOIN command c ON p.id = c.product_id
             WHERE p.id = :id
             GROUP BY p.id",
            ['id' => $productId]
        );
    }
}

/**
 * Command Model - Handles operations for the 'command' table
 */
class CommandModel
{
    private $db;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }
    
    /**
     * Get all orders
     * 
     * @param int $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array
     */
    public function getAllOrders($limit = null, $offset = 0)
    {
        $sql = "SELECT c.*, p.nom as product_name 
                FROM command c 
                JOIN produits p ON c.product_id = p.id 
                ORDER BY c.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            return $this->db->select($sql, ['limit' => $limit, 'offset' => $offset]);
        }
        
        return $this->db->select($sql);
    }
    
    /**
     * Get order by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getOrderById($id)
    {
        return $this->db->selectOne(
            "SELECT c.*, p.nom as product_name, p.description as product_description
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             WHERE c.id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Get order by order code
     * 
     * @param string $orderCode
     * @return array|false
     */
    public function getOrderByCode($orderCode)
    {
        return $this->db->selectOne(
            "SELECT c.*, p.nom as product_name, p.description as product_description
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             WHERE c.order_code = :order_code",
            ['order_code' => $orderCode]
        );
    }
    
    /**
     * Search orders by customer name or phone
     * 
     * @param string $searchTerm
     * @return array
     */
    public function searchOrders($searchTerm)
    {
        $searchTerm = '%' . $searchTerm . '%';
        return $this->db->select(
            "SELECT c.*, p.nom as product_name 
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             WHERE c.name LIKE :search OR c.phone LIKE :search OR c.order_code LIKE :search
             ORDER BY c.created_at DESC",
            ['search' => $searchTerm]
        );
    }
    
    /**
     * Place new order
     * 
     * @param array $orderData
     * @return string Last insert ID
     */
    public function placeOrder($orderData)
    {
        // Validate required fields
        $required = ['product_id', 'name', 'phone', 'adress', 'price'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Check if product exists and is in stock
        $productModel = new ProductModel();
        $product = $productModel->getProductById($orderData['product_id']);
        
        if (!$product) {
            throw new InvalidArgumentException("Product not found");
        }
        
        if ($product['quantity'] <= 0) {
            throw new InvalidArgumentException("Product is out of stock");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Generate order code if not provided
            $orderCode = $orderData['order_code'] ?? $this->generateOrderCode();
            
            // Sanitize input
            $data = [
                'product_id' => (int)$orderData['product_id'],
                'order_code' => $orderCode,
                'name' => SecurityHelper::sanitizeInput($orderData['name']),
                'phone' => preg_replace('/[^0-9]/', '', $orderData['phone']), // Keep only numbers
                'adress' => SecurityHelper::sanitizeInput($orderData['adress']),
                'price' => (int)$orderData['price']
            ];
            
            // Insert order
            $orderId = $this->db->insert(
                "INSERT INTO command (product_id, order_code, name, phone, adress, price) 
                 VALUES (:product_id, :order_code, :name, :phone, :adress, :price)",
                $data
            );
            
            // Decrease product stock
            if (!$productModel->decreaseStock($orderData['product_id'], 1)) {
                throw new Exception("Failed to update product stock");
            }
            
            $this->db->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update order
     * 
     * @param int $id
     * @param array $orderData
     * @return int Number of affected rows
     */
    public function updateOrder($id, $orderData)
    {
        $updateFields = [];
        $params = ['id' => $id];
        
        // Build dynamic update query
        $allowedFields = ['name', 'phone', 'adress', 'price'];
        
        foreach ($allowedFields as $field) {
            if (isset($orderData[$field])) {
                $updateFields[] = "$field = :$field";
                
                if ($field === 'phone') {
                    $params[$field] = preg_replace('/[^0-9]/', '', $orderData[$field]);
                } elseif (in_array($field, ['name', 'adress'])) {
                    $params[$field] = SecurityHelper::sanitizeInput($orderData[$field]);
                } else {
                    $params[$field] = $orderData[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        }
        
        $sql = "UPDATE command SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete order
     * 
     * @param int $id
     * @return int Number of affected rows
     */
    public function deleteOrder($id)
    {
        // Get order details before deletion to restore stock
        $order = $this->getOrderById($id);
        
        if ($order) {
            try {
                $this->db->beginTransaction();
                
                // Delete order
                $affected = $this->db->update("DELETE FROM command WHERE id = :id", ['id' => $id]);
                
                // Restore product stock
                if ($affected > 0) {
                    $productModel = new ProductModel();
                    $productModel->updateStock(
                        $order['product_id'], 
                        $order['quantity'] + 1 // Assuming 1 item per order
                    );
                }
                
                $this->db->commit();
                return $affected;
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
        }
        
        return 0;
    }
    
    /**
     * Get orders by customer phone
     * 
     * @param string $phone
     * @return array
     */
    public function getOrdersByPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return $this->db->select(
            "SELECT c.*, p.nom as product_name 
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             WHERE c.phone = :phone 
             ORDER BY c.created_at DESC",
            ['phone' => $phone]
        );
    }
    
    /**
     * Get orders by product ID
     * 
     * @param int $productId
     * @return array
     */
    public function getOrdersByProduct($productId)
    {
        return $this->db->select(
            "SELECT c.*, p.nom as product_name 
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             WHERE c.product_id = :product_id 
             ORDER BY c.created_at DESC",
            ['product_id' => $productId]
        );
    }
    
    /**
     * Get sales statistics
     * 
     * @param string $period 'today', 'week', 'month', 'year'
     * @return array
     */
    public function getSalesStats($period = 'month')
    {
        $dateCondition = '';
        
        switch ($period) {
            case 'today':
                $dateCondition = "WHERE DATE(c.created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $dateCondition = "WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $dateCondition = "WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        return $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(c.price) as total_revenue,
                AVG(c.price) as average_order_value,
                MIN(c.created_at) as first_order,
                MAX(c.created_at) as last_order
             FROM command c $dateCondition"
        );
    }
    
    /**
     * Generate unique order code
     * 
     * @return string
     */
    private function generateOrderCode()
    {
        $date = date('Ymd');
        
        // Get count of orders today
        $count = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM command WHERE DATE(created_at) = CURDATE()"
        );
        
        $counter = ($count['count'] ?? 0) + 1;
        
        return "ORD-{$date}-" . str_pad($counter, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get recent orders
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentOrders($limit = 10)
    {
        return $this->db->select(
            "SELECT c.*, p.nom as product_name 
             FROM command c 
             JOIN produits p ON c.product_id = p.id 
             ORDER BY c.created_at DESC 
             LIMIT :limit",
            ['limit' => $limit]
        );
    }
}

/**
 * Dashboard Model - Provides summary statistics
 */
class DashboardModel
{
    private $db;
    private $productModel;
    private $commandModel;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
        $this->productModel = new ProductModel();
        $this->commandModel = new CommandModel();
    }
    
    /**
     * Get dashboard summary
     * 
     * @return array
     */
    public function getDashboardSummary()
    {
        // Get basic counts
        $totalProducts = $this->db->selectOne("SELECT COUNT(*) as count FROM produits")['count'];
        $totalOrders = $this->db->selectOne("SELECT COUNT(*) as count FROM command")['count'];
        $lowStockProducts = count($this->productModel->getLowStockProducts());
        
        // Get sales stats
        $todayStats = $this->commandModel->getSalesStats('today');
        $monthStats = $this->commandModel->getSalesStats('month');
        
        // Get top selling products
        $topProducts = $this->db->select(
            "SELECT p.nom, COUNT(c.id) as order_count, SUM(c.price) as revenue
             FROM produits p
             LEFT JOIN command c ON p.id = c.product_id
             GROUP BY p.id, p.nom
             ORDER BY order_count DESC
             LIMIT 5"
        );
        
        return [
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'low_stock_products' => $lowStockProducts,
            'today_orders' => $todayStats['total_orders'] ?? 0,
            'today_revenue' => $todayStats['total_revenue'] ?? 0,
            'month_orders' => $monthStats['total_orders'] ?? 0,
            'month_revenue' => $monthStats['total_revenue'] ?? 0,
            'top_products' => $topProducts
        ];
    }
}