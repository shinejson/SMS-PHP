<?php
/**
 * PDF Generator for Invoices
 * Requires TCPDF library: https://github.com/tecnickcom/tcpdf
 */

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Include Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Rest of your code...
/**
 * Generate PDF invoice
 */
function generateInvoicePDF($invoice_id, $output_type = 'D') {
    // Include invoice functions
    require_once 'invoice-functions.php';
    
    // Get invoice data
    $invoice = getInvoiceById($invoice_id);
    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }
    
    // Get school information
    $school_info = getSchoolInfo();
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('School Management System');
    $pdf->SetAuthor($school_info['name']);
    $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
    $pdf->SetSubject('School Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Generate PDF content
    $html = generateInvoiceHTML($invoice, $school_info);
    
    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Define output filename
    $filename = 'invoice_' . $invoice['invoice_number'] . '.pdf';
    
    // Output PDF
    switch ($output_type) {
        case 'D': // Download
            $pdf->Output($filename, 'D');
            break;
        case 'F': // Save to file
            $filepath = 'invoices_pdf/' . $filename;
            $pdf->Output($filepath, 'F');
            return ['success' => true, 'filepath' => $filepath];
        case 'I': // Inline display
            $pdf->Output($filename, 'I');
            break;
        case 'S': // Return as string
            return $pdf->Output('', 'S');
        default:
            $pdf->Output($filename, 'D');
    }
    
    return ['success' => true];
}

/**
 * Generate HTML content for PDF invoice
 */
function generateInvoiceHTML($invoice, $school_info) {
    $status_colors = [
        'paid' => '#28a745',
        'unpaid' => '#ffc107',
        'overdue' => '#dc3545',
        'cancelled' => '#6c757d'
    ];
    
    $status_color = $status_colors[$invoice['status']] ?? '#6c757d';
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: helvetica, sans-serif;
                font-size: 10pt;
                line-height: 1.4;
                color: #333;
            }
            .invoice-header {
                border-bottom: 2px solid #4e73df;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .school-info h1 {
                color: #4e73df;
                font-size: 16pt;
                margin: 0 0 5px 0;
            }
            .invoice-meta {
                text-align: right;
            }
            .invoice-title {
                background: #4e73df;
                color: white;
                padding: 10px;
                border-radius: 3px;
                display: inline-block;
            }
            .invoice-details {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            .billing-section, .invoice-info-section {
                display: table-cell;
                width: 50%;
                vertical-align: top;
            }
            .detail-section {
                margin-bottom: 15px;
            }
            .detail-section h3 {
                color: #4e73df;
                font-size: 11pt;
                margin-bottom: 8px;
                border-bottom: 1px solid #eee;
                padding-bottom: 3px;
            }
            .detail-row {
                display: table-row;
            }
            .detail-label, .detail-value {
                display: table-cell;
                padding: 3px 0;
                border-bottom: 1px dotted #eee;
            }
            .detail-label {
                font-weight: bold;
                width: 40%;
            }
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .items-table th {
                background: #f8f9fa;
                border: 1px solid #ddd;
                padding: 8px;
                font-weight: bold;
                text-align: left;
            }
            .items-table td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .invoice-total {
                float: right;
                width: 250px;
                margin-top: 20px;
            }
            .total-row {
                display: table-row;
            }
            .total-label, .total-value {
                display: table-cell;
                padding: 5px 10px;
                border-bottom: 1px solid #eee;
            }
            .grand-total {
                background: #4e73df;
                color: white;
                font-weight: bold;
                border: none;
            }
            .status-badge {
                display: inline-block;
                padding: 5px 10px;
                background: ' . $status_color . ';
                color: white;
                border-radius: 3px;
                font-weight: bold;
                text-transform: uppercase;
                font-size: 9pt;
            }
            .payment-info {
                background: #e3f2fd;
                border: 1px solid #2196f3;
                border-radius: 3px;
                padding: 15px;
                margin: 20px 0;
                font-size: 9pt;
            }
            .footer {
                margin-top: 30px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 8pt;
                color: #666;
            }
            .overdue-notice {
                background: #ffebee;
                border: 1px solid #f44336;
                border-radius: 3px;
                padding: 15px;
                margin: 15px 0;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <!-- Invoice Header -->
        <div class="invoice-header">
            <table width="100%">
                <tr>
                    <td width="60%">
                        <div class="school-info">
                            <h1>' . htmlspecialchars($school_info['name']) . '</h1>
                            <p>' . htmlspecialchars($school_info['address']) . '</p>
                            <p>Phone: ' . htmlspecialchars($school_info['phone']) . '</p>
                            <p>Email: ' . htmlspecialchars($school_info['email']) . '</p>
                        </div>
                    </td>
                    <td width="40%">
                        <div class="invoice-meta">
                            <div class="invoice-title">
                                <h2 style="margin: 0; font-size: 14pt;">INVOICE</h2>
                                <p style="margin: 0; font-size: 11pt;">#' . htmlspecialchars($invoice['invoice_number']) . '</p>
                            </div>
                            <br>
                            <span class="status-badge">' . ucfirst($invoice['status']) . '</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="billing-section">
                <div class="detail-section">
                    <h3>Bill To:</h3>
                    <div class="detail-row">
                        <div class="detail-label">Student Name:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['student_name']) . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Student ID:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['student_number']) . '</div>
                    </div>';
    
    if (!empty($invoice['parent_name'])) {
        $html .= '
                    <div class="detail-row">
                        <div class="detail-label">Parent Name:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['parent_name']) . '</div>
                    </div>';
    }
    
    if (!empty($invoice['address'])) {
        $html .= '
                    <div class="detail-row">
                        <div class="detail-label">Address:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['address']) . '</div>
                    </div>';
    }
    
    $html .= '
                </div>
            </div>
            
            <div class="invoice-info-section">
                <div class="detail-section">
                    <h3>Invoice Details:</h3>
                    <div class="detail-row">
                        <div class="detail-label">Invoice Date:</div>
                        <div class="detail-value">' . date('M d, Y', strtotime($invoice['created_at'])) . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Due Date:</div>
                        <div class="detail-value">' . date('M d, Y', strtotime($invoice['due_date'])) . '</div>
                    </div>';
    
    if ($invoice['payment_date']) {
        $html .= '
                    <div class="detail-row">
                        <div class="detail-label">Payment Date:</div>
                        <div class="detail-value">' . date('M d, Y', strtotime($invoice['payment_date'])) . '</div>
                    </div>';
    }
    
    $html .= '
                    <div class="detail-row">
                        <div class="detail-label">Academic Year:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['year_name'] ?? 'N/A') . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Term:</div>
                        <div class="detail-value">' . htmlspecialchars($invoice['term_name'] ?? 'N/A') . '</div>
                    </div>
                </div>
            </div>
        </div>';
    
    // Overdue Notice
    if ($invoice['status'] === 'overdue') {
        $html .= '
        <div class="overdue-notice">
            <h3 style="color: #d32f2f; margin: 0 0 10px 0;">PAYMENT OVERDUE</h3>
            <p style="margin: 0;">This invoice is past due. Please contact the school office immediately.</p>
        </div>';
    }
    
    // Invoice Items
    $html .= '
        <table class="items-table">
            <thead>
                <tr>
                    <th width="50%">Description</th>
                    <th width="20%">Type</th>
                    <th width="15%">Period</th>
                    <th width="15%" style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>' . ucfirst($invoice['invoice_type']) . ' Fee</strong>';
    
    if (!empty($invoice['description'])) {
        $html .= '<br><small>' . htmlspecialchars($invoice['description']) . '</small>';
    }
    
    $html .= '
                    </td>
                    <td>' . ucfirst($invoice['invoice_type']) . '</td>
                    <td>' . htmlspecialchars($invoice['year_name'] ?? 'N/A') . '<br>' . 
                          htmlspecialchars($invoice['term_name'] ?? 'N/A') . '</td>
                    <td style="text-align: right;"><strong>₵' . number_format($invoice['amount'], 2) . '</strong></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Invoice Total -->
        <div class="invoice-total">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">₵' . number_format($invoice['amount'], 2) . '</div>
            </div>
            <div class="total-row">
                <div class="total-label">Tax (0%):</div>
                <div class="total-value">₵0.00</div>
            </div>
            <div class="total-row">
                <div class="total-label">Discount:</div>
                <div class="total-value">₵0.00</div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">TOTAL DUE:</div>
                <div class="total-value">₵' . number_format($invoice['amount'], 2) . '</div>
            </div>
        </div>
        
        <div style="clear: both;"></div>
        
        <!-- Payment Information -->
        <div class="payment-info">
            <h3 style="margin: 0 0 10px 0; color: #1976d2;">Payment Information</h3>
            <p style="margin: 5px 0;"><strong>Payment Methods:</strong> Cash, Bank Transfer, Mobile Money</p>
            <p style="margin: 5px 0;"><strong>Due Date:</strong> ' . date('F j, Y', strtotime($invoice['due_date'])) . '</p>
            <p style="margin: 5px 0;"><strong>Note:</strong> Please include invoice number as reference.</p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Thank you for choosing ' . htmlspecialchars($school_info['name']) . '!</p>
            <p>This is a computer-generated invoice. No signature required.</p>
            <p>Generated on ' . date('M d, Y \a\t g:i A') . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Generate bulk invoices PDF (multiple invoices in one PDF)
 */
function generateBulkInvoicesPDF($invoice_ids, $output_type = 'D') {
    require_once('tcpdf/tcpdf.php');
    require_once 'invoice-functions.php';
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    $school_info = getSchoolInfo();
    
    foreach ($invoice_ids as $invoice_id) {
        $invoice = getInvoiceById($invoice_id);
        if (!$invoice) continue;
        
        $pdf->AddPage();
        $html = generateInvoiceHTML($invoice, $school_info);
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    $filename = 'invoices_bulk_' . date('Y-m-d') . '.pdf';
    
    switch ($output_type) {
        case 'D': $pdf->Output($filename, 'D'); break;
        case 'F': 
            $filepath = 'invoices_pdf/' . $filename;
            $pdf->Output($filepath, 'F');
            return ['success' => true, 'filepath' => $filepath];
        case 'I': $pdf->Output($filename, 'I'); break;
        case 'S': return $pdf->Output('', 'S');
        default: $pdf->Output($filename, 'D');
    }
    
    return ['success' => true];
}

// PDF download endpoint
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
    session_start();
    require_once 'config.php';
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        die('Access denied');
    }
    
    $invoice_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $output_type = isset($_GET['view']) ? 'I' : 'D'; // I = inline, D = download
    
    generateInvoicePDF($invoice_id, $output_type);
    exit;
}
?>