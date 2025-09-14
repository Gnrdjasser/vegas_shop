<?php
/**
 * Customer Receipt Download
 * Allows customers to download their order receipts
 */

// Clear any output buffer to prevent issues with PDF generation
ob_clean();

define('VEGAS_SHOP_ACCESS', true);

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../src/models/Order.php';
require_once __DIR__ . '/../src/utils/FPDF.php';

if (!isset($_GET['code'])) {
    die('Order code is required');
}

$orderCode = $_GET['code'];
$orderModel = new Order();
$order = $orderModel->readByCode($orderCode);

if (!$order) {
    die('Order not found');
}

class CustomerPDF extends FPDF
{
    private $companyName = 'Vegas Shop';
    private $companyAddress = '123 Vegas Street, Las Vegas, NV 89101';
    private $companyPhone = '+1 (555) 123-4567';
    private $companyEmail = 'info@vegasshop.com';

    function Header()
    {
        // Company Header
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 12, $this->companyName, 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, $this->companyAddress, 0, 1, 'C');
        $this->Cell(0, 6, 'Phone: ' . $this->companyPhone . ' | Email: ' . $this->companyEmail, 0, 1, 'C');
        
        // Receipt Title
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(8);
        $this->Cell(0, 10, 'ORDER RECEIPT', 0, 1, 'C');
        
        // Decorative line
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(8);
    }

    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-25);
        
        // Thank you message
        $this->SetFont('Arial', 'I', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Thank you for your business!', 0, 1, 'C');
        
        // Footer line
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY() + 2, 190, $this->GetY() + 2);
        
        // Page number
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function AddOrderInfo($order)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'ORDER INFORMATION', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);
        
        // Order details in two columns
        $this->Cell(60, 6, 'Order Code:', 0, 0, 'L');
        $this->Cell(60, 6, $order['order_code'], 0, 0, 'L');
        $this->Cell(60, 6, 'Date:', 0, 0, 'L');
        $this->Cell(0, 6, date('M j, Y H:i', strtotime($order['created_at'])), 0, 1, 'L');
        
        $this->Cell(60, 6, 'Status:', 0, 0, 'L');
        $this->Cell(60, 6, ucfirst($order['status']), 0, 0, 'L');
        $this->Cell(60, 6, 'Total Items:', 0, 0, 'L');
        $this->Cell(0, 6, count($order['items']), 0, 1, 'L');
        
        $this->Ln(5);
    }

    function AddCustomerInfo($order)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'CUSTOMER INFORMATION', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);
        
        $this->Cell(30, 6, 'Name:', 0, 0, 'L');
        $this->Cell(0, 6, $order['customer_name'], 0, 1, 'L');
        
        $this->Cell(30, 6, 'Phone:', 0, 0, 'L');
        $this->Cell(0, 6, $order['customer_phone'], 0, 1, 'L');
        
        $this->Cell(30, 6, 'Address:', 0, 0, 'L');
        $this->MultiCell(0, 6, $order['customer_address'], 0, 'L');
        
        $this->Ln(5);
    }

    function AddItemsTable($items)
    {
        // Table header
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(50, 50, 50);
        
        $this->Cell(80, 8, 'Product Name', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Qty', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Unit Price', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Total', 1, 1, 'C', true);
        
        // Table rows
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(250, 250, 250);
        
        $fill = false;
        foreach ($items as $item) {
            $this->Cell(80, 7, $item['product_name'], 1, 0, 'L', $fill);
            $this->Cell(25, 7, $item['quantity'], 1, 0, 'C', $fill);
            $this->Cell(35, 7, number_format($item['unit_price'], 2) . ' DZD', 1, 0, 'R', $fill);
            $this->Cell(35, 7, number_format($item['total_price'], 2) . ' DZD', 1, 1, 'R', $fill);
            $fill = !$fill;
        }
        
        $this->Ln(3);
    }

    function AddTotal($totalAmount)
    {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        
        // Total box
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Rect(120, $this->GetY(), 75, 15);
        
        $this->Cell(50, 8, '', 0, 0, 'L');
        $this->Cell(25, 8, 'TOTAL:', 0, 0, 'L');
        $this->Cell(50, 8, number_format($totalAmount, 2) . ' DZD', 0, 1, 'R');
        
        $this->Ln(10);
    }

    function AddNotes()
    {
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(100, 100, 100);
        
        $this->Cell(0, 5, 'Notes:', 0, 1, 'L');
        $this->Cell(0, 5, '• This is an official receipt for your purchase', 0, 1, 'L');
        $this->Cell(0, 5, '• Please keep this receipt for your records', 0, 1, 'L');
        $this->Cell(0, 5, '• For returns or exchanges, please contact us within 30 days', 0, 1, 'L');
    }
}

// Create PDF
$pdf = new CustomerPDF('P', 'mm', 'A4');
$pdf->AddPage();

// Add content
$pdf->AddOrderInfo($order);
$pdf->AddCustomerInfo($order);
$pdf->AddItemsTable($order['items']);
$pdf->AddTotal($order['total_amount']);
$pdf->AddNotes();

// Generate HTML receipt
$filename = 'receipt_' . $order['order_code'] . '_' . date('Y-m-d') . '.html';

// Set proper headers for HTML download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$pdf->Output('D', $filename);
exit;
