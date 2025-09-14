-- Updated Vegas Shop Database Schema with Many-to-Many Relationship
-- Orders can have multiple products, and products can be in multiple orders

-- Create database
CREATE DATABASE IF NOT EXISTS vegas_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vegas_shop;

-- Drop existing tables in correct order (foreign keys first)
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS command;
DROP TABLE IF EXISTS produits;
DROP TABLE IF EXISTS users;

-- Create Produits (Products) table
CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(30) NOT NULL,
    description TEXT NOT NULL,
    prix_original DECIMAL(10,2) NULL,
    prix_sold DECIMAL(10,2) NULL,
    quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL, -- Changed from JSON to VARCHAR(255) for single image path
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for better performance
    INDEX idx_nom (nom),
    INDEX idx_prix_original (prix_original),
    INDEX idx_prix_sold (prix_sold),
    INDEX idx_quantity (quantity),
    INDEX idx_created_at (created_at),
    
    -- Full-text search index for product search
    FULLTEXT idx_search (nom, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Users table for admin authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for better performance
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Orders table (renamed from command for clarity)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(255) NOT NULL UNIQUE,
    customer_name VARCHAR(50) NOT NULL,
    customer_phone CHAR(15) NOT NULL,
    customer_address TEXT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for better performance
    INDEX idx_order_code (order_code),
    INDEX idx_customer_name (customer_name),
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Order Items pivot table (Many-to-Many relationship)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES produits(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for better performance
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id),
    INDEX idx_order_product (order_id, product_id),
    
    -- Prevent duplicate entries for same order-product combination
    UNIQUE KEY unique_order_product (order_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample products


-- Insert default admin user
INSERT INTO users (username, email, password, role, is_active) VALUES
('vegas', 'vegas@vegasshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Insert sample orders


-- Insert sample order items (Many-to-Many relationships)


-- Create useful views for common queries

-- View for order details with all items
CREATE VIEW v_order_details AS
SELECT 
    o.id as order_id,
    o.order_code,
    o.customer_name,
    o.customer_phone,
    o.customer_address,
    o.total_amount as order_total,
    o.status,
    o.created_at as order_date,
    oi.id as item_id,
    oi.quantity,
    oi.unit_price,
    oi.total_price as item_total,
    p.id as product_id,
    p.nom as product_name,
    p.description as product_description,
    p.image as product_image -- Changed from product_images
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
JOIN produits p ON oi.product_id = p.id;

-- View for product sales summary
CREATE VIEW v_product_sales AS
SELECT 
    p.id as product_id,
    p.nom as product_name,
    p.quantity as current_stock,
    COUNT(DISTINCT oi.order_id) as total_orders,
    SUM(oi.quantity) as total_quantity_sold,
    SUM(oi.total_price) as total_revenue,
    AVG(oi.unit_price) as average_selling_price,
    MIN(oi.created_at) as first_sale_date,
    MAX(oi.created_at) as last_sale_date
FROM produits p
LEFT JOIN order_items oi ON p.id = oi.product_id
GROUP BY p.id, p.nom, p.quantity;

-- View for customer order summary
CREATE VIEW v_customer_summary AS
SELECT 
    o.customer_name,
    o.customer_phone,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.total_amount) as total_spent,
    AVG(o.total_amount) as average_order_value,
    MIN(o.created_at) as first_order_date,
    MAX(o.created_at) as last_order_date
FROM orders o
GROUP BY o.customer_name, o.customer_phone;

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to create an order with multiple items
CREATE PROCEDURE sp_create_order_with_items(
    IN p_order_code VARCHAR(255),
    IN p_customer_name VARCHAR(50),
    IN p_customer_phone CHAR(15),
    IN p_customer_address TEXT,
    IN p_items JSON -- Array of {product_id, quantity, unit_price}
)
BEGIN
    DECLARE v_order_id INT;
    DECLARE v_total_amount DECIMAL(10,2) DEFAULT 0;
    DECLARE v_item_count INT DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    DECLARE v_unit_price DECIMAL(10,2);
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE v_available_stock INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Create the order
    INSERT INTO orders (order_code, customer_name, customer_phone, customer_address, total_amount)
    VALUES (p_order_code, p_customer_name, p_customer_phone, p_customer_address, 0);
    
    SET v_order_id = LAST_INSERT_ID();
    SET v_item_count = JSON_LENGTH(p_items);
    
    -- Process each item
    WHILE v_i < v_item_count DO
        SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].product_id')));
        SET v_quantity = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].quantity')));
        SET v_unit_price = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].unit_price')));
        SET v_total_price = v_quantity * v_unit_price;
        
        -- Check stock availability
        SELECT quantity INTO v_available_stock FROM produits WHERE id = v_product_id;
        
        IF v_available_stock IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('Product with ID ', v_product_id, ' not found');
        END IF;
        
        IF v_available_stock < v_quantity THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('Insufficient stock for product ID ', v_product_id);
        END IF;
        
        -- Add order item
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
        VALUES (v_order_id, v_product_id, v_quantity, v_unit_price, v_total_price);
        
        -- Update product stock
        UPDATE produits SET quantity = quantity - v_quantity WHERE id = v_product_id;
        
        -- Add to total amount
        SET v_total_amount = v_total_amount + v_total_price;
        SET v_i = v_i + 1;
    END WHILE;
    
    -- Update order total
    UPDATE orders SET total_amount = v_total_amount WHERE id = v_order_id;
    
    COMMIT;
    
    SELECT v_order_id as order_id, v_total_amount as total_amount;
END //

-- Procedure to cancel an order and restore stock
CREATE PROCEDURE sp_cancel_order(
    IN p_order_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    
    DECLARE item_cursor CURSOR FOR 
        SELECT product_id, quantity FROM order_items WHERE order_id = p_order_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Check if order exists
    IF NOT EXISTS (SELECT 1 FROM orders WHERE id = p_order_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
    END IF;
    
    -- Restore stock for each item
    OPEN item_cursor;
    
    restore_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity;
        IF done THEN
            LEAVE restore_loop;
        END IF;
        
        UPDATE produits SET quantity = quantity + v_quantity WHERE id = v_product_id;
    END LOOP;
    
    CLOSE item_cursor;
    
    -- Update order status
    UPDATE orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = p_order_id;
    
    COMMIT;
    
    SELECT 'Order cancelled successfully' as message;
END //

DELIMITER ;

-- Create triggers for automatic calculations

DELIMITER //

-- Trigger to update order total when items are added/updated
CREATE TRIGGER tr_update_order_total_after_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.order_id;
END //

CREATE TRIGGER tr_update_order_total_after_update
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.order_id;
END //

CREATE TRIGGER tr_update_order_total_after_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET total_amount = COALESCE((
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = OLD.order_id
    ), 0),
    updated_at = CURRENT_TIMESTAMP
    WHERE id = OLD.order_id;
END //

DELIMITER ;

-- Display success message
SELECT 'Many-to-Many database schema created successfully!' as message;

-- Show table structures
DESCRIBE produits;
DESCRIBE orders;
DESCRIBE order_items;

-- Show sample data counts
SELECT 
    (SELECT COUNT(*) FROM produits) as total_products,
    (SELECT COUNT(*) FROM orders) as total_orders,
    (SELECT COUNT(*) FROM order_items) as total_order_items;