<?php

/**
 * Order Model
 * Handles CRUD operations for orders with Many-to-Many relationship to products
 * Uses 'orders' and 'order_items' tables
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/Product.php';

class Order
{
    private $db;
    private $productModel;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
        $this->productModel = new Product();
    }

    /**
     * Create a new order with multiple products
     * 
     * @param array $orderData Order information
     * @param array $items Array of items: [['product_id' => 1, 'quantity' => 2, 'unit_price' => 99.99], ...]
     * @return string Last insert ID
     */
    public function create($orderData, $items = [])
    {
        // Validate order data
        $errors = $this->validateOrderData($orderData);
        if (!empty($errors)) {
            throw new InvalidArgumentException("Order validation failed: " . implode(', ', $errors));
        }

        // Validate items
        if (empty($items)) {
            throw new InvalidArgumentException("Order must contain at least one item");
        }

        $itemErrors = $this->validateItems($items);
        if (!empty($itemErrors)) {
            throw new InvalidArgumentException("Items validation failed: " . implode(', ', $itemErrors));
        }

        // Check stock availability for all items
        $stockCheck = [];
        foreach ($items as $item) {
            $stockCheck[$item['product_id']] = $item['quantity'];
        }

        $availability = $this->productModel->checkStockAvailability($stockCheck);
        $unavailableItems = array_filter($availability, function ($item) {
            return !$item['available'];
        });

        if (!empty($unavailableItems)) {
            $errorMessages = [];
            foreach ($unavailableItems as $productId => $info) {
                $errorMessages[] = "Product {$info['product_name']} (ID: $productId): {$info['reason']}";
            }
            throw new InvalidArgumentException("Stock unavailable: " . implode(', ', $errorMessages));
        }

        // Generate order code if not provided
        $orderCode = $orderData['order_code'] ?? $this->generateOrderCode();

        // Calculate total amount
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }

        // Create the order
        $sql = "INSERT INTO orders (order_code, customer_name, customer_phone, customer_address, total_amount, status, notes) 
                VALUES (:order_code, :customer_name, :customer_phone, :customer_address, :total_amount, :status, :notes)";

        $params = [
            'order_code' => $orderCode,
            'customer_name' => SecurityHelper::sanitizeInput($orderData['customer_name']),
            'customer_phone' => preg_replace('/[^0-9+\-\s]/', '', $orderData['customer_phone']),
            'customer_address' => SecurityHelper::sanitizeInput($orderData['customer_address']),
            'total_amount' => $totalAmount,
            'status' => $orderData['status'] ?? 'pending',
            'notes' => isset($orderData['notes']) ? SecurityHelper::sanitizeInput($orderData['notes']) : null
        ];

        $this->db->executeQuery($sql, $params);
        $orderId = $this->db->getConnection()->lastInsertId();

        // Add order items
        foreach ($items as $item) {
            $this->addOrderItem($orderId, $item);
        }

        // Decrease stock for all products
        $stockDecrease = [];
        foreach ($items as $item) {
            $stockDecrease[$item['product_id']] = $item['quantity'];
        }
        $this->productModel->decreaseMultipleStock($stockDecrease);

        return $orderId;
    }

    /**
     * Read all orders with their items
     * 
     * @param int|null $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array
     */
    public function readAll($limit = null, $offset = 0)
    {
        $sql = "SELECT * FROM orders ORDER BY created_at DESC";
        $params = [];

        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params = ['limit' => $limit, 'offset' => $offset];
        }

        $stmt = $this->db->executeQuery($sql, $params);
        $orders = $stmt->fetchAll();

        // Load items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    /**
     * Read a single order by ID with all items
     * 
     * @param int $id Order ID
     * @return array|false
     */
    public function readById($id)
    {
        $sql = "SELECT * FROM orders WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        $order = $stmt->fetch();

        if ($order) {
            $order['items'] = $this->getOrderItems($id);
        }

        return $order;
    }

    /**
     * Read order by order code
     * 
     * @param string $orderCode Order code
     * @return array|false
     */
    public function readByCode($orderCode)
    {
        $sql = "SELECT * FROM orders WHERE order_code = :order_code";
        $stmt = $this->db->executeQuery($sql, ['order_code' => $orderCode]);
        $order = $stmt->fetch();

        if ($order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $order;
    }

    /**
     * Update an order (basic information only)
     * 
     * @param int $id Order ID
     * @param array $data Updated data
     * @return int Number of affected rows
     */
    public function update($id, $data)
    {
        $updateFields = [];
        $params = ['id' => $id];

        // Build dynamic update query
        $allowedFields = ['customer_name', 'customer_phone', 'customer_address', 'status', 'notes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";

                if ($field === 'customer_phone') {
                    $params[$field] = preg_replace('/[^0-9+\-\s]/', '', $data[$field]);
                } elseif (in_array($field, ['customer_name', 'customer_address', 'notes'])) {
                    $params[$field] = SecurityHelper::sanitizeInput($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }

        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        }

        $sql = "UPDATE orders SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete an order and restore product stock
     * 
     * @param int $id Order ID
     * @return int Number of affected rows
     */
    public function delete($id)
    {
        // Get order items before deletion to restore stock
        $orderItems = $this->getOrderItems($id);

        if (empty($orderItems)) {
            return 0;
        }

        try {
            $this->db->beginTransaction();

            // Delete order (cascade will delete order_items)
            $sql = "DELETE FROM orders WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, ['id' => $id]);
            $affected = $stmt->rowCount();

            // Restore product stock if order was deleted
            if ($affected > 0) {
                $stockIncrease = [];
                foreach ($orderItems as $item) {
                    $stockIncrease[$item['product_id']] = $item['quantity'];
                }
                $this->productModel->increaseMultipleStock($stockIncrease);
            }

            $this->db->commit();
            return $affected;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Add an item to an existing order
     * 
     * @param int $orderId Order ID
     * @param array $item Item data
     * @return string Last insert ID
     */
    public function addOrderItem($orderId, $item)
    {
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)";

        $totalPrice = $item['quantity'] * $item['unit_price'];

        $params = [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $totalPrice
        ];

        $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }

    /**
     * Update an order item
     * 
     * @param int $itemId Order item ID
     * @param array $data Updated data
     * @return int Number of affected rows
     */
    public function updateOrderItem($itemId, $data)
    {
        $updateFields = [];
        $params = ['id' => $itemId];

        $allowedFields = ['quantity', 'unit_price'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        // Recalculate total price if quantity or unit_price changed
        if (isset($data['quantity']) || isset($data['unit_price'])) {
            // Get current values
            $currentItem = $this->getOrderItemById($itemId);
            $newQuantity = $data['quantity'] ?? $currentItem['quantity'];
            $newUnitPrice = $data['unit_price'] ?? $currentItem['unit_price'];

            $updateFields[] = "total_price = :total_price";
            $params['total_price'] = $newQuantity * $newUnitPrice;
        }

        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        }

        $sql = "UPDATE order_items SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Remove an item from an order
     * 
     * @param int $itemId Order item ID
     * @return int Number of affected rows
     */
    public function removeOrderItem($itemId)
    {
        // Get item details before deletion to restore stock
        $item = $this->getOrderItemById($itemId);

        if (!$item) {
            return 0;
        }

        try {
            $this->db->beginTransaction();

            // Delete order item
            $sql = "DELETE FROM order_items WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, ['id' => $itemId]);
            $affected = $stmt->rowCount();

            // Restore product stock
            if ($affected > 0) {
                $this->productModel->increaseStock($item['product_id'], $item['quantity']);
            }

            $this->db->commit();
            return $affected;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get all items for an order
     * 
     * @param int $orderId Order ID
     * @return array
     */
    public function getOrderItems($orderId)
    {
        $sql = "SELECT 
                    oi.*,
                    p.nom as product_name,
                    p.description as product_description,
                    p.image as product_image
                FROM order_items oi
                JOIN produits p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
                ORDER BY oi.created_at ASC";

        $stmt = $this->db->executeQuery($sql, ['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single order item by ID
     * 
     * @param int $itemId Order item ID
     * @return array|false
     */
    public function getOrderItemById($itemId)
    {
        $sql = "SELECT 
                    oi.*,
                    p.nom as product_name,
                    p.description as product_description
                FROM order_items oi
                JOIN produits p ON oi.product_id = p.id
                WHERE oi.id = :id";

        $stmt = $this->db->executeQuery($sql, ['id' => $itemId]);
        return $stmt->fetch();
    }

    /**
     * Search orders by customer name, phone, or order code
     * 
     * @param string $searchTerm Search term
     * @return array
     */
    public function search($searchTerm)
    {
        $sql = "SELECT * FROM orders 
            WHERE customer_name LIKE :search1 
               OR customer_phone LIKE :search2 
               OR order_code LIKE :search3 
            ORDER BY created_at DESC";

        $searchTerm = '%' . $searchTerm . '%';
        $stmt = $this->db->executeQuery($sql, [
            'search1' => $searchTerm,
            'search2' => $searchTerm,
            'search3' => $searchTerm
        ]);
        $orders = $stmt->fetchAll();

        // Load items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    /**
     * Get orders by customer phone
     * 
     * @param string $phone Customer phone
     * @return array
     */
    public function getByPhone($phone)
    {
        $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);

        $sql = "SELECT * FROM orders 
                WHERE customer_phone LIKE :phone 
                ORDER BY created_at DESC";

        $stmt = $this->db->executeQuery($sql, ['phone' => "%$phone%"]);
        $orders = $stmt->fetchAll();

        // Load items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    /**
     * Get orders by status
     * 
     * @param string $status Order status
     * @return array
     */
    public function getByStatus($status)
    {
        $sql = "SELECT * FROM orders WHERE status = :status ORDER BY created_at DESC";
        $stmt = $this->db->executeQuery($sql, ['status' => $status]);
        $orders = $stmt->fetchAll();

        // Load items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    /**
     * Get orders by date range
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array
     */
    public function getByDateRange($startDate, $endDate)
    {
        $sql = "SELECT * FROM orders 
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                ORDER BY created_at DESC";

        $stmt = $this->db->executeQuery($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get sales statistics for a period
     * 
     * @param string $period 'today', 'week', 'month', 'year'
     * @return array|false
     */
    public function getSalesStats($period = 'month')
    {
        $dateCondition = '';

        switch ($period) {
            case 'today':
                $dateCondition = "WHERE DATE(o.created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $dateCondition = "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $dateCondition = "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }

        $sql = "SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as average_order_value,
                    SUM(oi.quantity) as total_items_sold,
                    MIN(o.created_at) as first_order,
                    MAX(o.created_at) as last_order
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                $dateCondition";

        $stmt = $this->db->executeQuery($sql);
        return $stmt->fetch();
    }

    /**
     * Get top selling products
     * 
     * @param int $limit Number of products to retrieve
     * @return array
     */
    public function getTopSellingProducts($limit = 10)
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.total_price) as total_revenue,
                    AVG(oi.unit_price) as average_price
                FROM produits p
                JOIN order_items oi ON p.id = oi.product_id
                GROUP BY p.id, p.nom
                ORDER BY total_quantity_sold DESC, total_revenue DESC
                LIMIT :limit";

        $stmt = $this->db->executeQuery($sql, ['limit' => $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get customer summary
     * 
     * @param string $customerPhone Customer phone
     * @return array|false
     */
    public function getCustomerSummary($customerPhone)
    {
        $phone = preg_replace('/[^0-9+\-\s]/', '', $customerPhone);

        $sql = "SELECT 
                    customer_name,
                    customer_phone,
                    COUNT(DISTINCT id) as total_orders,
                    SUM(total_amount) as total_spent,
                    AVG(total_amount) as average_order_value,
                    MIN(created_at) as first_order_date,
                    MAX(created_at) as last_order_date
                FROM orders 
                WHERE customer_phone LIKE :phone
                GROUP BY customer_name, customer_phone";

        $stmt = $this->db->executeQuery($sql, ['phone' => "%$phone%"]);
        return $stmt->fetch();
    }

    /**
     * Get total orders count
     * 
     * @return int
     */
    public function getCount()
    {
        $sql = "SELECT COUNT(*) as count FROM orders";
        $stmt = $this->db->executeQuery($sql);
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * Check if order exists
     * 
     * @param int $id Order ID
     * @return bool
     */
    public function exists($id)
    {
        $sql = "SELECT 1 FROM orders WHERE id = :id LIMIT 1";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Generate unique order code
     * 
     * @return string
     */
    private function generateOrderCode()
    {
        $date = date('Ymd');
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $attempt++;
            
            // Get the highest counter for today
            $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(order_code, '-', -1), '-', -1) AS UNSIGNED)) as max_counter 
                    FROM orders 
                    WHERE order_code LIKE :pattern";
            
            $stmt = $this->db->executeQuery($sql, ['pattern' => "ORD-{$date}-%"]);
            $result = $stmt->fetch();
            
            $counter = ($result['max_counter'] ?? 0) + 1;
            $orderCode = "ORD-{$date}-" . str_pad($counter, 3, '0', STR_PAD_LEFT);
            
            // Check if this code already exists
            $checkSql = "SELECT 1 FROM orders WHERE order_code = :code LIMIT 1";
            $checkStmt = $this->db->executeQuery($checkSql, ['code' => $orderCode]);
            
            if ($checkStmt->fetch() === false) {
                return $orderCode; // Code is unique
            }
            
        } while ($attempt < $maxAttempts);
        
        // Fallback: use timestamp if we can't generate a unique code
        return "ORD-{$date}-" . time();
    }

    /**
     * Validate order data
     * 
     * @param array $data Order data
     * @return array Validation errors
     */
    private function validateOrderData($data)
    {
        $errors = [];

        // Required fields
        if (empty($data['customer_name'])) {
            $errors[] = "Customer name is required";
        } elseif (strlen($data['customer_name']) > 50) {
            $errors[] = "Customer name must be 50 characters or less";
        }

        if (empty($data['customer_phone'])) {
            $errors[] = "Customer phone is required";
        }

        if (empty($data['customer_address'])) {
            $errors[] = "Customer address is required";
        }

        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors[] = "Invalid status. Must be one of: " . implode(', ', $validStatuses);
            }
        }

        return $errors;
    }

    /**
     * Validate order items
     * 
     * @param array $items Order items
     * @return array Validation errors
     */
    private function validateItems($items)
    {
        $errors = [];

        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                $errors[] = "Item $index: Product ID is required";
            } elseif (!$this->productModel->exists($item['product_id'])) {
                $errors[] = "Item $index: Product does not exist";
            }

            if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                $errors[] = "Item $index: Quantity must be a positive number";
            }

            if (!isset($item['unit_price']) || $item['unit_price'] <= 0) {
                $errors[] = "Item $index: Unit price must be a positive number";
            }
        }

        return $errors;
    }
}
