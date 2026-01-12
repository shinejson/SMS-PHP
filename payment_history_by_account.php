<?php
// payment_history_by_account.php - Detailed payment history analysis
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

// Get filters
$filter_account_type = isset($_GET['account_type']) ? $_GET['account_type'] : '';
$filter_payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$filter_class = isset($_GET['class']) ? intval($_GET['class']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build WHERE clause for payments
$where_conditions = ["p.payment_date BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = 'ss';

if (!empty($filter_payment_type)) {
    $where_conditions[] = "p.payment_type = ?";
    $params[] = $filter_payment_type;
    $types .= 's';
}

if ($filter_class > 0) {
    $where_conditions[] = "s.class_id = ?";
    $params[] = $filter_class;
    $types .= 'i';
}

if (!empty($filter_account_type)) {
    $where_conditions[] = "sa.account_type = ?";
    $params[] = $filter_account_type;
    $types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get payment history with linked accounts
$payments_sql = "SELECT p.*,
                        s.first_name, s.last_name, s.student_id as student_code,
                        c.class_name,
                        t.term_name,
                        ay.year_name,
                        sa.account_number, sa.account_type, sa.current_balance,
                        at.id as transaction_id, at.description as transaction_desc
                 FROM payments p
                 JOIN students s ON p.student_id = s.id
                 LEFT JOIN classes c ON s.class_id = c.id
                 LEFT JOIN terms t ON p.term_id = t.id
                 LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
                 LEFT JOIN payment_accounts pa ON p.student_id = pa.student_id AND p.payment_type = pa.payment_type
                 LEFT JOIN student_accounts sa ON pa.account_id = sa.id
                 LEFT JOIN account_transactions at ON sa.id = at.account_id AND at.reference_number = p.receipt_no
                 $where_clause
                 ORDER BY p.payment_date DESC, p.created_at DESC";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param($types, ...$params);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

$payments = [];
$total_payments = 0;
$payments_with_accounts = 0;
$payments_without_accounts = 0;

while ($row = $payments_result->fetch_assoc()) {
    $total_payments += $row['amount'];
    if ($row['account_number']) {
        $payments_with_accounts++;
    } else {
        $payments_without_accounts++;
    }
    $payments[] = $row;
}
$payments_stmt->close();

// Get summary by account type
$summary_sql = "SELECT sa.account_type,
                       COUNT(DISTINCT sa.id) as account_count,
                       COUNT(p.id) as payment_count,
                       SUM(p.amount) as total_amount
                FROM payments p
                JOIN students s ON p.student_id = s.id
                LEFT JOIN payment_accounts pa ON p.student_id = pa.student_id AND p.payment_type = pa.payment_type
                LEFT JOIN student_accounts sa ON pa.account_id = sa.id
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY sa.account_type
                ORDER BY total_amount DESC";

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param('ss', $date_from, $date_to);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();

$account_type_summary = [];
while ($row = $summary_result->fetch_assoc()) {
    $account_type_summary[] = $row;
}
$summary_stmt->close();

// Get summary by payment type
$payment_type_sql = "SELECT p.payment_type,
                            COUNT(p.id) as payment_count,
                            SUM(p.amount) as total_amount,
                            COUNT(DISTINCT sa.id) as linked_accounts
                     FROM payments p
                     LEFT JOIN payment_accounts pa ON p.student_id = pa.student_id AND p.payment_type = pa.payment_type
                     LEFT JOIN student_accounts sa ON pa.account_id = sa.id
                     WHERE p.payment_date BETWEEN ? AND ?
                     GROUP BY p.payment_type
                     ORDER BY total_amount DESC";

$pt_stmt = $conn->prepare($payment_type_sql);
$pt_stmt->bind_param('ss', $date_from, $date_to);
$pt_stmt->execute();
$pt_result = $pt_stmt->get_result();

$payment_type_summary = [];
while ($row = $pt_result->fetch_assoc()) {
    $payment_type_summary[] = $row;
}
$pt_stmt->close();

// Get classes for filter
$classes = [];
$classes_sql = "SELECT id, class_name FROM classes ORDER BY class_name";
$classes_result = $conn->query($classes_sql);
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row;
}

// Get payment types for filter
$payment_types = [];
$pt_filter_sql = "SELECT DISTINCT payment_type FROM billing ORDER BY payment_type";
$pt_filter_result = $conn->query($pt_filter_sql);
while ($row = $pt_filter_result->fetch_assoc()) {
    $payment_types[] = $row['payment_type'];
}

$account_types = ['Tuition', 'Extra Class', 'PTA', 'General'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History by Account</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
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
        
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .summary-table th,
        .summary-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .summary-table th {
            background: #f9fafb;
            font-weight: 600;
        }
        
        .linked-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .not-linked-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <!-- Report Header -->
                <div class="report-header">
                    <h1 style="margin: 0 0 0.5rem 0;">
                        <i class="fas fa-history"></i> Payment History & Account Analysis
                    </h1>
                    <p style="margin: 0; opacity: 0.9;">
                        <?= date('M j, Y', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?>
                    </p>
                </div>
                
                <!-- Statistics Row -->
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-title">Total Payments</div>
                        <div class="stat-value"><?= count($payments) ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-title">Total Amount</div>
                        <div class="stat-value" style="color: #7c3aed;">
                            GHC <?= number_format($total_payments, 2) ?>
                        </div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-title">With Linked Accounts</div>
                        <div class="stat-value" style="color: #059669;">
                            <?= $payments_with_accounts ?>
                        </div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-title">Without Accounts</div>
                        <div class="stat-value" style="color: #f59e0b;">
                            <?= $payments_without_accounts ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Panel -->
                <div class="card no-print" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div class="form-group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" value="<?= $date_from ?>" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" value="<?= $date_to ?>" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Payment Type</label>
                                    <select name="payment_type" class="form-control">
                                        <option value="">All Payment Types</option>
                                        <?php foreach ($payment_types as $pt): ?>
                                            <option value="<?= $pt ?>" <?= $filter_payment_type === $pt ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Account Type</label>
                                    <select name="account_type" class="form-control">
                                        <option value="">All Account Types</option>
                                        <?php foreach ($account_types as $type): ?>
                                            <option value="<?= $type ?>" <?= $filter_account_type === $type ? 'selected' : '' ?>>
                                                <?= $type ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Class</label>
                                    <select name="class" class="form-control">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?= $class['id'] ?>" <?= $filter_class == $class['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($class['class_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <a href="payment_history_by_account.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="chart-grid">
                    <div class="chart-container">
                        <h4>Payments by Account Type</h4>
                        <canvas id="accountTypeChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h4>Payments by Payment Type</h4>
                        <canvas id="paymentTypeChart"></canvas>
                    </div>
                </div>
                
                <!-- Summary Tables -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Summary by Account Type</h3>
                        </div>
                        <div class="card-body">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th>Account Type</th>
                                        <th>Payments</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($account_type_summary as $summary): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($summary['account_type'] ?? 'No Account') ?></td>
                                        <td><?= $summary['payment_count'] ?></td>
                                        <td style="font-weight: 600;">GHC <?= number_format($summary['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Summary by Payment Type</h3>
                        </div>
                        <div class="card-body">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th>Payment Type</th>
                                        <th>Count</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_type_summary as $summary): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($summary['payment_type']) ?></td>
                                        <td><?= $summary['payment_count'] ?></td>
                                        <td style="font-weight: 600;">GHC <?= number_format($summary['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Payment History -->
                <div class="card">
                    <div class="card-header">
                        <h3>Detailed Payment History</h3>
                    </div>
                    <div class="card-body">
                        <table id="paymentsTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Payment Type</th>
                                    <th>Amount</th>
                                    <th>Account</th>
                                    <th>Account Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($payment['receipt_no']) ?></strong></td>
                                    <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                                        <br><small><?= htmlspecialchars($payment['class_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($payment['payment_type']) ?></td>
                                    <td style="font-weight: 600;">GHC <?= number_format($payment['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($payment['account_number']): ?>
                                            <a href="account_details.php?id=<?= $payment['account_number'] ?>">
                                                <?= htmlspecialchars($payment['account_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">Not Linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($payment['account_type'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($payment['transaction_id']): ?>
                                            <span class="linked-badge">Linked</span>
                                        <?php else: ?>
                                            <span class="not-linked-badge">Not Linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="print_receipt.php?id=<?= $payment['id'] ?>" class="btn-icon small">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    <script>
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
        });
        
        // Account Type Chart
        const accountTypeCtx = document.getElementById('accountTypeChart').getContext('2d');
        new Chart(accountTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($account_type_summary, 'account_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($account_type_summary, 'total_amount')) ?>,
                    backgroundColor: ['#667eea', '#f093fb', '#4ade80', '#fbbf24', '#f87171']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Payment Type Chart
        const paymentTypeCtx = document.getElementById('paymentTypeChart').getContext('2d');
        new Chart(paymentTypeCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($payment_type_summary, 'payment_type')) ?>,
                datasets: [{
                    label: 'Amount (GHC)',
                    data: <?= json_encode(array_column($payment_type_summary, 'total_amount')) ?>,
                    backgroundColor: '#7c3aed'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>