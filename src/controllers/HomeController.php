<?php
/**
 * Home Controller
 * Handles the main homepage and product listing
 */

require_once __DIR__ . '/../core/BaseController.php';

class HomeController extends BaseController
{
    private $productModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->productModel = $this->loadModel('Product');
    }
    
    /**
     * Homepage - Display products with categories
     */
    public function index()
    {
        try {
            // Get search and category parameters
            $search = $this->getGet('search', '');
            $category = $this->getGet('category', '');
            
            // Get products based on search/category
            if ($search) {
                $products = $this->productModel->search($search);
            } else {
                $products = $this->productModel->readInStock();
            }
            
            // Filter products by category
            $bags = [];
            $caps = [];
            
            foreach ($products as $product) {
                $productName = strtolower($product['nom']);
                $productDescription = strtolower($product['description']);
                
                if (strpos($productName, 'bag') !== false || strpos($productDescription, 'bag') !== false) {
                    $bags[] = $product;
                } elseif (strpos($productName, 'cap') !== false || strpos($productName, 'hat') !== false || 
                          strpos($productDescription, 'cap') !== false || strpos($productDescription, 'hat') !== false) {
                    $caps[] = $product;
                }
            }
            
            // Apply category filter
            if ($category === 'bags') {
                $products = $bags;
            } elseif ($category === 'caps') {
                $products = $caps;
            }
            
            // Get cart count
            $cartCount = $this->getCartCount();
            
            // Set data for view
            $this->setData([
                'products' => $products,
                'bags' => $bags,
                'caps' => $caps,
                'search' => $search,
                'category' => $category,
                'cartCount' => $cartCount,
                'pageTitle' => $search ? "Search Results for '$search'" : ($category ? ucfirst($category) : 'Vegas Shop - Premium Bags & Caps')
            ]);
            
            // Load the existing index.php as view
            require_once __DIR__ . '/../../public/index.php';
            
        } catch (Exception $e) {
            $this->handleError('Error loading homepage: ' . $e->getMessage());
        }
    }
    
    /**
     * Get cart count from session
     */
    private function getCartCount()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $cartCount = 0;
        if (isset($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $cartCount += $item['quantity'];
            }
        }
        
        return $cartCount;
    }
}