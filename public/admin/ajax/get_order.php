<?php
/**
 * AJAX endpoint to get order details
 */

require_once __DIR__ . '/../../../config/connection.php';
require_once __DIR__ . '/../../../src/models/Order.php';

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit();
}

try {
    $orderModel = new Order();
    $orderId = (int)$_GET['id'];
    
    $order = $orderModel->readById($orderId);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}