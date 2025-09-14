<?php

/**
 * Create Initial Tables Migration
 * This migration creates the initial database structure
 */
class CreateInitialTables
{
    public function up()
    {
        $db = \DatabaseConnection::getInstance();
        
        // Create products table
        $sql = "CREATE TABLE IF NOT EXISTS produits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(30) NOT NULL,
            description TEXT NOT NULL,
            prix_original DECIMAL(10,2) NULL,
            prix_sold DECIMAL(10,2) NULL,
            quantity INT NOT NULL DEFAULT 0,
            image VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_nom (nom),
            INDEX idx_prix_original (prix_original),
            INDEX idx_prix_sold (prix_sold),
            INDEX idx_quantity (quantity),
            INDEX idx_created_at (created_at),
            
            FULLTEXT idx_search (nom, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->executeQuery($sql);
        
        // Create users table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'admin',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->executeQuery($sql);
        
        // Create orders table
        $sql = "CREATE TABLE IF NOT EXISTS orders (
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
            
            INDEX idx_order_code (order_code),
            INDEX idx_customer_name (customer_name),
            INDEX idx_customer_phone (customer_phone),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->executeQuery($sql);
        
        // Create order_items table
        $sql = "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (product_id) REFERENCES produits(id) ON DELETE CASCADE ON UPDATE CASCADE,
            
            INDEX idx_order_id (order_id),
            INDEX idx_product_id (product_id),
            INDEX idx_order_product (order_id, product_id),
            
            UNIQUE KEY unique_order_product (order_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->executeQuery($sql);
        
        // Insert default admin user
        $sql = "INSERT IGNORE INTO users (username, email, password, role, is_active) VALUES
                ('admin', 'admin@vegasshop.com', :password, 'admin', TRUE)";
        
        $db->executeQuery($sql, [
            'password' => password_hash('admin123', PASSWORD_DEFAULT)
        ]);
    }
    
    public function down()
    {
        $db = \DatabaseConnection::getInstance();
        
        // Drop tables in reverse order
        $db->executeQuery("DROP TABLE IF EXISTS order_items");
        $db->executeQuery("DROP TABLE IF EXISTS orders");
        $db->executeQuery("DROP TABLE IF EXISTS users");
        $db->executeQuery("DROP TABLE IF EXISTS produits");
    }
}
