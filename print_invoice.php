<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get invoice ID
$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header('Location: invoice.php');
    exit();
}

// Fetch invoice details with related information
$sql = "SELECT i.*,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id as student_number,
               s.email,
               s.address,
               s.parent_name,
               s.parent_contact,
               ay.year_name,
               t.term_name,
               CONCAT(u.username, ' ', u.full_name) as created_by_name
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
        LEFT JOIN terms t ON i.term_id = t.id
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Invoice not found!'); window.close();</script>";
    exit();
}

$invoice = $result->fetch_assoc();
$stmt->close();

// Get school information
$school_info = [
    'name' => 'Bright Future Academy',
    'address' => '123 Education Street, Accra, Ghana',
    'phone' => '+233 24 123 4567',
    'email' => 'info@brightfutureacademy.edu.gh',
    'website' => 'www.brightfutureacademy.edu.gh',
    'logo' => 'img/school-logo.png' // Optional logo
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4e73df;
        }

        .school-info {
            flex: 1;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
        }

        .school-info h1 {
            color: #4e73df;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .school-info p {
            margin: 3px 0;
            color: #666;
            font-size: 0.9rem;
        }

        .invoice-meta {
            text-align: right;
            flex: 0 0 auto;
        }

        .invoice-title {
            background: #4e73df;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .invoice-title h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .invoice-number {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .detail-section h3 {
            color: #4e73df;
            margin-bottom: 15px;
            font-size: 1.1rem;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 5px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dotted #ddd;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #4e73df;
        }

        .student-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 10px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: white;
            border: 1px solid #ddd;
        }

        .items-table th {
            background: #4e73df;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .items-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .item-description {
            font-weight: 600;
        }

        .item-note {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .amount-cell {
            text-align: right;
            font-weight: bold;
            color: #4e73df;
            font-size: 1.1rem;
        }

        .invoice-total {
            float: right;
            width: 300px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .grand-total {
            background: #4e73df;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
        }

        .payment-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }

        .payment-info h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .overdue-notice {
            background: #ffebee;
            border: 1px solid #f44336;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .overdue-notice h3 {
            color: #d32f2f;
            margin-bottom: 10px;
        }

        .overdue-notice .icon {
            font-size: 2rem;
            color: #f44336;
            margin-bottom: 10px;
        }

        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
        }

        .footer-note {
            font-size: 0.9rem;
            margin: 5px 0;
        }

        .print-buttons {
            text-align: center;
            margin: 20px 0;
            page-break-inside: avoid;
        }

        .btn {
            background: #4e73df;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 10px;
            font-size: 1rem;
        }

        .btn:hover {
            background: #2e59d9;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Print specific styles */
        @media print {
            .print-buttons {
                display: none;
            }
            
            body {
                font-size: 12pt;
            }
            
            .print-container {
                max-width: none;
                margin: 0;
                padding: 0;
            }
            
            .invoice-header {
                page-break-inside: avoid;
            }
            
            .items-table {
                page-break-inside: avoid;
            }
            
            .invoice-total {
                page-break-inside: avoid;
            }
        }

        @page {
            size: A4;
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Print Buttons -->
        <div class="print-buttons">
            <button onclick="window.print()" class="btn">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>

        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="school-info">
                <?php if (file_exists($school_info['logo'])): ?>
                    <img src="<?= $school_info['logo'] ?>" alt="School Logo" class="school-logo">
                <?php endif; ?>
                <h1><?= htmlspecialchars($school_info['name']) ?></h1>
                <p><?= htmlspecialchars($school_info['address']) ?></p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($school_info['phone']) ?></p>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($school_info['email']) ?></p>
                <p><i class="fas fa-globe"></i> <?= htmlspecialchars($school_info['website']) ?></p>
            </div>

            <div class="invoice-meta">
                <div class="invoice-title">
                    <h2>INVOICE</h2>
                    <div class="invoice-number">#<?= htmlspecialchars($invoice['invoice_number']) ?></div>
                </div>
                <span class="status-badge status-<?= $invoice['status'] ?>">
                    <?= ucfirst($invoice['status']) ?>
                </span>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="billing-section">
                <h3>Bill To:</h3>
                <div class="student-info">
                    <div class="student-name"><?= htmlspecialchars($invoice['student_name']) ?></div>
                    <div class="detail-row">
                        <span class="detail-label">Student ID:</span>
                        <span class="detail-value"><?= htmlspecialchars($invoice['student_number']) ?></span>
                    </div>
                    <?php if (!empty($invoice['parent_name'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Parent:</span>
                            <span class="detail-value"><?= htmlspecialchars($invoice['parent_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['address'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value"><?= htmlspecialchars($invoice['address']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['phone'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?= htmlspecialchars($invoice['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['parent_phone'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Parent Phone:</span>
                            <span class="detail-value"><?= htmlspecialchars($invoice['parent_phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['email'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?= htmlspecialchars($invoice['email']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="invoice-info-section">
                <h3>Invoice Information:</h3>
                <div class="detail-section">
                    <div class="detail-row">
                        <span class="detail-label">Academic Year:</span>
                        <span class="detail-value"><?= htmlspecialchars($invoice['year_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Term:</span>
                        <span class="detail-value"><?= htmlspecialchars($invoice['term_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Invoice Date:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($invoice['created_at'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></span>
                    </div>
                    <?php if ($invoice['payment_date']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Payment Date:</span>
                            <span class="detail-value"><?= date('M d, Y', strtotime($invoice['payment_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Created By:</span>
                        <span class="detail-value"><?= htmlspecialchars($invoice['created_by_name'] ?? 'System') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Notice -->
        <?php if ($invoice['status'] === 'overdue'): ?>
            <div class="overdue-notice">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3>PAYMENT OVERDUE</h3>
                <p>This invoice is past due. Please contact the school office immediately to arrange payment and avoid additional late fees.</p>
            </div>
        <?php endif; ?>

        <!-- Invoice Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 20%;">Type</th>
                    <th style="width: 15%;">Academic Period</th>
                    <th style="width: 15%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-description"><?= ucfirst($invoice['invoice_type']) ?> Fee</div>
                        <?php if (!empty($invoice['description'])): ?>
                            <div class="item-note"><?= htmlspecialchars($invoice['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= ucfirst($invoice['invoice_type']) ?></td>
                    <td>
                        <?= htmlspecialchars($invoice['year_name'] ?? 'N/A') ?><br>
                        <small><?= htmlspecialchars($invoice['term_name'] ?? 'N/A') ?></small>
                    </td>
                    <td class="amount-cell">₵<?= number_format($invoice['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Invoice Total -->
        <div class="invoice-total">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>₵<?= number_format($invoice['amount'], 2) ?></span>
            </div>
            <div class="total-row">
                <span>Tax (0%):</span>
                <span>₵0.00</span>
            </div>
            <div class="total-row">
                <span>Discount:</span>
                <span>₵0.00</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL AMOUNT DUE:</span>
                <span>₵<?= number_format($invoice['amount'], 2) ?></span>
            </div>
        </div>

        <div style="clear: both;"></div>

        <!-- Payment Information -->
        <div class="payment-info">
            <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
            <p><strong>Payment Methods Accepted:</strong></p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Cash payment at school office</li>
                <li>Bank transfer to school account</li>
                <li>Mobile money (MTN, AirtelTigo, Vodafone)</li>
                <li>Online payment portal</li>
            </ul>
            <p><strong>Important:</strong> Please include the invoice number as reference for all payments.</p>
            <p><strong>Late Payment:</strong> A late fee of 5% will be applied to payments made after the due date.</p>
        </div>

        <!-- Terms and Conditions -->
        <div class="payment-info" style="background: #f8f9fa; border-color: #6c757d;">
            <h3><i class="fas fa-info-circle"></i> Terms and Conditions</h3>
            <ul style="margin: 10px 0; padding-left: 20px; font-size: 0.9rem;">
                <li>Payment is due by the date specified above</li>
                <li>Late payments may incur additional fees</li>
                <li>Students with outstanding fees may be restricted from certain activities</li>
                <li>All payments are non-refundable unless approved by school administration</li>
                <li>For payment disputes, contact the school office within 7 days</li>
            </ul>
        </div>

        <!-- Invoice Footer -->
        <div class="invoice-footer">
            <p class="footer-note">Thank you for choosing <?= htmlspecialchars($school_info['name']) ?>!</p>
            <p class="footer-note">For inquiries, contact us at <?= htmlspecialchars($school_info['phone']) ?> or <?= htmlspecialchars($school_info['email']) ?></p>
            <p class="footer-note"><small>This is a computer-generated invoice. No signature required.</small></p>
            <p class="footer-note"><small>Generated on <?= date('M d, Y \a\t g:i A') ?> by <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?></small></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // }

        // Print function
        function printInvoice() {
            window.print();
        }

        // Close window function
        function closeWindow() {
            window.close();
        }

        // Add print event listener
        window.addEventListener('afterprint', function() {
            // Optional: Close window after printing
            // window.close();
        });
    </script>
</body>
</html>