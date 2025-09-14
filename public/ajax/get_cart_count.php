<?php
/**
 * Get Cart Count AJAX Handler
 */

session_start();

header('Content-Type: application/json');

$cartCount = 0;

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

echo json_encode(['count' => $cartCount]);
?>