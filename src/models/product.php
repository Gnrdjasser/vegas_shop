<?php
/**
 * Product Model
 * Handles CRUD operations for the 'produits' table using executeQuery
 * Updated for Many-to-Many relationship with orders
 */

require_once __DIR__ . '/../../config/connection.php';

class Product
{
    private $db;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }
    
    /**
     * Create a new product
     * 
     * @param array $data Product data
     * @return string Last insert ID
     */
    public function create($data)
    {
        $sql = "INSERT INTO produits (nom, description, prix_original, prix_sold, quantity, image) 
                VALUES (:nom, :description, :prix_original, :prix_sold, :quantity, :image)";
        
        // Handle image - now expects a single image path string
        $image = $data['image'] ?? null;
        
        $params = [
            'nom' => SecurityHelper::sanitizeInput($data['nom']),
            'description' => SecurityHelper::sanitizeInput($data['description']),
            'prix_original' => $data['prix_original'] ?? null,
            'prix_sold' => $data['prix_sold'] ?? null,
            'quantity' => (int)$data['quantity'],
            'image' => $image
        ];
        
        $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Read all products
     * 
     * @param int|null $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array
     */
    public function readAll($limit = null, $offset = 0)
    {
        $sql = "SELECT * FROM produits ORDER BY created_at DESC";
        $params = [];
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params = ['limit' => $limit, 'offset' => $offset];
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Read a single product by ID
     * 
     * @param int $id Product ID
     * @return array|false
     */
    public function readById($id)
    {
        $sql = "SELECT * FROM produits WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Read products by search term
     * 
     * @param string $searchTerm Search term
     * @return array
     */
    public function search($searchTerm)
    {
        $sql = "SELECT * FROM produits 
                WHERE nom LIKE :search1 OR description LIKE :search2 
                ORDER BY nom ASC";
        
        $searchTerm = '%' . $searchTerm . '%';
        $stmt = $this->db->executeQuery($sql, [
            'search1' => $searchTerm,
            'search2' => $searchTerm
        ]);
        return $stmt->fetchAll();
    }
    
    /**
     * Read products that are in stock
     * 
     * @return array
     */
    public function readInStock()
    {
        $sql = "SELECT * FROM produits WHERE quantity > 0 ORDER BY nom ASC";
        $stmt = $this->db->executeQuery($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Update a product
     * 
     * @param int $id Product ID
     * @param array $data Updated data
     * @return int Number of affected rows
     */
    public function update($id, $data)
    {
        $updateFields = [];
        $params = ['id' => $id];
        
        // Build dynamic update query
        $allowedFields = ['nom', 'description', 'prix_original', 'prix_sold', 'quantity', 'image'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                
                if ($field === 'image') {
                    // Image now expects a single string path
                    $params[$field] = $data[$field];
                } elseif (in_array($field, ['nom', 'description'])) {
                    $params[$field] = SecurityHelper::sanitizeInput($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        } 
        
        $sql = "UPDATE produits SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete a product
     * 
     * @param int $id Product ID
     * @return int Number of affected rows
     */
    public function delete($id)
    {
        $sql = "DELETE FROM produits WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount();
    }
    
    /**
     * Update product stock quantity
     * 
     * @param int $id Product ID
     * @param int $quantity New quantity
     * @return int Number of affected rows
     */
    public function updateStock($id, $quantity)
    {
        $sql = "UPDATE produits SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id, 'quantity' => $quantity]);
        return $stmt->rowCount();
    }
    
    /**
     * Decrease product stock (for orders)
     * 
     * @param int $id Product ID
     * @param int $quantity Quantity to decrease
     * @return bool Success
     */
    public function decreaseStock($id, $quantity = 1)
    {
        $sql = "UPDATE produits 
                SET quantity = quantity - :decrease_qty, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id AND quantity >= :min_qty";
        
        $stmt = $this->db->executeQuery($sql, [
            'id' => $id, 
            'decrease_qty' => $quantity,
            'min_qty' => $quantity
        ]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Increase product stock
     * 
     * @param int $id Product ID
     * @param int $quantity Quantity to increase
     * @return int Number of affected rows
     */
    public function increaseStock($id, $quantity = 1)
    {
        $sql = "UPDATE produits 
                SET quantity = quantity + :quantity, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->db->executeQuery($sql, ['id' => $id, 'quantity' => $quantity]);
        return $stmt->rowCount();
    }
    
    /**
     * Get products with low stock
     * 
     * @param int $threshold Stock threshold
     * @return array
     */
    public function getLowStock($threshold = 5)
    {
        $sql = "SELECT * FROM produits WHERE quantity <= :threshold ORDER BY quantity ASC";
        $stmt = $this->db->executeQuery($sql, ['threshold' => $threshold]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get product statistics (updated for many-to-many)
     * 
     * @param int $id Product ID
     * @return array|false
     */
    public function getStats($id)
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.quantity as current_stock,
                    p.prix_original,
                    p.prix_sold,
                    COUNT(DISTINCT oi.order_id) as total_orders,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.total_price) as total_revenue,
                    AVG(oi.unit_price) as average_selling_price,
                    MIN(oi.created_at) as first_sale,
                    MAX(oi.created_at) as last_sale
                FROM produits p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                WHERE p.id = :id
                GROUP BY p.id";
        
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all orders that contain this product
     * 
     * @param int $id Product ID
     * @return array
     */
    public function getOrders($id)
    {
        $sql = "SELECT 
                    o.id as order_id,
                    o.order_code,
                    o.customer_name,
                    o.customer_phone,
                    o.total_amount as order_total,
                    o.status,
                    o.created_at as order_date,
                    oi.quantity,
                    oi.unit_price,
                    oi.total_price as item_total
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE oi.product_id = :id
                ORDER BY o.created_at DESC";
        
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get customers who bought this product
     * 
     * @param int $id Product ID
     * @return array
     */
    public function getCustomers($id)
    {
        $sql = "SELECT DISTINCT
                    o.customer_name,
                    o.customer_phone,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.quantity) as total_quantity_bought,
                    SUM(oi.total_price) as total_spent_on_product,
                    MIN(o.created_at) as first_purchase,
                    MAX(o.created_at) as last_purchase
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE oi.product_id = :id
                GROUP BY o.customer_name, o.customer_phone
                ORDER BY total_spent_on_product DESC";
        
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check stock availability for multiple products
     * 
     * @param array $items Array of ['product_id' => quantity_needed]
     * @return array Array of availability status
     */
    public function checkStockAvailability($items)
    {
        $availability = [];
        
        foreach ($items as $productId => $quantityNeeded) {
            $sql = "SELECT id, nom, quantity FROM produits WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, ['id' => $productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $availability[$productId] = [
                    'available' => false,
                    'reason' => 'Product not found',
                    'current_stock' => 0,
                    'requested' => $quantityNeeded
                ];
            } elseif ($product['quantity'] < $quantityNeeded) {
                $availability[$productId] = [
                    'available' => false,
                    'reason' => 'Insufficient stock',
                    'current_stock' => $product['quantity'],
                    'requested' => $quantityNeeded,
                    'product_name' => $product['nom']
                ];
            } else {
                $availability[$productId] = [
                    'available' => true,
                    'current_stock' => $product['quantity'],
                    'requested' => $quantityNeeded,
                    'product_name' => $product['nom']
                ];
            }
        }
        
        return $availability;
    }
    
    /**
     * Decrease stock for multiple products (used in order creation)
     * 
     * @param array $items Array of ['product_id' => quantity_to_decrease]
     * @return bool Success
     */
    public function decreaseMultipleStock($items)
    {
        foreach ($items as $productId => $quantity) {
            if (!$this->decreaseStock($productId, $quantity)) {
                throw new Exception("Failed to decrease stock for product ID: $productId");
            }
        }
        
        return true;
    }
    
    /**
     * Increase stock for multiple products (used in order cancellation)
     * 
     * @param array $items Array of ['product_id' => quantity_to_increase]
     * @return bool Success
     */
    public function increaseMultipleStock($items)
    {
        foreach ($items as $productId => $quantity) {
            $this->increaseStock($productId, $quantity);
        }
        
        return true;
    }
    
    /**
     * Get total products count
     * 
     * @return int
     */
    public function getCount()
    {
        $sql = "SELECT COUNT(*) as count FROM produits";
        $stmt = $this->db->executeQuery($sql);
        $result = $stmt->fetch();
        return (int)$result['count'];
    }
    
    /**
     * Check if product exists
     * 
     * @param int $id Product ID
     * @return bool
     */
    public function exists($id)
    {
        $sql = "SELECT 1 FROM produits WHERE id = :id LIMIT 1";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get products by price range
     * 
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @return array
     */
    public function getByPriceRange($minPrice, $maxPrice)
    {
        $sql = "SELECT * FROM produits 
                WHERE (COALESCE(prix_sold, prix_original) BETWEEN :min_price AND :max_price)
                AND quantity > 0
                ORDER BY COALESCE(prix_sold, prix_original) ASC";
        
        $stmt = $this->db->executeQuery($sql, [
            'min_price' => $minPrice,
            'max_price' => $maxPrice
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get featured products (products with sales)
     * 
     * @param int $limit Limit results
     * @return array
     */
    public function getFeatured($limit = 10)
    {
        $sql = "SELECT p.*, COUNT(c.id) as order_count
                FROM produits p
                LEFT JOIN command c ON p.id = c.product_id
                WHERE p.quantity > 0
                GROUP BY p.id
                ORDER BY order_count DESC, p.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->executeQuery($sql, ['limit' => $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate product data
     * 
     * @param array $data Product data
     * @return array Validation errors
     */
    public function validate($data)
    {
        $errors = [];
        
        // Required fields
        if (empty($data['nom'])) {
            $errors[] = "Product name is required";
        } elseif (strlen($data['nom']) > 30) {
            $errors[] = "Product name must be 30 characters or less";
        }
        
        if (empty($data['description'])) {
            $errors[] = "Product description is required";
        }
        
        if (!isset($data['quantity']) || $data['quantity'] < 0) {
            $errors[] = "Quantity must be a non-negative number";
        }
        
        // Price validation
        if (isset($data['prix_original']) && $data['prix_original'] < 0) {
            $errors[] = "Original price must be positive";
        }
        
        if (isset($data['prix_sold']) && $data['prix_sold'] < 0) {
            $errors[] = "Sale price must be positive";
        }
        
        // Image validation - now expects a single string or null
        if (isset($data['image']) && !is_string($data['image']) && $data['image'] !== null) {
            $errors[] = "Image must be a string or null";
        }
        
        return $errors;
    }
}