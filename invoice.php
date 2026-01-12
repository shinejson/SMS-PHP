<?php
require_once 'config.php';
require_once 'session.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check permission for invoice management
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'accountant') {
    header('Location: unauthorized.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_invoice'])) {
        // Validate and sanitize inputs
        $student_id = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
        $academic_year_id = filter_var($_POST['academic_year_id'], FILTER_VALIDATE_INT);
        $term_id = filter_var($_POST['term_id'], FILTER_VALIDATE_INT);
        $invoice_type = htmlspecialchars(trim($_POST['invoice_type']));
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $due_date = $_POST['due_date'];
        $description = htmlspecialchars(trim($_POST['description']));
        
        // Validate required fields
        if (!$student_id || !$academic_year_id || !$term_id || !$invoice_type || !$amount || !$due_date) {
            $error_message = "Please fill in all required fields correctly.";
        } elseif ($amount <= 0) {
            $error_message = "Amount must be greater than zero.";
        } elseif (strtotime($due_date) < strtotime(date('Y-m-d'))) {
            $error_message = "Due date cannot be in the past.";
        } else {
            // Generate unique invoice number
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if invoice number already exists (unlikely but safe)
            $check_sql = "SELECT id FROM invoices WHERE invoice_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $invoice_number);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                // Regenerate if duplicate
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            $check_stmt->close();
            
            $sql = "INSERT INTO invoices (invoice_number, student_id, academic_year_id, term_id, invoice_type, amount, due_date, description, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, NOW())";
$stmt = $conn->prepare($sql);

// Count the parameters: invoice_number (s), student_id (i), academic_year_id (i), term_id (i), 
// invoice_type (s), amount (d), due_date (s), description (s), created_by (i)
// That's 9 parameters total: s i i i s d s s i
$stmt->bind_param("siiisdssi", $invoice_number, $student_id, $academic_year_id, $term_id, $invoice_type, $amount, $due_date, $description, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_message = "Invoice created successfully! Invoice Number: " . $invoice_number;
                
                // Log the action
                logAction($_SESSION['user_id'], 'CREATE_INVOICE', 'Created invoice: ' . $invoice_number);
            } else {
                $error_message = "Error creating invoice: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if (isset($_POST['update_status'])) {
        $invoice_id = filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT);
        $status = htmlspecialchars(trim($_POST['status']));
        
        if (!$invoice_id || !in_array($status, ['paid', 'unpaid', 'overdue', 'cancelled'])) {
            $error_message = "Invalid invoice status update request.";
        } else {
            $payment_date = $status === 'paid' ? date('Y-m-d') : null;
            
            $sql = "UPDATE invoices SET status = ?, payment_date = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $payment_date, $invoice_id);
            
            if ($stmt->execute()) {
                $success_message = "Invoice status updated successfully!";
                
                // Log the action
                logAction($_SESSION['user_id'], 'UPDATE_INVOICE_STATUS', 'Updated invoice ID ' . $invoice_id . ' to ' . $status);
            } else {
                $error_message = "Error updating invoice status: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if (isset($_POST['bulk_action'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_invoices = $_POST['selected_invoices'] ?? [];
        
        if (empty($selected_invoices)) {
            $error_message = "No invoices selected for bulk action.";
        } else {
            $placeholders = str_repeat('?,', count($selected_invoices) - 1) . '?';
            $invoice_ids = array_map('intval', $selected_invoices);
            
            switch ($bulk_action) {
                case 'mark_paid':
                    $sql = "UPDATE invoices SET status = 'paid', payment_date = NOW() WHERE id IN ($placeholders)";
                    $action_message = "marked as paid";
                    $log_action = 'BULK_MARK_PAID';
                    break;
                case 'mark_overdue':
                    $sql = "UPDATE invoices SET status = 'overdue' WHERE id IN ($placeholders)";
                    $action_message = "marked as overdue";
                    $log_action = 'BULK_MARK_OVERDUE';
                    break;
                case 'delete':
                    $sql = "UPDATE invoices SET status = 'cancelled' WHERE id IN ($placeholders)";
                    $action_message = "cancelled";
                    $log_action = 'BULK_CANCEL_INVOICES';
                    break;
                default:
                    $error_message = "Invalid bulk action.";
                    break;
            }
            
            if (isset($sql)) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(str_repeat('i', count($invoice_ids)), ...$invoice_ids);
                
                if ($stmt->execute()) {
                    $success_message = count($selected_invoices) . " invoices " . $action_message . " successfully!";
                    logAction($_SESSION['user_id'], $log_action, 'Bulk action on ' . count($selected_invoices) . ' invoices');
                } else {
                    $error_message = "Error performing bulk action: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get filter parameters with validation
$filter_status = in_array($_GET['status'] ?? '', ['paid', 'unpaid', 'overdue', 'cancelled']) ? $_GET['status'] : '';
$filter_type = in_array($_GET['type'] ?? '', ['tuition', 'transport', 'meals', 'books', 'uniform', 'other']) ? $_GET['type'] : '';
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query with parameter binding for security
$where_conditions = ["i.status != 'deleted'"];
$params = [];
$param_types = '';

if (!empty($filter_status)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_type)) {
    $where_conditions[] = "i.invoice_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM invoices i JOIN students s ON i.student_id = s.id $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get invoices with student information
$sql = "SELECT i.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id as student_number,
               ay.year_name,
               t.term_name,
               DATEDIFF(i.due_date, CURDATE()) as days_until_due
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
        LEFT JOIN terms t ON i.term_id = t.id
        $where_clause
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$invoices = $stmt->get_result();

// Get students for dropdown (only active students)
$students_sql = "SELECT id, student_id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name, last_name";
$students_result = $conn->query($students_sql);

// Get academic years - FIXED QUERY
$years_sql = "SELECT id, year_name FROM academic_years WHERE is_current = 1 ORDER BY year_name DESC";
$years_result = $conn->query($years_sql);

// Get terms - FIXED QUERY  
$terms_sql = "SELECT id, term_name FROM terms ORDER BY id";
$terms_result = $conn->query($terms_sql);

// Get enhanced statistics
$stats_sql = "SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as total_unpaid,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue,
                SUM(CASE WHEN status = 'cancelled' THEN amount ELSE 0 END) as total_cancelled,
                AVG(amount) as avg_invoice_amount,
                COUNT(CASE WHEN DATEDIFF(due_date, CURDATE()) < 0 AND status = 'unpaid' THEN 1 END) as overdue_count
              FROM invoices 
              WHERE status != 'deleted'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Function to log actions
function logAction($user_id, $action, $details) {
    global $conn;
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $stmt->execute();
    $stmt->close();
}

// Add these to your existing filter parameters
$filter_academic_year = filter_var($_GET['academic_year'] ?? 0, FILTER_VALIDATE_INT);
$filter_term = filter_var($_GET['term'] ?? 0, FILTER_VALIDATE_INT);

// Update your WHERE conditions to include the new filters
if ($filter_academic_year > 0) {
    $where_conditions[] = "i.academic_year_id = ?";
    $params[] = $filter_academic_year;
    $param_types .= 'i';
}

if ($filter_term > 0) {
    $where_conditions[] = "i.term_id = ?";
    $params[] = $filter_term;
    $param_types .= 'i';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/invoice.css">
    <link rel="stylesheet" href="css/dark-mode.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-file-invoice-dollar"></i> Invoice Management</h1>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i> Create Invoice
                        </button>
                        <button class="btn btn-secondary" onclick="exportInvoices()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Notification Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Enhanced Statistics Cards -->
<!-- Enhanced Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3><?= number_format($stats['total_invoices'] ?? 0) ?></h3>
            <p>Total Invoices</p>
            <small>Avg: ₵<?= number_format($stats['avg_invoice_amount'] ?? 0, 2) ?></small>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3>₵<?= number_format($stats['total_paid'] ?? 0, 2) ?></h3>
            <p>Total Paid</p>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>₵<?= number_format($stats['total_unpaid'] ?? 0, 2) ?></h3>
            <p>Total Unpaid</p>
        </div>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3>₵<?= number_format($stats['total_overdue'] ?? 0, 2) ?></h3>
            <p>Total Overdue</p>
            <small><?= $stats['overdue_count'] ?? 0 ?> invoices</small>
        </div>
    </div>
</div>

            <!-- Enhanced Filters -->
<div class="filters-section">
    <form method="GET" class="filters-form" id="filterForm">
        <div class="filter-group">
            <label>Status:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Type:</label>
            <select name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="tuition" <?= $filter_type === 'tuition' ? 'selected' : '' ?>>Tuition</option>
                <option value="transport" <?= $filter_type === 'transport' ? 'selected' : '' ?>>Transport</option>
                <option value="meals" <?= $filter_type === 'meals' ? 'selected' : '' ?>>Meals</option>
                <option value="books" <?= $filter_type === 'books' ? 'selected' : '' ?>>Books</option>
                <option value="uniform" <?= $filter_type === 'uniform' ? 'selected' : '' ?>>Uniform</option>
                <option value="other" <?= $filter_type === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        
        <!-- ADD ACADEMIC YEAR FILTER -->
        <div class="filter-group">
            <label>Academic Year:</label>
            <select name="academic_year" onchange="this.form.submit()">
                <option value="">All Years</option>
                <?php 
                $years_result->data_seek(0);
                while ($year = $years_result->fetch_assoc()): ?>
                    <option value="<?= $year['id'] ?>" <?= ($_GET['academic_year'] ?? '') == $year['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year['year_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <!-- ADD TERM FILTER -->
        <div class="filter-group">
            <label>Term:</label>
            <select name="term" onchange="this.form.submit()">
                <option value="">All Terms</option>
                <?php 
                $terms_result->data_seek(0);
                while ($term = $terms_result->fetch_assoc()): ?>
                    <option value="<?= $term['id'] ?>" <?= ($_GET['term'] ?? '') == $term['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($term['term_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Search:</label>
            <input type="text" name="search" placeholder="Invoice number or student name..." value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <button type="submit" class="btn btn-secondary">
            <i class="fas fa-filter"></i> Filter
        </button>
        
        <a href="invoice.php" class="btn btn-light">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>
</div>

                <!-- Bulk Actions -->
                <div class="bulk-actions-section" id="bulkActions" style="display: none;">
                    <form method="POST" id="bulkActionForm">
                        <div class="bulk-actions-info">
                            <span id="selectedCount">0</span> invoices selected
                        </div>
                        <select name="bulk_action" class="bulk-action-select">
                            <option value="">Choose Action</option>
                            <option value="mark_paid">Mark as Paid</option>
                            <option value="mark_overdue">Mark as Overdue</option>
                            <option value="delete">Cancel Invoices</option>
                        </select>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-play"></i> Apply
                        </button>
                        <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </form>
                </div>

                <!-- Enhanced Invoices Table -->
                <div class="table-container">
                    <table class="data-table" id="invoicesTable">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Invoice #</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Days Left</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows > 0): ?>
                                <?php while ($invoice = $invoices->fetch_assoc()): 
                                    $is_overdue = $invoice['status'] === 'unpaid' && $invoice['days_until_due'] < 0;
                                    $due_class = $is_overdue ? 'overdue' : ($invoice['days_until_due'] < 7 ? 'warning' : '');
                                ?>
                                    <tr data-invoice-id="<?= $invoice['id'] ?>" class="<?= $due_class ?>">
                                        <td>
                                            <input type="checkbox" name="selected_invoices[]" value="<?= $invoice['id'] ?>" class="invoice-checkbox">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="student-info">
                                                <strong><?= htmlspecialchars($invoice['student_name']) ?></strong>
                                                <small><?= htmlspecialchars($invoice['student_number']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="invoice-type"><?= ucfirst($invoice['invoice_type']) ?></span>
                                        </td>
                                        <td>
                                            <strong>₵<?= number_format($invoice['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                            <?php if ($is_overdue): ?>
                                                <br><small class="text-danger">Overdue by <?= abs($invoice['days_until_due']) ?> days</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($invoice['status'] === 'unpaid'): ?>
                                                <span class="days-left <?= $due_class ?>">
                                                    <?= $invoice['days_until_due'] >= 0 ? $invoice['days_until_due'] : abs($invoice['days_until_due']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $invoice['status'] ?>">
                                                <?= ucfirst($invoice['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                   <td class="actions">
    <div class="action-buttons">
        <button class="btn-icon" onclick="viewInvoice(<?= $invoice['id'] ?>)" title="View" data-bs-toggle="tooltip">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn-icon" onclick="printInvoice(<?= $invoice['id'] ?>)" title="Print">
            <i class="fas fa-print"></i>
        </button>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Edit Button -->
           <button class="btn-icon primary" onclick="editInvoice(<?= $invoice['id'] ?>, '<?= $invoice['status'] ?>')" title="Edit Invoice">
    <i class="fas fa-edit"></i>
</button>
            <button class="btn-icon" onclick="updateStatus(<?= $invoice['id'] ?>, '<?= $invoice['status'] ?>')" title="Update Status">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn-icon danger" onclick="deleteInvoice(<?= $invoice['id'] ?>)" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        <?php endif; ?>
    </div>
</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-data">
                                        <i class="fas fa-file-invoice"></i>
                                        <p>No invoices found</p>
                                        <?php if (!empty($search) || !empty($filter_status) || !empty($filter_type)): ?>
                                            <a href="invoice.php" class="btn btn-primary btn-sm">Clear filters</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">First</a>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">Next</a>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-link">Last</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Invoice Modal -->
    <div id="createInvoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Create New Invoice</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" class="modal-form" id="createInvoiceForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student *</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">Select Student</option>
                            <?php 
                            $students_result->data_seek(0); // Reset pointer
                            while ($student = $students_result->fetch_assoc()): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                    (<?= htmlspecialchars($student['student_id']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year_id">Academic Year *</label>
                        <select name="academic_year_id" id="academic_year_id" required>
                            <option value="">Select Year</option>
                            <?php 
                            $years_result->data_seek(0); // Reset pointer
                            while ($year = $years_result->fetch_assoc()): ?>
                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_id">Term *</label>
                        <select name="term_id" id="term_id" required>
                            <option value="">Select Term</option>
                            <?php 
                            $terms_result->data_seek(0); // Reset pointer
                            while ($term = $terms_result->fetch_assoc()): ?>
                                <option value="<?= $term['id'] ?>"><?= htmlspecialchars($term['term_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoice_type">Invoice Type *</label>
                        <select name="invoice_type" id="invoice_type" required>
                            <option value="">Select Type</option>
                            <option value="tuition">Tuition</option>
                            <option value="transport">Transport</option>
                            <option value="meals">Meals</option>
                            <option value="books">Books</option>
                            <option value="uniform">Uniform</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount (₵) *</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date *</label>
                        <input type="date" name="due_date" id="due_date" required>
                        <small id="dueDateHelp" class="form-text"></small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" rows="3" placeholder="Additional notes or description..."></textarea>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="create_invoice" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content small">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Invoice Status</h2>
                <button class="close-btn" onclick="closeStatusModal()">&times;</button>
            </div>
            
            <form method="POST" class="modal-form">
                <input type="hidden" name="invoice_id" id="status_invoice_id">
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select name="status" id="status" required>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div id="paymentDateField" style="display: none;">
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Load jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Then load toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="js/invoice.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
</body>
</html>
