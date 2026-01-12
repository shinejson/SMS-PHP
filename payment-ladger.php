<?php
require_once 'config.php';
require_once 'session.php';

/**
 * Helper: Adds parameter safely
 */
function add_param(&$params, &$types, $value, $type = 'i') {
    $params[] = $value;
    $types .= $type;
}

// ----------------------
// 1. Get current academic year if not specified
// ----------------------
$current_academic_year_id = $_GET['academic_year_id'] ?? null;
if (!$current_academic_year_id) {
    $res = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_academic_year_id = (int) $res->fetch_assoc()['id'];
    }
}
if (!$current_academic_year_id) {
    // Fallback to latest year if no current marked
    $res = $conn->query("SELECT id FROM academic_years ORDER BY year_name DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_academic_year_id = (int) $res->fetch_assoc()['id'];
    }
}

// ----------------------
// 2. Filters
// ----------------------
$selected_term_id  = isset($_GET['term_id']) && $_GET['term_id'] !== '' ? (int)$_GET['term_id'] : null;
$selected_class_id = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$payment_status_filter = $_GET['status'] ?? null;
$selected_payment_type = $_GET['payment_type'] ?? null;

// Get payment types for dropdown
$payment_types = [];
$sql_pt = "SELECT DISTINCT payment_type FROM billing WHERE payment_type IS NOT NULL ORDER BY payment_type";
$result_pt = $conn->query($sql_pt);
while ($row = $result_pt->fetch_assoc()) {
    $payment_types[] = $row['payment_type'];
}

// Build the SQL query and parameters dynamically
$params = [];
$param_types = '';

// Start with the base query - simplified without JSON functions
$sql = "
SELECT 
    s.id AS student_id,
    s.student_id AS student_code,
    s.first_name,
    s.last_name,
    c.id AS class_id,
    c.class_name,
    
    -- Get billing total
    COALESCE(SUM(b.amount), 0) AS total_billing,
    
    -- Get payments total
    COALESCE(SUM(p.amount), 0) AS total_paid
    
FROM students s
LEFT JOIN classes c ON s.class_id = c.id
LEFT JOIN billing b ON b.class_id = s.class_id 
    AND b.academic_year_id = ?
LEFT JOIN payments p ON p.student_id = s.id 
    AND p.academic_year_id = ?
";

add_param($params, $param_types, $current_academic_year_id);
add_param($params, $param_types, $current_academic_year_id);

// Add WHERE clause for active students
$sql .= " WHERE s.status = 'Active'";

// Add term filter if selected
if ($selected_term_id) {
    $sql .= " AND b.term_id = ? AND p.term_id = ?";
    add_param($params, $param_types, $selected_term_id);
    add_param($params, $param_types, $selected_term_id);
}

// Add class filter if selected
if ($selected_class_id) {
    $sql .= " AND s.class_id = ?";
    add_param($params, $param_types, $selected_class_id);
}

// Complete the query
$sql .= "
GROUP BY s.id, s.student_id, s.first_name, s.last_name, c.id, c.class_name
ORDER BY c.class_name, s.first_name, s.last_name
";

// Debug logging
if (ini_get('display_errors')) {
    error_log("Ledger SQL:\n" . $sql);
    error_log("Params: " . json_encode($params));
    error_log("Param types: " . $param_types);
}

// ----------------------
// 3. Prepare & Execute Query
// ----------------------
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Failed to prepare query.");
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ----------------------
// 4. Post-process Results (compute balance + status)
// ----------------------
$students_ledger = [];
while ($row = $result->fetch_assoc()) {
    $total_billing = (float)($row['total_billing'] ?? 0);
    $total_paid    = (float)($row['total_paid'] ?? 0);
    $balance       = $total_billing - $total_paid;

    // Determine payment status
    $payment_status = 'No Billing';
    $status_class   = 'no-billing';

    if ($total_billing > 0) {
        if ($total_paid == 0) {
            $payment_status = 'Not Paid';
            $status_class   = 'not-paid';
        } elseif ($total_paid < $total_billing) {
            $payment_status = 'Part Payment';
            $status_class   = 'part-payment';
        } elseif ($total_paid == $total_billing) {
            $payment_status = 'Full Payment';
            $status_class   = 'full-payment';
        } elseif ($total_paid > $total_billing) {
            $payment_status = 'Overpayment';
            $status_class   = 'overpayment';
        }
    } elseif ($total_paid > 0) {
        $payment_status = 'Paid (No Billing)';
        $status_class   = 'paid-no-billing';
    }

    // Apply filter if user selected a specific status
    if ($payment_status_filter && $payment_status !== $payment_status_filter) {
        continue;
    }

    $row['total_billing']     = $total_billing;
    $row['total_paid']        = $total_paid;
    $row['balance']           = $balance;
    $row['payment_status']    = $payment_status;
    $row['status_class']      = $status_class;
    
    // Get payment breakdown for this student
    $breakdown_sql = "
        SELECT 
            p.payment_type,
            COALESCE((
                SELECT SUM(b.amount) 
                FROM billing b 
                WHERE b.class_id = ?
                AND b.academic_year_id = ?
                AND b.payment_type = p.payment_type
                " . ($selected_term_id ? " AND b.term_id = ?" : "") . "
            ), 0) AS billed,
            SUM(p.amount) AS paid
        FROM payments p
        WHERE p.student_id = ?
        AND p.academic_year_id = ?
        " . ($selected_term_id ? " AND p.term_id = ?" : "") . "
        GROUP BY p.payment_type
    ";
    
    $breakdown_params = [];
    $breakdown_types = '';
    add_param($breakdown_params, $breakdown_types, $row['class_id']);
    add_param($breakdown_params, $breakdown_types, $current_academic_year_id);
    if ($selected_term_id) {
        add_param($breakdown_params, $breakdown_types, $selected_term_id);
    }
    add_param($breakdown_params, $breakdown_types, $row['student_id']);
    add_param($breakdown_params, $breakdown_types, $current_academic_year_id);
    if ($selected_term_id) {
        add_param($breakdown_params, $breakdown_types, $selected_term_id);
    }
    
    $breakdown_stmt = $conn->prepare($breakdown_sql);
    if ($breakdown_stmt) {
        $breakdown_stmt->bind_param($breakdown_types, ...$breakdown_params);
        $breakdown_stmt->execute();
        $breakdown_result = $breakdown_stmt->get_result();
        
        $payment_breakdown = [];
        while ($breakdown_row = $breakdown_result->fetch_assoc()) {
            $breakdown_row['balance'] = $breakdown_row['billed'] - $breakdown_row['paid'];
            $payment_breakdown[] = $breakdown_row;
        }
        $row['payment_breakdown'] = $payment_breakdown;
        $breakdown_stmt->close();
    } else {
        $row['payment_breakdown'] = [];
    }

    $students_ledger[] = $row;
}
$stmt->close();

// 5. Calculate Summary Statistics with payment type filter
$total_students = count($students_ledger);
$total_billing_amount = 0;
$total_paid_amount = 0;

foreach ($students_ledger as $student) {
    if ($selected_payment_type) {
        // Filter by payment type if selected
        foreach ($student['payment_breakdown'] as $breakdown) {
            if ($breakdown['payment_type'] === $selected_payment_type) {
                $total_billing_amount += $breakdown['billed'];
                $total_paid_amount += $breakdown['paid'];
            }
        }
    } else {
        // Use total amounts if no payment type filter
        $total_billing_amount += $student['total_billing'];
        $total_paid_amount += $student['total_paid'];
    }
}

$total_outstanding = $total_billing_amount - $total_paid_amount;

// 6. Get Dropdown Data
$academic_years = [];
$sql = "SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $academic_years[] = $row;
}

$terms = [];
$sql = "SELECT id, term_name FROM terms ORDER BY term_order";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $terms[] = $row;
}

$classes = [];
$sql = "SELECT id, class_name FROM classes ORDER BY class_name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// 7. Calculate payment type breakdown more efficiently
$payment_type_totals = [];

// Initialize with all known payment types
foreach ($payment_types as $type) {
    $payment_type_totals[$type] = [
        'billed' => 0,
        'paid' => 0,
        'balance' => 0
    ];
}

// Calculate totals from the ledger data
foreach ($students_ledger as $student) {
    foreach ($student['payment_breakdown'] as $breakdown) {
        $type = $breakdown['payment_type'];
        if (isset($payment_type_totals[$type])) {
            $payment_type_totals[$type]['billed'] += $breakdown['billed'];
            $payment_type_totals[$type]['paid'] += $breakdown['paid'];
            $payment_type_totals[$type]['balance'] += $breakdown['balance'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Student Account Ledger - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="css/payment-ladger.css">
    
</head>

<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'topnav.php'; ?>
        
        <main>
            <div class="page-header">
                <h1>Student Account Ledger</h1>
                <nav class="breadcrumb">
                    <a href="index.php">Home</a> > <a href="#">Finance</a> > Account Ledger
                </nav>
            </div>

            <!-- Filters Section -->
            <div class="filter-section">
                <h3>Filter Options</h3>
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="academic_year_id">Academic Year</label>
                            <select name="academic_year_id" id="academic_year_id">
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?= $year['id'] ?>" 
                                        <?= ($year['id'] == $current_academic_year_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['year_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="term_id">Term</label>
                            <select name="term_id" id="term_id">
                                <option value="">All Terms</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['id'] ?>" 
                                        <?= ($term['id'] == $selected_term_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="class_id">Class</label>
                            <select name="class_id" id="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" 
                                        <?= ($class['id'] == $selected_class_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Payment Status</label>
                            <select name="status" id="status">
                                <option value="">All Statuses</option>
                                <option value="Not Paid" <?= ($payment_status_filter == 'Not Paid') ? 'selected' : '' ?>>Not Paid</option>
                                <option value="Part Payment" <?= ($payment_status_filter == 'Part Payment') ? 'selected' : '' ?>>Part Payment</option>
                                <option value="Full Payment" <?= ($payment_status_filter == 'Full Payment') ? 'selected' : '' ?>>Full Payment</option>
                                <option value="Overpayment" <?= ($payment_status_filter == 'Overpayment') ? 'selected' : '' ?>>Overpayment</option>
                                <option value="No Billing" <?= ($payment_status_filter == 'No Billing') ? 'selected' : '' ?>>No Billing</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-section">
                <div class="summary-header">
                    <h3>Financial Summary</h3>
                    <div class="payment-type-filter">
                        <select name="payment_type" id="payment_type_filter" onchange="this.form.submit()">
                            <option value="">All Payment Types</option>
                            <?php foreach ($payment_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" 
                                    <?= ($selected_payment_type === $type) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="summary-cards compact">
                    <div class="summary-card compact billing">
                        <div class="card-icon">💰</div>
                        <div class="card-content">
                            <h4>Total Billing</h4>
                            <div class="amount">GH₵ <?= number_format($total_billing_amount, 2) ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card compact paid">
                        <div class="card-icon">💳</div>
                        <div class="card-content">
                            <h4>Total Paid</h4>
                            <div class="amount">GH₵ <?= number_format($total_paid_amount, 2) ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card compact outstanding">
                        <div class="card-icon">⚖️</div>
                        <div class="card-content">
                            <h4>Balance</h4>
                            <div class="amount">GH₵ <?= number_format($total_outstanding, 2) ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card compact students">
                        <div class="card-icon">👥</div>
                        <div class="card-content">
                            <h4>Students</h4>
                            <div class="count"><?= $total_students ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Type Breakdown -->
            <div class="payment-type-breakdown">
                <h4>Payment Type Breakdown</h4>
                <div class="breakdown-grid">
                    <?php foreach ($payment_type_totals as $type => $totals): ?>
                        <div class="breakdown-item">
                            <h5><?= htmlspecialchars($type) ?></h5>
                            <div class="breakdown-amounts">
                                <span>Billed: GH₵ <?= number_format($totals['billed'], 2) ?></span>
                                <span>Paid: GH₵ <?= number_format($totals['paid'], 2) ?></span>
                                <span>Balance: GH₵ <?= number_format($totals['balance'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn-daily-report" onclick="loadDailyClosing()">
                    <i class="fas fa-calendar-day"></i> Daily Closing Report
                </button>
            </div>
            
            <!-- Ledger Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Student Account Details</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ledgerTable" class="display nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Student Code</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Total Billing</th>
                                    <th>Total Paid</th>
                                    <th>Balance</th>
                                    <th>Payment Status</th>
                                    <th>Payment Breakdown</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_ledger as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['student_code']) ?></td>
                                    <td>
                                        <a href="student_statement.php?student_id=<?= $student['student_id'] ?>&academic_year_id=<?= $current_academic_year_id ?><?= $selected_term_id ? '&term_id=' . $selected_term_id : '' ?>" 
                                           class="student-name-link" 
                                           title="View payment history">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($student['class_name']) ?></td>
                                    <td class="<?= $student['total_billing'] > 0 ? 'amount-positive' : 'amount-zero' ?>">
                                        GH₵ <?= number_format($student['total_billing'], 2) ?>
                                    </td>
                                    <td class="<?= $student['total_paid'] > 0 ? 'amount-positive' : 'amount-zero' ?>">
                                        GH₵ <?= number_format($student['total_paid'], 2) ?>
                                    </td>
                                    <td class="<?= $student['balance'] > 0 ? 'amount-negative' : ($student['balance'] < 0 ? 'amount-positive' : 'amount-zero') ?>">
                                        GH₵ <?= number_format($student['balance'], 2) ?>
                                    </td>
                                    <td>
                                        <span class="status <?= $student['status_class'] ?>">
                                            <?= $student['payment_status'] ?>
                                        </span>
                                    </td>
<td class="payment-breakdown">
    <?php if (!empty($student['payment_breakdown'])): ?>
        <div class="breakdown-container">
            <?php foreach ($student['payment_breakdown'] as $breakdown): 
                // Determine balance class
                $balance_class = 'zero';
                if ($breakdown['balance'] > 0) {
                    $balance_class = 'positive';
                } elseif ($breakdown['balance'] < 0) {
                    $balance_class = 'negative';
                }
            ?>
                <div class="breakdown-item">
                    <div class="breakdown-type"><?= htmlspecialchars($breakdown['payment_type']) ?></div>
                    <div class="breakdown-details">
                        <span class="breakdown-label">Billed:</span>
                        <span class="breakdown-value">GH₵ <?= number_format($breakdown['billed'], 2) ?></span>
                    </div>
                    <div class="breakdown-details">
                        <span class="breakdown-label">Paid:</span>
                        <span class="breakdown-value">GH₵ <?= number_format($breakdown['paid'], 2) ?></span>
                    </div>
                    <div class="breakdown-details">
                        <span class="breakdown-label">Balance:</span>
                        <span class="breakdown-value <?= $balance_class ?>">GH₵ <?= number_format($breakdown['balance'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="text-muted">No billing records</span>
    <?php endif; ?>
</td>
                                    <td>
                                        <a href="student_statement.php?student_id=<?= $student['student_id'] ?>&academic_year_id=<?= $current_academic_year_id ?><?= $selected_term_id ? '&term_id=' . $selected_term_id : '' ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="View full statement">
                                            <i class="fas fa-file-alt"></i>
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

    <!-- Daily Closing Modal -->
    <div id="dailyClosingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDailyModal()">&times;</span>
            <h2>Daily Account Closing Report</h2>
            
            <!-- Filter Controls -->
            <div class="modal-filters">
                <h3>Report Filters</h3>
                <form id="dailyReportForm" onsubmit="loadDailyClosing(); return false;">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="modal_date_range">Date Range</label>
                            <select id="modal_date_range" onchange="toggleCustomDate()">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" id="customDateGroup" style="display: none;">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="filter-group" id="customEndDateGroup" style="display: none;">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="modal_academic_year">Academic Year</label>
                            <select id="modal_academic_year">
                                <option value="">All Academic Years</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?= $year['id'] ?>" 
                                        <?= ($current_academic_year_id == $year['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['year_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="modal_term">Term</label>
                            <select id="modal_term">
                                <option value="">All Terms</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['id'] ?>" 
                                        <?= ($selected_term_id == $term['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="modal_class">Class</label>
                            <select id="modal_class">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" 
                                        <?= ($selected_class_id == $class['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="modal_payment_method">Payment Method</label>
                            <select id="modal_payment_method">
                                <option value="">All Methods</option>
                                <option value="Cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-body">
                <div class="report-period">
                    <h4>Report Period: <span id="reportPeriodText"></span></h4>
                </div>
                
                <div class="daily-summary">
                    <div class="summary-item">
                        <h4>Total Collections</h4>
                        <div class="amount" id="dailyTotal">GH₵ 0.00</div>
                    </div>
                    <div class="summary-item">
                        <h4>Number of Transactions</h4>
                        <div class="count" id="dailyTransactions">0</div>
                    </div>
                    <div class="summary-item">
                        <h4>Payment Methods Breakdown</h4>
                        <div id="paymentMethods">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    <div class="summary-item">
                        <h4>Class-wise Summary</h4>
                        <div id="classSummary">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="transaction-details">
                    <h4>Transaction Details</h4>
                    <div class="table-responsive">
                        <table id="dailyTransactionsTable" class="display compact" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt No</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th>Collected By</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsBody">
                                <!-- Transactions will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-export" onclick="exportDailyReport('csv')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn-export" onclick="exportDailyReport('excel')">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn-export" onclick="exportDailyReport('pdf')">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn-print" onclick="printDailyReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button class="btn-close" onclick="closeDailyModal()">
                    Close
                </button>
            </div>
        </div>
    </div>

<!-- Load jQuery and other dependencies first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Load DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<!-- Load jsPDF FIRST, then autoTable plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- Other optional libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Your custom JavaScript files -->
<script src="js/payment-ladger.js"></script>
<script src="js/dashboard.js"></script>
<script src="js/darkmode.js"></script>

</body>
</html>