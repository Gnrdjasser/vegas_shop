<?php
/**
 * FPDF - A simple PDF generation library
 * This is a basic implementation for generating PDF receipts
 */

class FPDF
{
	private $buffer = '';
	private $state = 0;
	private $page = 0;
	private $fontSize = 12;
	private $fontFamily = 'Arial';
    private $fontStyle = '';
	private $x = 0;
	private $y = 0;
    private $w = 210; // A4 width in mm
    private $h = 297; // A4 height in mm
    private $lineHeight = 5;
    private $marginLeft = 10;
    private $marginRight = 10;
    private $marginTop = 10;
    private $marginBottom = 10;

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
	{
		$this->buffer = '';
		$this->state = 0;
		$this->page = 0;
        $this->x = $this->marginLeft;
        $this->y = $this->marginTop;
	}

    function AddPage($orientation = '', $size = '', $rotation = 0)
	{
		$this->page++;
        $this->x = $this->marginLeft;
        $this->y = $this->marginTop;
	}

	function SetFont($family, $style = '', $size = 0)
	{
		$this->fontFamily = $family;
        $this->fontStyle = $style;
		if ($size > 0) {
			$this->fontSize = $size;
		}
        $this->lineHeight = $this->fontSize * 0.4;
    }

    function Cell($w, $h, $txt, $border = 0, $ln = 0, $align = '', $fill = false)
    {
        // Generate HTML content instead of text
        $style = '';
        $tag = 'span';
        
        // Handle font styling
        if ($this->fontStyle == 'B') {
            $tag = 'strong';
        } elseif ($this->fontStyle == 'I') {
            $tag = 'em';
        }
        
        // Handle alignment
        if ($align == 'C') {
            $style .= 'text-align: center; ';
        } elseif ($align == 'R') {
            $style .= 'text-align: right; ';
        }
        
        // Handle borders and fill
        if ($border || $fill) {
            $style .= 'border: 1px solid #000; ';
            if ($fill) {
                $style .= 'background-color: #f0f0f0; ';
            }
        }
        
        // Add padding for table cells
        if ($border) {
            $style .= 'padding: 5px; ';
        }
        
        // Generate HTML
        if ($style) {
            $this->buffer .= '<' . $tag . ' style="' . $style . '">' . htmlspecialchars($txt) . '</' . $tag . '>';
        } else {
            $this->buffer .= '<' . $tag . '>' . htmlspecialchars($txt) . '</' . $tag . '>';
        }
        
        // Handle line breaks
		if ($ln > 0) {
            $this->buffer .= '<br>';
			if ($ln == 1) {
                $this->buffer .= '<br>';
			}
		}
	}

	function Ln($h = null)
	{
        $this->buffer .= '<br>';
        $this->y += $h ? $h : $this->lineHeight;
        $this->x = $this->marginLeft;
    }

    function SetY($y)
    {
        $this->y = $y;
    }

    function SetX($x)
    {
        $this->x = $x;
    }

    function GetX()
    {
        return $this->x;
    }

    function GetY()
    {
        return $this->y;
    }

    function SetMargins($left, $top, $right = -1)
    {
        $this->marginLeft = $left;
        $this->marginTop = $top;
        if ($right == -1) {
            $right = $left;
        }
        $this->marginRight = $right;
    }

    function SetAutoPageBreak($auto, $margin = 0)
    {
        // Not implemented in this basic version
    }

    function PageNo()
    {
        return $this->page;
	}

	function Output($dest = '', $name = '')
	{
		if ($dest == 'D') {
            // Generate a simple HTML-based "PDF" that can be printed as PDF
            $content = $this->generateHTMLReceipt();
            
            header('Content-Type: text/html');
			header('Content-Disposition: attachment; filename="' . $name . '"');
            echo $content;
		} else {
			echo $this->buffer;
		}
	}

    private function generateHTMLReceipt()
    {
        // Parse the buffer content to create proper HTML structure
        $content = $this->parseBufferToHTML();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vegas Shop Receipt</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: "Courier New", monospace; 
            margin: 0;
            padding: 10px;
            background: white;
            color: black;
            font-size: 12px;
            line-height: 1.2;
        }
        
        .receipt-container {
            max-width: 300px;
            margin: 0 auto;
            background: white;
            border: 1px solid #000;
            padding: 10px;
        }
        
        .header { 
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .company-name { 
            font-size: 16px; 
            font-weight: bold; 
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .company-info { 
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .receipt-title { 
            font-size: 14px; 
            font-weight: bold; 
            margin: 6px 0 4px;
            text-transform: uppercase;
        }
        
        .content {
            padding: 0;
        }
        
        .section { 
            margin: 8px 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
        }
        
        .section h3 { 
            font-size: 11px; 
            margin-bottom: 4px; 
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-bottom: 4px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        
        .info-label { 
            font-weight: bold; 
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 11px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 6px 0;
            font-size: 10px;
        }
        
        .items-table th, .items-table td { 
            padding: 2px 4px; 
            text-align: left; 
            border-bottom: 1px solid #000;
        }
        
        .items-table th { 
            background: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .total-section { 
            text-align: right; 
            margin: 8px 0;
            border-top: 2px solid #000;
            padding-top: 4px;
        }
        
        .total-box { 
            background: #f0f0f0;
            border: 2px solid #000;
            padding: 6px 8px; 
            display: inline-block; 
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .footer { 
            text-align: center; 
            padding: 6px 0;
            border-top: 1px solid #000;
            font-size: 9px;
        }
        
        .footer p {
            margin: 2px 0;
        }
        
        .notes {
            margin: 6px 0;
            padding: 4px;
            border: 1px solid #000;
            font-size: 9px;
        }
        
        .notes h4 {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 10px;
        }
        
        .notes ul {
            margin: 0;
            padding-left: 12px;
        }
        
        .notes li {
            margin: 1px 0;
        }
        
        .divider {
            border-top: 1px solid #000;
            margin: 4px 0;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        @media print { 
            body { 
                margin: 0;
                padding: 5px;
                background: white;
            }
            .receipt-container {
                border: 1px solid #000;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="company-name">VEGAS SHOP</div>
            <div class="company-info">123 Vegas Street, Las Vegas, NV 89101</div>
            <div class="company-info">Phone: +1 (555) 123-4567</div>
            <div class="company-info">Email: info@vegasshop.com</div>
            <div class="receipt-title">Order Receipt</div>
        </div>
        
        <div class="content">' . $content . '</div>
        
        <div class="footer">
            <p class="bold">Thank you for your business!</p>
            <p>Keep this receipt for your records</p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>';
        
        return $html;
    }

    private function parseBufferToHTML()
    {
        // Convert the buffer content to proper HTML
        $html = $this->buffer;
        
        // Convert line breaks to proper HTML
        $html = str_replace('<br><br>', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        
        // Wrap sections in proper divs with compact styling
        $html = str_replace('<strong>ORDER INFORMATION</strong>', '<div class="section"><h3>Order Information</h3><div class="info-grid">', $html);
        $html = str_replace('<strong>CUSTOMER INFORMATION</strong>', '</div><div class="section"><h3>Customer Information</h3><div class="info-grid">', $html);
        
        // Convert info items to compact grid format
        $html = str_replace('<strong>Order Code:</strong>', '<div class="info-item"><span class="info-label">Order Code</span><span class="info-value">', $html);
        $html = str_replace('<strong>Date:</strong>', '</div><div class="info-item"><span class="info-label">Date</span><span class="info-value">', $html);
        $html = str_replace('<strong>Status:</strong>', '</div><div class="info-item"><span class="info-label">Status</span><span class="info-value status-badge status-', $html);
        $html = str_replace('<strong>Total Items:</strong>', '</span></div><div class="info-item"><span class="info-label">Items</span><span class="info-value">', $html);
        $html = str_replace('<strong>Name:</strong>', '</span></div></div><div class="info-item"><span class="info-label">Name</span><span class="info-value">', $html);
        $html = str_replace('<strong>Phone:</strong>', '</span></div><div class="info-item"><span class="info-label">Phone</span><span class="info-value">', $html);
        $html = str_replace('<strong>Address:</strong>', '</span></div><div class="info-item"><span class="info-label">Address</span><span class="info-value">', $html);
        
        // Handle address MultiCell output - convert div to span
        $html = preg_replace('/<div style="margin: 5px 0;">([^<]+)<\/div>/', '$1', $html);
        
        // Close the last section
        $html = str_replace('</span></div></div><div class="section">', '</span></div></div></div><div class="section">', $html);
        
        // Add items table wrapper
        $html = str_replace('<strong style="text-align: center; border: 1px solid #000; background-color: #f0f0f0; padding: 5px; ">Product Name</strong>', '<h3>Items</h3><table class="items-table"><thead><tr><th>Item</th>', $html);
        $html = str_replace('<strong style="text-align: center; border: 1px solid #000; background-color: #f0f0f0; padding: 5px; ">Qty</strong>', '<th>Qty</th>', $html);
        $html = str_replace('<strong style="text-align: center; border: 1px solid #000; background-color: #f0f0f0; padding: 5px; ">Unit Price</strong>', '<th>Price</th>', $html);
        $html = str_replace('<strong style="text-align: center; border: 1px solid #000; background-color: #f0f0f0; padding: 5px; ">Total</strong>', '<th>Total</th></tr></thead><tbody>', $html);
        
        // Convert table rows
        $html = preg_replace('/<span style="border: 1px solid #000; padding: 5px; ">([^<]+)<\/span>/', '<tr><td>$1</td>', $html);
        $html = preg_replace('/<span style="text-align: center; border: 1px solid #000; padding: 5px; ">([^<]+)<\/span>/', '<td class="text-center">$1</td>', $html);
        $html = preg_replace('/<span style="text-align: right; border: 1px solid #000; padding: 5px; ">([^<]+)<\/span>/', '<td class="text-right">$1</td>', $html);
        
        // Close table
        $html = str_replace('</span></p><p><br>', '</td></tr></tbody></table>', $html);
        
        // Convert total section
        $html = str_replace('<strong>TOTAL:</strong>', '<div class="total-section"><div class="total-box">TOTAL: ', $html);
        $html = str_replace('<strong style="text-align: right; ">', '', $html);
        $html = str_replace('</strong></p>', '</div></div>', $html);
        
        // Convert notes section
        $html = str_replace('<em>Notes:</em>', '<div class="notes"><h4>Notes</h4><ul>', $html);
        $html = str_replace('<em>• ', '<li>', $html);
        $html = str_replace('</em>', '</li>', $html);
        $html = str_replace('</p><p><em>', '', $html);
        $html = str_replace('</li></p><p><em>', '</li>', $html);
        $html = str_replace('</li></p><p></p>', '</li></ul></div>', $html);
        
        return $html;
    }

    function SetTitle($title, $isUTF8 = false)
    {
        // Not implemented in this basic version
    }

    function SetAuthor($author, $isUTF8 = false)
    {
        // Not implemented in this basic version
    }

    function SetSubject($subject, $isUTF8 = false)
    {
        // Not implemented in this basic version
    }

    function SetKeywords($keywords, $isUTF8 = false)
    {
        // Not implemented in this basic version
    }

    function SetCreator($creator, $isUTF8 = false)
    {
        // Not implemented in this basic version
    }

    function AliasNbPages($alias = '{nb}')
    {
        // Not implemented in this basic version
    }

    function AddFont($family, $style = '', $file = '')
    {
        // Not implemented in this basic version
    }

    function SetDisplayMode($zoom, $layout = 'default')
    {
        // Not implemented in this basic version
    }

    function SetCompression($compress)
    {
        // Not implemented in this basic version
    }

    function SetTopMargin($margin)
    {
        $this->marginTop = $margin;
    }

    function SetLeftMargin($margin)
    {
        $this->marginLeft = $margin;
    }

    function SetRightMargin($margin)
    {
        $this->marginRight = $margin;
    }

    function GetStringWidth($s)
    {
        return strlen($s) * $this->fontSize * 0.3;
    }

    function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        $this->buffer .= '<div style="margin: 5px 0;">' . nl2br(htmlspecialchars($txt)) . '</div>';
    }

    function Write($h, $txt, $link = '')
    {
        $this->Cell(0, $h, $txt, 0, 1, '', false, $link);
    }

    function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
        // Not implemented in this basic version
    }

    function Line($x1, $y1, $x2, $y2)
    {
        $this->buffer .= '<hr style="margin: 10px 0; border: none; border-top: 1px solid #000;">';
    }

    function Rect($x, $y, $w, $h, $style = '')
    {
        $this->buffer .= '<div style="border: 1px solid #000; padding: 10px; margin: 5px 0;"></div>';
    }

    private $fillColor = [255, 255, 255];
    private $textColor = [0, 0, 0];
    private $drawColor = [0, 0, 0];
    private $lineWidth = 0.2;

    function SetFillColor($r, $g = null, $b = null)
    {
        if ($g === null) {
            $this->fillColor = [$r, $r, $r];
        } else {
            $this->fillColor = [$r, $g, $b];
        }
    }

    function SetTextColor($r, $g = null, $b = null)
    {
        if ($g === null) {
            $this->textColor = [$r, $r, $r];
        } else {
            $this->textColor = [$r, $g, $b];
        }
    }

    function SetDrawColor($r, $g = null, $b = null)
    {
        if ($g === null) {
            $this->drawColor = [$r, $r, $r];
        } else {
            $this->drawColor = [$r, $g, $b];
        }
    }

    function SetLineWidth($width)
    {
        $this->lineWidth = $width;
    }

    function SetDash($black = null, $white = null)
    {
        // Not implemented in this basic version
	}
}
