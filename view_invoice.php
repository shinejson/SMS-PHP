

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
    header('Location: invoice.php');
    exit();
}

$invoice = $result->fetch_assoc();
$stmt->close();

// Get school information (you might want to create a school_settings table)
$school_info = [
    'name' => 'Bright Future Academy',
    'address' => '123 Education Street, Accra, Ghana',
    'phone' => '+233 24 123 4567',
    'email' => 'info@brightfutureacademy.edu.gh',
    'website' => 'www.brightfutureacademy.edu.gh'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> - School Management System</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>


            <div class="page-content">
                <div class="page-header">
          
                                <div>
                        <h1><i class="fas fa-file-invoice"></i> Invoice Details</h1>
                        <p>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></p>
                    </div>
                    <div class="header-actions">
                        <button onclick="printInvoice(<?= $invoice_id ?>)" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print
                        </button>
                         <button onclick="downloadPDF(<?= $invoice_id ?>)" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
                        <a href="invoice.php" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="invoice-container" id="invoiceContent">
                    <div class="invoice-header">
                        <div class="school-info">
                            <h2><?= htmlspecialchars($school_info['name']) ?></h2>
                            <p><?= htmlspecialchars($school_info['address']) ?></p>
                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($school_info['phone']) ?></p>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($school_info['email']) ?></p>
                            <p><i class="fas fa-globe"></i> <?= htmlspecialchars($school_info['website']) ?></p>
                        </div>
                        
                        <div class="invoice-meta">
                            <div class="invoice-number">
                                <h3>INVOICE</h3>
                                <p><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                            </div>
                            <div class="invoice-status">
                                <span class="status-badge status-<?= $invoice['status'] ?>">
                                    <?= ucfirst($invoice['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-details">
                        <div class="billing-info">
                            <h4>Bill To:</h4>
                            <div class="student-details">
                                <p><strong><?= htmlspecialchars($invoice['student_name']) ?></strong></p>
                                <p>Student ID: <?= htmlspecialchars($invoice['student_number']) ?></p>
                                <?php if (!empty($invoice['parent_name'])): ?>
                                    <p>Parent: <?= htmlspecialchars($invoice['parent_name']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['address'])): ?>
                                    <p><?= htmlspecialchars($invoice['address']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['phone'])): ?>
                                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($invoice['phone']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['parent_phone'])): ?>
                                    <p><i class="fas fa-phone"></i> Parent: <?= htmlspecialchars($invoice['parent_phone']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['email'])): ?>
                                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($invoice['email']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="invoice-info">
                            <div class="info-row">
                                <span>Academic Year:</span>
                                <span><?= htmlspecialchars($invoice['year_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-row">
                                <span>Term:</span>
                                <span><?= htmlspecialchars($invoice['term_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-row">
                                <span>Invoice Date:</span>
                                <span><?= date('M d, Y', strtotime($invoice['created_at'])) ?></span>
                            </div>
                            <div class="info-row">
                                <span>Due Date:</span>
                                <span><?= date('M d, Y', strtotime($invoice['due_date'])) ?></span>
                            </div>
                            <?php if ($invoice['payment_date']): ?>
                                <div class="info-row">
                                    <span>Payment Date:</span>
                                    <span><?= date('M d, Y', strtotime($invoice['payment_date'])) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span>Created By:</span>
                                <span><?= htmlspecialchars($invoice['created_by_name'] ?? 'System') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-items">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="item-description">
                                            <strong><?= ucfirst($invoice['invoice_type']) ?> Fee</strong>
                                            <?php if (!empty($invoice['description'])): ?>
                                                <p><?= htmlspecialchars($invoice['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="invoice-type"><?= ucfirst($invoice['invoice_type']) ?></span>
                                    </td>
                                    <td class="amount">
                                        <strong>₵<?= number_format($invoice['amount'], 2) ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="invoice-total">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>₵<?= number_format($invoice['amount'], 2) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Tax (0%):</span>
                            <span>₵0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total Amount:</span>
                            <span>₵<?= number_format($invoice['amount'], 2) ?></span>
                        </div>
                    </div>

                    <div class="invoice-notes">
                        <h4>Payment Instructions:</h4>
                        <p>Please pay by the due date to avoid late fees. Payment can be made at the school office or through our online payment portal.</p>
                        
                        <?php if ($invoice['status'] === 'overdue'): ?>
                            <div class="overdue-notice">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>OVERDUE NOTICE:</strong> This invoice is past due. Please contact the school office immediately to arrange payment.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="invoice-footer">
                        <p>Thank you for choosing <?= htmlspecialchars($school_info['name']) ?>!</p>
                        <p><small>This is a computer-generated invoice and does not require a signature.</small></p>
                    </div>
                </div>

                <?php if ($invoice['status'] !== 'paid'): ?>
                    <div class="payment-actions">
                        <h3>Payment Actions</h3>
                        <div class="action-buttons">
                            <button onclick="markAsPaid()" class="btn btn-success">
                                <i class="fas fa-check"></i> Mark as Paid
                            </button>
                            <button onclick="sendReminder()" class="btn btn-warning">
                                <i class="fas fa-bell"></i> Send Reminder
                            </button>
                            <button onclick="editInvoice()" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Invoice
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
     
        // Update the JavaScript function
function downloadPDF(invoiceId) {
    window.open('pdf-generator.php?action=download&id=' + invoiceId, '_blank');
}

function printInvoice(invoiceId) {
    // Open PDF in print view
    window.open('pdf-generator.php?action=download&id=' + invoiceId + '&view=1', '_blank');
}

        function markAsPaid() {
            if (confirm('Mark this invoice as paid?')) {
                // Submit form to update status
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'invoice.php';
                
                const invoiceId = document.createElement('input');
                invoiceId.type = 'hidden';
                invoiceId.name = 'invoice_id';
                invoiceId.value = '<?= $invoice_id ?>';
                
                const status = document.createElement('input');
                status.type = 'hidden';
                status.name = 'status';
                status.value = 'paid';
                
                const action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'update_status';
                action.value = '1';
                
                form.appendChild(invoiceId);
                form.appendChild(status);
                form.appendChild(action);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function sendReminder() {
            alert('Reminder sent successfully!');
        }

        function editInvoice() {
            window.location.href = 'edit_invoice.php?id=<?= $invoice_id ?>';
        }

    </script>
</body>
</html>

<style>
.invoice-container {
    background: white;
    border-radius: 10px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.school-info h2 {
    color: #4e73df;
    margin-bottom: 10px;
}

.school-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-meta {
    text-align: right;
}

.invoice-number h3 {
    color: #4e73df;
    margin-bottom: 5px;
    font-size: 1.5rem;
}

.invoice-number p {
    font-size: 1.2rem;
    font-weight: bold;
    margin: 0;
}

.invoice-status {
    margin-top: 15px;
}

.invoice-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.billing-info h4,
.invoice-info h4 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 5px;
    border-bottom: 1px solid #eee;
}

.student-details p {
    margin: 8px 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-row span:first-child {
    font-weight: 500;
    color: #666;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
}

.items-table th,
.items-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.items-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.item-description strong {
    display: block;
    margin-bottom: 5px;
}

.item-description p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.amount {
    text-align: right;
}

.invoice-total {
    border-top: 2px solid #eee;
    padding-top: 20px;
    max-width: 300px;
    margin-left: auto;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
}

.grand-total {
    font-size: 1.2rem;
    font-weight: bold;
    color: #4e73df;
    border-top: 1px solid #eee;
    margin-top: 10px;
    padding-top: 15px;
}

.invoice-notes {
    margin: 30px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.invoice-notes h4 {
    margin-bottom: 10px;
    color: #333;
}

.overdue-notice {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
}

.overdue-notice i {
    margin-right: 8px;
}

.invoice-footer {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #666;
}

.payment-actions {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.payment-actions h3 {
    margin-bottom: 20px;
    color: #333;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

@media (max-width: 768px) {
    .invoice-container {
        padding: 20px;
    }
    
    .invoice-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .invoice-meta {
        text-align: left;
    }
    
    .invoice-details {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .header-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

@media print {
    .sidebar,
    .top-nav,
    .page-header,
    .payment-actions {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
    }
    
    .invoice-container {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>