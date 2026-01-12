<?php
// account_details.php - Detailed view of a student account
require_once 'config.php';
require_once 'session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$allowed_roles = ['admin', 'accountant', 'finance'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: access_denied.php');
    exit();
}

$account_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($account_id <= 0) {
    $_SESSION['error'] = "Invalid account ID";
    header('Location: accounts.php');
    exit();
}

// Get account details with student info
$account_sql = "SELECT sa.*, 
                       s.first_name, s.last_name, s.student_id as student_code,
                       s.date_of_birth, s.gender,
                       c.class_name,
                       u.full_name as created_by_name
                FROM student_accounts sa
                JOIN students s ON sa.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN users u ON sa.created_by = u.id
                WHERE sa.id = ?";

$account_stmt = $conn->prepare($account_sql);
$account_stmt->bind_param('i', $account_id);
$account_stmt->execute();
$account_result = $account_stmt->get_result();

if ($account_result->num_rows === 0) {
    $_SESSION['error'] = "Account not found";
    header('Location: accounts.php');
    exit();
}

$account = $account_result->fetch_assoc();
$account_stmt->close();

// Get linked payment types
$payment_types = [];
$pt_sql = "SELECT payment_type FROM payment_accounts WHERE account_id = ? AND is_active = 1";
$pt_stmt = $conn->prepare($pt_sql);
$pt_stmt->bind_param('i', $account_id);
$pt_stmt->execute();
$pt_result = $pt_stmt->get_result();
while ($row = $pt_result->fetch_assoc()) {
    $payment_types[] = $row['payment_type'];
}
$pt_stmt->close();

// Get all transactions
$transactions = [];
$trans_sql = "SELECT at.*, u.full_name as processed_by_name
              FROM account_transactions at
              LEFT JOIN users u ON at.created_by = u.id
              WHERE at.account_id = ?
              ORDER BY at.created_at DESC";

$trans_stmt = $conn->prepare($trans_sql);
$trans_stmt->bind_param('i', $account_id);
$trans_stmt->execute();
$trans_result = $trans_stmt->get_result();
while ($row = $trans_result->fetch_assoc()) {
    $transactions[] = $row;
}
$trans_stmt->close();

// Get related payments
$payments = [];
$pay_sql = "SELECT p.*, t.term_name, ay.year_name
            FROM payments p
            LEFT JOIN terms t ON p.term_id = t.id
            LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
            WHERE p.student_id = ? 
            AND p.payment_type IN (SELECT payment_type FROM payment_accounts WHERE account_id = ?)
            ORDER BY p.payment_date DESC";

$pay_stmt = $conn->prepare($pay_sql);
$pay_stmt->bind_param('ii', $account['student_id'], $account_id);
$pay_stmt->execute();
$pay_result = $pay_stmt->get_result();
while ($row = $pay_result->fetch_assoc()) {
    $payments[] = $row;
}
$pay_stmt->close();

// Calculate statistics
$total_deposits = 0;
$total_withdrawals = 0;
foreach ($transactions as $trans) {
    if ($trans['transaction_type'] === 'deposit') {
        $total_deposits += $trans['amount'];
    } else {
        $total_withdrawals += $trans['amount'];
    }
}

$total_payments = array_sum(array_column($payments, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Details - <?= htmlspecialchars($account['account_number']) ?></title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <style>
        .account-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .account-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .balance-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        
        .balance-positive { color: #059669; }
        .balance-negative { color: #dc2626; }
        .balance-zero { color: #6b7280; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 600;
            color: #6b7280;
            position: relative;
        }
        
        .tab.active {
            color: #667eea;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .transaction-type {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .type-deposit {
            background: #d1fae5;
            color: #065f46;
        }
        
        .type-withdrawal {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 2px;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <!-- Account Header -->
                <div class="account-header">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h1 style="margin: 0 0 1rem 0;">
                                <i class="fas fa-piggy-bank"></i> 
                                <?= htmlspecialchars($account['account_number']) ?>
                            </h1>
                            <h3 style="margin: 0; opacity: 0.9;">
                                <?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?>
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; opacity: 0.8;">
                                <?= htmlspecialchars($account['student_code']) ?> | <?= htmlspecialchars($account['class_name']) ?>
                            </p>
                        </div>
                        <div class="no-print">
                            <button onclick="window.print()" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button onclick="exportToPDF()" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>
                    
                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Account Type</div>
                            <div class="info-value"><?= htmlspecialchars($account['account_type']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value"><?= ucfirst($account['status']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created</div>
                            <div class="info-value"><?= date('M j, Y', strtotime($account['created_at'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Linked Payment Types</div>
                            <div class="info-value">
                                <?php foreach ($payment_types as $pt): ?>
                                    <span class="payment-badge badge-primary"><?= htmlspecialchars($pt) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="balance-card">
                        <div class="stat-title">Current Balance</div>
                        <div class="balance-amount <?= $account['current_balance'] > 0 ? 'balance-positive' : ($account['current_balance'] < 0 ? 'balance-negative' : 'balance-zero') ?>">
                            GHC <?= number_format($account['current_balance'], 2) ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-title">Total Deposits</div>
                        <div class="stat-value" style="color: #059669;">
                            GHC <?= number_format($total_deposits, 2) ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-title">Total Withdrawals</div>
                        <div class="stat-value" style="color: #dc2626;">
                            GHC <?= number_format($total_withdrawals, 2) ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-title">Total Payments</div>
                        <div class="stat-value" style="color: #7c3aed;">
                            GHC <?= number_format($total_payments, 2) ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-title">Transactions Count</div>
                        <div class="stat-value"><?= count($transactions) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-title">Payments Count</div>
                        <div class="stat-value"><?= count($payments) ?></div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs no-print">
                    <button class="tab active" onclick="switchTab('transactions')">
                        <i class="fas fa-exchange-alt"></i> Account Transactions
                    </button>
                    <button class="tab" onclick="switchTab('payments')">
                        <i class="fas fa-money-bill-wave"></i> Related Payments
                    </button>
                    <button class="tab" onclick="switchTab('summary')">
                        <i class="fas fa-chart-line"></i> Summary Report
                    </button>
                </div>
                
                <!-- Tab Content: Transactions -->
                <div id="transactions-tab" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3>Account Transactions History</h3>
                        </div>
                        <div class="card-body">
                            <table id="transactionsTable" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($trans['created_at'])) ?></td>
                                        <td>
                                            <span class="transaction-type type-<?= $trans['transaction_type'] ?>">
                                                <?= ucfirst($trans['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: <?= $trans['transaction_type'] === 'deposit' ? '#059669' : '#dc2626' ?>">
                                            <?= $trans['transaction_type'] === 'deposit' ? '+' : '-' ?>GHC <?= number_format($trans['amount'], 2) ?>
                                        </td>
                                        <td><?= htmlspecialchars($trans['description']) ?></td>
                                        <td><?= htmlspecialchars($trans['reference_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($trans['processed_by_name']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Content: Payments -->
                <div id="payments-tab" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3>Related Payments</h3>
                        </div>
                        <div class="card-body">
                            <table id="paymentsTable" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Receipt No.</th>
                                        <th>Date</th>
                                        <th>Payment Type</th>
                                        <th>Term</th>
                                        <th>Academic Year</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($pay['receipt_no']) ?></strong></td>
                                        <td><?= date('M j, Y', strtotime($pay['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($pay['payment_type']) ?></td>
                                        <td><?= htmlspecialchars($pay['term_name']) ?></td>
                                        <td><?= htmlspecialchars($pay['year_name']) ?></td>
                                        <td style="font-weight: 600;">GHC <?= number_format($pay['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($pay['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($pay['status']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Content: Summary -->
                <div id="summary-tab" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3>Account Summary Report</h3>
                        </div>
                        <div class="card-body">
                            <div style="max-width: 800px; margin: 0 auto;">
                                <h4>Account Overview</h4>
                                <table style="width: 100%; margin-bottom: 2rem;">
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Account Number:</td>
                                        <td style="padding: 0.5rem;"><?= htmlspecialchars($account['account_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Student:</td>
                                        <td style="padding: 0.5rem;"><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Account Type:</td>
                                        <td style="padding: 0.5rem;"><?= htmlspecialchars($account['account_type']) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Status:</td>
                                        <td style="padding: 0.5rem;"><?= ucfirst($account['status']) ?></td>
                                    </tr>
                                </table>
                                
                                <h4>Financial Summary</h4>
                                <table style="width: 100%; margin-bottom: 2rem; border-collapse: collapse;">
                                    <tr style="background: #f9fafb;">
                                        <td style="padding: 0.75rem; font-weight: 600;">Opening Balance:</td>
                                        <td style="padding: 0.75rem; text-align: right;">GHC <?= number_format($total_deposits - $total_withdrawals - $account['current_balance'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.75rem; font-weight: 600; color: #059669;">Total Deposits:</td>
                                        <td style="padding: 0.75rem; text-align: right; color: #059669;">+ GHC <?= number_format($total_deposits, 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.75rem; font-weight: 600; color: #dc2626;">Total Withdrawals:</td>
                                        <td style="padding: 0.75rem; text-align: right; color: #dc2626;">- GHC <?= number_format($total_withdrawals, 2) ?></td>
                                    </tr>
                                    <tr style="background: #f9fafb; border-top: 2px solid #d1d5db;">
                                        <td style="padding: 0.75rem; font-weight: 700; font-size: 1.1rem;">Current Balance:</td>
                                        <td style="padding: 0.75rem; text-align: right; font-weight: 700; font-size: 1.1rem;">
                                            GHC <?= number_format($account['current_balance'], 2) ?>
                                        </td>
                                    </tr>
                                </table>
                                
                                <h4>Activity Summary</h4>
                                <table style="width: 100%;">
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Total Transactions:</td>
                                        <td style="padding: 0.5rem;"><?= count($transactions) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Total Payments:</td>
                                        <td style="padding: 0.5rem;"><?= count($payments) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Last Transaction:</td>
                                        <td style="padding: 0.5rem;">
                                            <?= $account['last_transaction_date'] ? date('M j, Y g:i A', strtotime($account['last_transaction_date'])) : 'Never' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 0.5rem; font-weight: 600;">Created By:</td>
                                        <td style="padding: 0.5rem;"><?= htmlspecialchars($account['created_by_name']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="no-print" style="margin-top: 2rem;">
                    <a href="accounts.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Accounts
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#transactionsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
            
            $('#paymentsTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
        });
        
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add header
            doc.setFontSize(18);
            doc.text('Account Statement', 14, 20);
            
            doc.setFontSize(12);
            doc.text('Account Number: <?= $account['account_number'] ?>', 14, 30);
            doc.text('Student: <?= $account['first_name'] . ' ' . $account['last_name'] ?>', 14, 37);
            doc.text('Generated: ' + new Date().toLocaleDateString(), 14, 44);
            
            // Add balance info
            doc.setFontSize(14);
            doc.text('Current Balance: GHC <?= number_format($account['current_balance'], 2) ?>', 14, 54);
            
            // Add transactions table
            doc.autoTable({
                startY: 60,
                head: [['Date', 'Type', 'Amount', 'Description']],
                body: [
                    <?php foreach ($transactions as $trans): ?>
                    [
                        '<?= date('M j, Y', strtotime($trans['created_at'])) ?>',
                        '<?= ucfirst($trans['transaction_type']) ?>',
                        '<?= ($trans['transaction_type'] === 'deposit' ? '+' : '-') . number_format($trans['amount'], 2) ?>',
                        '<?= addslashes($trans['description']) ?>'
                    ],
                    <?php endforeach; ?>
                ]
            });
            
            doc.save('account_statement_<?= $account['account_number'] ?>.pdf');
        }
    </script>
</body>
</html>