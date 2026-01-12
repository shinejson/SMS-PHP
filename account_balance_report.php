<?php
// account_balance_report.php - Comprehensive report of all student accounts
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

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_class = isset($_GET['class']) ? intval($_GET['class']) : 0;
$filter_account_type = isset($_GET['account_type']) ? $_GET['account_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_status)) {
    $where_conditions[] = "sa.status = ?";
    $params[] = $filter_status;
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all accounts with detailed information
$accounts_sql = "SELECT sa.*, 
                        s.first_name, s.last_name, s.student_id as student_code,
                        c.class_name,
                        (SELECT COUNT(*) FROM account_transactions WHERE account_id = sa.id) as transaction_count,
                        (SELECT SUM(amount) FROM account_transactions WHERE account_id = sa.id AND transaction_type = 'deposit') as total_deposits,
                        (SELECT SUM(amount) FROM account_transactions WHERE account_id = sa.id AND transaction_type = 'withdrawal') as total_withdrawals,
                        (SELECT GROUP_CONCAT(payment_type SEPARATOR ', ') FROM payment_accounts WHERE account_id = sa.id AND is_active = 1) as linked_payment_types
                 FROM student_accounts sa
                 JOIN students s ON sa.student_id = s.id
                 LEFT JOIN classes c ON s.class_id = c.id
                 $where_clause
                 ORDER BY sa.current_balance DESC, s.first_name, s.last_name";

$accounts_stmt = $conn->prepare($accounts_sql);
if (!empty($params)) {
    $accounts_stmt->bind_param($types, ...$params);
}
$accounts_stmt->execute();
$accounts_result = $accounts_stmt->get_result();

$accounts = [];
$total_balance = 0;
$total_positive = 0;
$total_negative = 0;
$positive_count = 0;
$negative_count = 0;
$zero_count = 0;

while ($row = $accounts_result->fetch_assoc()) {
    $balance = floatval($row['current_balance']);
    $total_balance += $balance;
    
    if ($balance > 0) {
        $total_positive += $balance;
        $positive_count++;
    } elseif ($balance < 0) {
        $total_negative += abs($balance);
        $negative_count++;
    } else {
        $zero_count++;
    }
    
    $accounts[] = $row;
}
$accounts_stmt->close();

// Get classes for filter
$classes = [];
$classes_sql = "SELECT id, class_name FROM classes ORDER BY class_name";
$classes_result = $conn->query($classes_sql);
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row;
}

// Get account types for filter
$account_types = ['Tuition', 'Extra Class', 'PTA', 'General'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Balance Report</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-title {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .summary-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .positive { color: #059669; }
        .negative { color: #dc2626; }
        .neutral { color: #6b7280; }
        
        .filter-panel {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .balance-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .indicator-positive { background: #059669; }
        .indicator-negative { background: #dc2626; }
        .indicator-zero { background: #6b7280; }
        
        @media print {
            .no-print { display: none !important; }
            .summary-cards { page-break-inside: avoid; }
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
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1 style="margin: 0 0 0.5rem 0;">
                                <i class="fas fa-chart-bar"></i> Account Balance Report
                            </h1>
                            <p style="margin: 0; opacity: 0.9;">
                                Comprehensive overview of all student accounts
                            </p>
                        </div>
                        <div class="no-print">
                            <button onclick="window.print()" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button onclick="exportToExcel()" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-title">Total Accounts</div>
                        <div class="summary-value"><?= count($accounts) ?></div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-title">Total Balance</div>
                        <div class="summary-value <?= $total_balance >= 0 ? 'positive' : 'negative' ?>">
                            GHC <?= number_format(abs($total_balance), 2) ?>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-title">Positive Balances</div>
                        <div class="summary-value positive">
                            <?= $positive_count ?> (GHC <?= number_format($total_positive, 2) ?>)
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-title">Negative Balances</div>
                        <div class="summary-value negative">
                            <?= $negative_count ?> (GHC <?= number_format($total_negative, 2) ?>)
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-title">Zero Balances</div>
                        <div class="summary-value neutral">
                            <?= $zero_count ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Panel -->
                <div class="filter-panel no-print">
                    <h3 style="margin: 0 0 1rem 0;">
                        <i class="fas fa-filter"></i> Filters
                    </h3>
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>Closed</option>
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
                            
                            <div class="form-group">
                                <label>Account Type</label>
                                <select name="account_type" class="form-control">
                                    <option value="">All Types</option>
                                    <?php foreach ($account_types as $type): ?>
                                        <option value="<?= $type ?>" <?= $filter_account_type === $type ? 'selected' : '' ?>>
                                            <?= $type ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="account_balance_report.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Accounts Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Account Details</h3>
                    </div>
                    <div class="card-body">
                        <table id="accountsTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Account No.</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Balance</th>
                                    <th>Deposits</th>
                                    <th>Withdrawals</th>
                                    <th>Transactions</th>
                                    <th>Payment Types</th>
                                    <th>Status</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                <?php 
                                    $balance = floatval($account['current_balance']);
                                    $indicator_class = $balance > 0 ? 'indicator-positive' : ($balance < 0 ? 'indicator-negative' : 'indicator-zero');
                                    $balance_class = $balance > 0 ? 'positive' : ($balance < 0 ? 'negative' : 'neutral');
                                ?>
                                <tr>
                                    <td><span class="balance-indicator <?= $indicator_class ?>"></span></td>
                                    <td><strong><?= htmlspecialchars($account['account_number']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?>
                                        <br><small><?= htmlspecialchars($account['student_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($account['class_name']) ?></td>
                                    <td><?= htmlspecialchars($account['account_type']) ?></td>
                                    <td class="<?= $balance_class ?>" style="font-weight: 600;">
                                        GHC <?= number_format($balance, 2) ?>
                                    </td>
                                    <td style="color: #059669;">
                                        GHC <?= number_format($account['total_deposits'] ?? 0, 2) ?>
                                    </td>
                                    <td style="color: #dc2626;">
                                        GHC <?= number_format($account['total_withdrawals'] ?? 0, 2) ?>
                                    </td>
                                    <td><?= $account['transaction_count'] ?></td>
                                    <td>
                                        <small><?= htmlspecialchars($account['linked_payment_types'] ?? 'None') ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $account['status'] ?>">
                                            <?= ucfirst($account['status']) ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <a href="account_details.php?id=<?= $account['id'] ?>" class="btn-icon small">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f9fafb; font-weight: 600;">
                                    <td colspan="5" style="text-align: right; padding: 1rem;">TOTALS:</td>
                                    <td class="<?= $total_balance >= 0 ? 'positive' : 'negative' ?>" style="padding: 1rem;">
                                        GHC <?= number_format(abs($total_balance), 2) ?>
                                    </td>
                                    <td style="color: #059669; padding: 1rem;">
                                        GHC <?= number_format($total_positive, 2) ?>
                                    </td>
                                    <td style="color: #dc2626; padding: 1rem;">
                                        GHC <?= number_format($total_negative, 2) ?>
                                    </td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    <script>
        $(document).ready(function() {
            $('#accountsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Export to Excel',
                        className: 'btn btn-success',
                        exportOptions: {
                            columns: ':not(.no-print)'
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> Export to CSV',
                        className: 'btn btn-info',
                        exportOptions: {
                            columns: ':not(.no-print)'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-secondary',
                        exportOptions: {
                            columns: ':not(.no-print)'
                        }
                    }
                ],
                order: [[5, 'desc']], // Sort by balance
                pageLength: 50,
                footerCallback: function() {
                    // Footer already rendered in PHP
                }
            });
        });
        
        function exportToExcel() {
            $('#accountsTable').DataTable().button('.buttons-excel').trigger();
        }
    </script>
</body>
</html>