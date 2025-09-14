#!/usr/bin/env php
<?php
/**
 * Vegas Shop CLI Tool
 * Command line interface for database management and maintenance
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Set up environment
define('VEGAS_SHOP_ACCESS', true);
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/src/utils/Migration.php';

use VegasShop\Utils\Migration;

class VegasShopCLI
{
    private $migration;

    public function __construct()
    {
        $this->migration = new Migration();
    }

    public function run($args)
    {
        $command = $args[1] ?? 'help';
        
        switch ($command) {
            case 'migrate':
                $this->migrate();
                break;
                
            case 'rollback':
                $this->rollback();
                break;
                
            case 'status':
                $this->status();
                break;
                
            case 'seed':
                $this->seed();
                break;
                
            case 'cache:clear':
                $this->clearCache();
                break;
                
            case 'logs:clear':
                $this->clearLogs();
                break;
                
            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }

    private function migrate()
    {
        echo "Running database migrations...\n";
        $this->migration->run();
    }

    private function rollback()
    {
        echo "Rolling back last migration batch...\n";
        $this->migration->rollback();
    }

    private function status()
    {
        $this->migration->status();
    }

    private function seed()
    {
        echo "Seeding database with sample data...\n";
        
        $db = DatabaseConnection::getInstance();
        
        // Sample products
        $products = [
            [
                'nom' => 'Premium Leather Handbag',
                'description' => 'High-quality leather handbag perfect for any occasion',
                'prix_original' => 299.99,
                'prix_sold' => 199.99,
                'quantity' => 50,
                'image' => 'handbag1.jpg'
            ],
            [
                'nom' => 'Designer Baseball Cap',
                'description' => 'Stylish baseball cap with embroidered logo',
                'prix_original' => 49.99,
                'prix_sold' => 29.99,
                'quantity' => 100,
                'image' => 'cap1.jpg'
            ],
            [
                'nom' => 'Luxury Wallet',
                'description' => 'Genuine leather wallet with multiple compartments',
                'prix_original' => 89.99,
                'prix_sold' => 59.99,
                'quantity' => 75,
                'image' => 'wallet1.jpg'
            ]
        ];
        
        foreach ($products as $product) {
            $sql = "INSERT IGNORE INTO produits (nom, description, prix_original, prix_sold, quantity, image) 
                    VALUES (:nom, :description, :prix_original, :prix_sold, :quantity, :image)";
            $db->executeQuery($sql, $product);
        }
        
        echo "✓ Seeded " . count($products) . " products\n";
    }

    private function clearCache()
    {
        echo "Clearing application cache...\n";
        
        // Clear file cache
        $cacheDir = sys_get_temp_dir() . '/vegasshop_cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            echo "✓ File cache cleared\n";
        }
        
        // Clear rate limit cache
        $rateLimitDir = sys_get_temp_dir() . '/vegasshop_rate_limit';
        if (is_dir($rateLimitDir)) {
            $files = glob($rateLimitDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            echo "✓ Rate limit cache cleared\n";
        }
    }

    private function clearLogs()
    {
        echo "Clearing application logs...\n";
        
        $logFile = 'logs/app.log';
        if (file_exists($logFile)) {
            unlink($logFile);
            echo "✓ Log file cleared\n";
        }
        
        // Clear rotated log files
        $logFiles = glob('logs/app.log.*');
        foreach ($logFiles as $file) {
            unlink($file);
        }
        
        if (!empty($logFiles)) {
            echo "✓ Rotated log files cleared\n";
        }
    }

    private function showHelp()
    {
        echo "Vegas Shop CLI Tool\n";
        echo "==================\n\n";
        echo "Available commands:\n\n";
        echo "  migrate          Run database migrations\n";
        echo "  rollback         Rollback last migration batch\n";
        echo "  status           Show migration status\n";
        echo "  seed             Seed database with sample data\n";
        echo "  cache:clear      Clear application cache\n";
        echo "  logs:clear       Clear application logs\n";
        echo "  help             Show this help message\n\n";
        echo "Usage: php cli.php <command>\n";
    }
}

// Run CLI
$cli = new VegasShopCLI();
$cli->run($argv);
