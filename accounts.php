<?php
// accounts.php
require_once 'config.php';
require_once 'session.php';
require_once 'functions/activity_logger.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access to admin, accountant, and finance roles only
$allowed_roles = ['admin', 'accountant', 'finance'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_action'])) {
        $form_action = $_POST['form_action'];

        // --- Create Student Account ---
     if ($form_action === 'create_account') {
    $student_id = intval($_POST['student_id']);
    $account_type = $_POST['account_type'];
    $initial_balance = floatval($_POST['initial_balance']);
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    $payment_types = isset($_POST['payment_types']) ? $_POST['payment_types'] : []; // Array of selected payment types

    // Check if account already exists for this student
    $check_sql = "SELECT id FROM student_accounts WHERE student_id = ? AND account_type = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('is', $student_id, $account_type);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "An account of this type already exists for this student.";
    } else {
        // Generate account number
        $account_number = 'ACC' . date('Ymd') . str_pad($student_id, 6, '0', STR_PAD_LEFT);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert account
            $sql = "INSERT INTO student_accounts 
                    (account_number, student_id, account_type, current_balance, status, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sissdsi', 
                $account_number, 
                $student_id, 
                $account_type, 
                $initial_balance, 
                $status, 
                $notes, 
                $user_id
            );

            if ($stmt->execute()) {
                $account_id = $stmt->insert_id;
                
                // If initial balance > 0, create initial transaction
                if ($initial_balance > 0) {
                    $transaction_sql = "INSERT INTO account_transactions 
                                        (account_id, transaction_type, amount, description, created_by) 
                                        VALUES (?, 'deposit', ?, 'Initial account balance', ?)";
                    $transaction_stmt = $conn->prepare($transaction_sql);
                    $transaction_stmt->bind_param('idi', $account_id, $initial_balance, $user_id);
                    $transaction_stmt->execute();
                    $transaction_stmt->close();
                }

                // Link payment types to this account
                if (!empty($payment_types)) {
                    $link_sql = "INSERT INTO payment_accounts (student_id, payment_type, account_id) VALUES (?, ?, ?)";
                    $link_stmt = $conn->prepare($link_sql);
                    
                    foreach ($payment_types as $payment_type) {
                        $link_stmt->bind_param('isi', $student_id, $payment_type, $account_id);
                        $link_stmt->execute();
                    }
                    $link_stmt->close();
                }

                $conn->commit();

                // Log activity
                logActivity(
                    $conn,
                    "Student Account Created",
                    "Account: $account_number, Student ID: $student_id, Balance: $initial_balance, Payment Types: " . implode(', ', $payment_types),
                    "create",
                    "fas fa-piggy-bank",
                    $account_id
                );
                
                $_SESSION['success'] = "Student account created successfully! Account Number: $account_number";
            } else {
                throw new Exception("Error creating account: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            error_log("Account creation error: " . $e->getMessage());
        }
    }
    $check_stmt->close();
}

        // --- Update Account ---
        elseif ($form_action === 'update_account') {
            $account_id = intval($_POST['account_id']);
            $account_type = $_POST['account_type'];
            $status = $_POST['status'];
            $notes = trim($_POST['notes']);

            $sql = "UPDATE student_accounts SET 
                    account_type = ?, 
                    status = ?, 
                    notes = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $account_type, $status, $notes, $account_id);

            if ($stmt->execute()) {
                // Log activity
                logActivity(
                    $conn,
                    "Student Account Updated",
                    "Account ID: $account_id",
                    "update",
                    "fas fa-edit",
                    $account_id
                );
                
                $_SESSION['success'] = "Account updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating account: " . $stmt->error;
            }
            $stmt->close();
        }

        // --- Process Transaction ---
        elseif ($form_action === 'process_transaction') {
            $account_id = intval($_POST['account_id']);
            $transaction_type = $_POST['transaction_type'];
            $amount = floatval($_POST['amount']);
            $description = trim($_POST['description']);
            $reference_number = $_POST['reference_number'] ?? '';

            // Get current balance
            $balance_sql = "SELECT current_balance FROM student_accounts WHERE id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->bind_param('i', $account_id);
            $balance_stmt->execute();
            $balance_result = $balance_stmt->get_result();
            
            if ($balance_result->num_rows === 0) {
                $_SESSION['error'] = "Account not found.";
                header("Location: accounts.php");
                exit();
            }

            $current_balance = $balance_result->fetch_assoc()['current_balance'];
            $balance_stmt->close();

            // Validate withdrawal
            if ($transaction_type === 'withdrawal' && $amount > $current_balance) {
                $_SESSION['error'] = "Insufficient balance for withdrawal.";
                header("Location: accounts.php");
                exit();
            }

            // Calculate new balance
            $new_balance = ($transaction_type === 'deposit') 
                ? $current_balance + $amount 
                : $current_balance - $amount;

            // Start transaction
            $conn->begin_transaction();

            try {
                // Insert transaction
                $transaction_sql = "INSERT INTO account_transactions 
                                    (account_id, transaction_type, amount, description, reference_number, created_by) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                $transaction_stmt = $conn->prepare($transaction_sql);
                $transaction_stmt->bind_param('isdssi', 
                    $account_id, 
                    $transaction_type, 
                    $amount, 
                    $description, 
                    $reference_number, 
                    $user_id
                );
                $transaction_stmt->execute();
                $transaction_id = $transaction_stmt->insert_id;
                $transaction_stmt->close();

                // Update account balance
                $update_sql = "UPDATE student_accounts SET 
                              current_balance = ?, 
                              last_transaction_date = NOW(),
                              updated_at = NOW()
                              WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('di', $new_balance, $account_id);
                $update_stmt->execute();
                $update_stmt->close();

                $conn->commit();

                // Log activity
                logActivity(
                    $conn,
                    "Account Transaction Processed",
                    "Type: $transaction_type, Amount: $amount, New Balance: $new_balance",
                    "update",
                    "fas fa-exchange-alt",
                    $account_id
                );
                
                $_SESSION['success'] = ucfirst($transaction_type) . " processed successfully! New Balance: GHC " . number_format($new_balance, 2);
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Transaction failed: " . $e->getMessage();
            }
        }

        // --- Close Account ---
        elseif ($form_action === 'close_account') {
            $account_id = intval($_POST['account_id']);
            $closing_notes = trim($_POST['closing_notes']);

            $sql = "UPDATE student_accounts SET 
                    status = 'closed', 
                    notes = CONCAT(IFNULL(notes, ''), ' | Closed: ', ?),
                    closed_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ? AND current_balance = 0";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $closing_notes, $account_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Log activity
                logActivity(
                    $conn,
                    "Student Account Closed",
                    "Account ID: $account_id",
                    "update",
                    "fas fa-lock",
                    $account_id
                );
                
                $_SESSION['success'] = "Account closed successfully!";
            } else {
                $_SESSION['error'] = "Cannot close account. Account must have zero balance.";
            }
            $stmt->close();
        }

        header("Location: accounts.php");
        exit();
    }
}

// --- Delete Account (only if balance is zero and no transactions) ---
if (isset($_POST['delete_account'])) {
    $account_id = intval($_POST['account_id']);

    // Check if account can be deleted
    $check_sql = "SELECT sa.current_balance, COUNT(at.id) as transaction_count 
                  FROM student_accounts sa 
                  LEFT JOIN account_transactions at ON sa.id = at.account_id 
                  WHERE sa.id = ? 
                  GROUP BY sa.id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $account_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $account_data = $check_result->fetch_assoc();
        if ($account_data['current_balance'] == 0 && $account_data['transaction_count'] == 0) {
            $delete_sql = "DELETE FROM student_accounts WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param('i', $account_id);
            
            if ($delete_stmt->execute()) {
                // Log activity
                logActivity(
                    $conn,
                    "Student Account Deleted",
                    "Account ID: $account_id",
                    "delete",
                    "fas fa-trash",
                    $account_id
                );
                
                $_SESSION['success'] = "Account deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting account: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error'] = "Cannot delete account. Account must have zero balance and no transactions.";
        }
    }
    $check_stmt->close();

    header("Location: accounts.php");
    exit();
}

// Get all student accounts with student information
$accounts = [];
$sql = "SELECT sa.*, 
               s.first_name, s.last_name, s.student_id as student_code,
               c.class_name,
               u.full_name as created_by_name,
               (SELECT COUNT(*) FROM account_transactions WHERE account_id = sa.id) as transaction_count,
               (SELECT MAX(created_at) FROM account_transactions WHERE account_id = sa.id) as last_activity
        FROM student_accounts sa
        JOIN students s ON sa.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON sa.created_by = u.id
        ORDER BY sa.created_at DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

// Get students without accounts for creating new accounts
$students_without_accounts = [];
$sql = "SELECT s.id, s.first_name, s.last_name, s.student_id, c.class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_accounts sa ON s.id = sa.student_id
        WHERE sa.id IS NULL AND s.status = 'active'
        ORDER BY s.first_name, s.last_name";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $students_without_accounts[] = $row;
}

// Get account types
$account_types = ['Savings', 'Current', 'Tuition', 'General'];

// Get total statistics
$stats_sql = "SELECT 
              COUNT(*) as total_accounts,
              SUM(current_balance) as total_balance,
              COUNT(CASE WHEN status = 'active' THEN 1 END) as active_accounts,
              COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_accounts
              FROM student_accounts";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Student Accounts Management</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/payments.css">
    <style>
        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .balance-positive { color: #059669; }
        .balance-zero { color: #6b7280; }
        .balance-negative { color: #dc2626; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fef3c7; color: #92400e; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        
        .account-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-icon.small {
            padding: 6px 8px;
            font-size: 0.8rem;
        }
        
        .transaction-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .type-deposit { background: #d1fae5; color: #065f46; }
        .type-withdrawal { background: #fee2e2; color: #991b1b; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
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
                <!-- Flash Messages -->
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="page-header">
                    <h1>
                        <i class="fas fa-piggy-bank"></i> Student Accounts Management
                    </h1>
                    <div class="header-actions">
                        <button id="createAccountBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Account
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="account-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_accounts'] ?></div>
                        <div class="stat-label">Total Accounts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number balance-positive">
                            GHC <?= number_format($stats['total_balance'] ?? 0, 2) ?>
                        </div>
                        <div class="stat-label">Total Balance</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['active_accounts'] ?></div>
                        <div class="stat-label">Active Accounts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['closed_accounts'] ?></div>
                        <div class="stat-label">Closed Accounts</div>
                    </div>
                </div>

                <!-- Accounts Table -->
                <div class="table-container">
                    <div class="table-actions">
                        <div class="table-actions-left">
                            <span class="pagination-info">
                                Showing <?= count($accounts) ?> accounts
                            </span>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Account No.</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Transactions</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($accounts)): ?>
                                    <?php foreach ($accounts as $account): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($account['account_number']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?>
                                                <br><small><?= htmlspecialchars($account['student_code']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($account['class_name']) ?></td>
                                            <td><?= htmlspecialchars($account['account_type']) ?></td>
                                            <td>
                                                <span class="<?= $account['current_balance'] > 0 ? 'balance-positive' : ($account['current_balance'] < 0 ? 'balance-negative' : 'balance-zero') ?>">
                                                    GHC <?= number_format($account['current_balance'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $account['status'] ?>">
                                                    <?= ucfirst($account['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $account['transaction_count'] ?></td>
                                            <td>
                                                <?= $account['last_activity'] ? date('M j, Y', strtotime($account['last_activity'])) : 'Never' ?>
                                            </td>
                                            <td>
                                                <div class="account-actions">
                                                    <button class="btn-icon small primary" 
                                                            onclick="viewAccount(<?= $account['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-icon small success" 
                                                            onclick="processTransaction(<?= $account['id'] ?>)"
                                                            title="Process Transaction">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </button>
                                                    <button class="btn-icon small" 
                                                            onclick="editAccount(<?= $account['id'] ?>)"
                                                            title="Edit Account">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($account['current_balance'] == 0 && $account['status'] == 'active'): ?>
                                                        <button class="btn-icon small warning" 
                                                                onclick="closeAccount(<?= $account['id'] ?>)"
                                                                title="Close Account">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($account['current_balance'] == 0 && $account['transaction_count'] == 0): ?>
                                                        <button class="btn-icon small danger" 
                                                                onclick="deleteAccount(<?= $account['id'] ?>)"
                                                                title="Delete Account">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            No student accounts found. <a href="javascript:void(0)" onclick="showCreateModal()">Create the first account</a>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

<!-- Replace the existing account modal in accounts.php with this enhanced version -->
<div id="accountModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Create Student Account</h3>
            <span class="close" onclick="closeModal('accountModal')">&times;</span>
        </div>
        <form method="POST" id="accountForm">
            <div class="modal-body">
                <input type="hidden" name="account_id" id="account_id">
                <input type="hidden" name="form_action" id="form_action" value="create_account">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student *</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students_without_accounts as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                    (<?= htmlspecialchars($student['student_id']) ?> - <?= htmlspecialchars($student['class_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_type">Account Type *</label>
                        <select id="account_type" name="account_type" required>
                            <option value="">Select Type</option>
                            <option value="Tuition">Tuition</option>
                            <option value="Extra Class">Extra Class</option>
                            <option value="PTA">PTA</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width" id="payment_types_container">
                        <label>Payment Types for this Account *</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 10px;">
                            <?php
                            // Get all available payment types from billing
                            $payment_types_sql = "SELECT DISTINCT payment_type FROM billing ORDER BY payment_type";
                            $payment_types_result = $conn->query($payment_types_sql);
                            while ($pt = $payment_types_result->fetch_assoc()):
                            ?>
                                <label style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="payment_types[]" value="<?= htmlspecialchars($pt['payment_type']) ?>">
                                    <?= htmlspecialchars($pt['payment_type']) ?>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <small class="text-muted">Select which payment types should use this account</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="initial_balance">Initial Balance</label>
                        <input type="number" step="0.01" id="initial_balance" name="initial_balance" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about this account..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('accountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmit">Create Account</button>
            </div>
        </form>
    </div>
</div>

    <!-- Transaction Modal -->
    <div id="transactionModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Process Transaction</h3>
                <span class="close" onclick="closeModal('transactionModal')">&times;</span>
            </div>
            <form method="POST" id="transactionForm">
                <div class="modal-body">
                    <input type="hidden" name="account_id" id="transaction_account_id">
                    <input type="hidden" name="form_action" value="process_transaction">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="transaction_type">Transaction Type *</label>
                            <select id="transaction_type" name="transaction_type" required>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount *</label>
                            <input type="number" step="0.01" id="amount" name="amount" required min="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="reference_number">Reference Number</label>
                            <input type="text" id="reference_number" name="reference_number" placeholder="Optional reference">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="3" required placeholder="Transaction description..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('transactionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Close Account Modal -->
    <div id="closeAccountModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Close Account</h3>
                <span class="close" onclick="closeModal('closeAccountModal')">&times;</span>
            </div>
            <form method="POST" id="closeAccountForm">
                <div class="modal-body">
                    <input type="hidden" name="account_id" id="close_account_id">
                    <input type="hidden" name="form_action" value="close_account">
                    
                    <div class="form-group full-width">
                        <label for="closing_notes">Closing Notes</label>
                        <textarea id="closing_notes" name="closing_notes" rows="3" required placeholder="Reason for closing this account..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('closeAccountModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Close Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteAccountModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Account</h3>
                <span class="close" onclick="closeModal('deleteAccountModal')">&times;</span>
            </div>
            <form method="POST" id="deleteAccountForm">
                <div class="modal-body">
                    <input type="hidden" name="account_id" id="delete_account_id">
                    <input type="hidden" name="delete_account" value="1">
                    
                    <p>Are you sure you want to delete this account? This action cannot be undone.</p>
                    <p><strong>Note:</strong> Account must have zero balance and no transactions to be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteAccountModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    <script>
        // Modal functions
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Student Account';
            document.getElementById('form_action').value = 'create_account';
            document.getElementById('modalSubmit').textContent = 'Create Account';
            document.getElementById('accountForm').reset();
            document.getElementById('account_id').value = '';
            document.getElementById('student_id').required = true;
            document.getElementById('student_id').disabled = false;
            openModal('accountModal');
        }

        function editAccount(accountId) {
            // Fetch account data and populate form
            fetch(`get_account.php?id=${accountId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').textContent = 'Edit Account';
                    document.getElementById('form_action').value = 'update_account';
                    document.getElementById('modalSubmit').textContent = 'Update Account';
                    document.getElementById('account_id').value = data.id;
                    document.getElementById('account_type').value = data.account_type;
                    document.getElementById('status').value = data.status;
                    document.getElementById('notes').value = data.notes || '';
                    document.getElementById('student_id').required = false;
                    document.getElementById('student_id').disabled = true;
                    document.getElementById('initial_balance').style.display = 'none';
                    openModal('accountModal');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading account details.');
                });
        }

        function processTransaction(accountId) {
            document.getElementById('transaction_account_id').value = accountId;
            document.getElementById('transactionForm').reset();
            openModal('transactionModal');
        }

        function closeAccount(accountId) {
            document.getElementById('close_account_id').value = accountId;
            document.getElementById('closeAccountForm').reset();
            openModal('closeAccountModal');
        }

        function deleteAccount(accountId) {
            document.getElementById('delete_account_id').value = accountId;
            openModal('deleteAccountModal');
        }

        function viewAccount(accountId) {
            window.location.href = `account_details.php?id=${accountId}`;
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Initialize create button
        document.addEventListener('DOMContentLoaded', function() {
            const createBtn = document.getElementById('createAccountBtn');
            if (createBtn) {
                createBtn.addEventListener('click', showCreateModal);
            }
        });
    </script>
</body>
</html>