<?php
/**
 * Health Check Endpoint
 * Provides system health status for monitoring
 */

define('VEGAS_SHOP_ACCESS', true);

require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
    'checks' => []
];

// Database connectivity check
try {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query('SELECT 1');
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// File system check
$writableDirs = ['logs', 'public/uploads'];
foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (is_dir($path) && is_writable($path)) {
        $health['checks']["fs_{$dir}"] = [
            'status' => 'ok',
            'message' => "Directory {$dir} is writable"
        ];
    } else {
        $health['status'] = 'error';
        $health['checks']["fs_{$dir}"] = [
            'status' => 'error',
            'message' => "Directory {$dir} is not writable"
        ];
    }
}

// Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$health['checks']['memory'] = [
    'status' => 'ok',
    'message' => "Memory usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB / {$memoryLimit}"
];

// Disk space
$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskUsage = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

$health['checks']['disk'] = [
    'status' => $diskUsage > 90 ? 'warning' : 'ok',
    'message' => "Disk usage: {$diskUsage}%"
];

// Set appropriate HTTP status code
$httpStatus = $health['status'] === 'ok' ? 200 : 503;
http_response_code($httpStatus);

echo json_encode($health, JSON_PRETTY_PRINT);
