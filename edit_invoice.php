<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$invoice_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$invoice_id) {
    header('Location: invoice.php');
    exit();
}

// Fetch invoice details
$sql = "SELECT i.*, 
               s.student_id as student_number,
               CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        WHERE i.id = ? AND i.status != 'deleted'";

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $due_date = $_POST['due_date'];
    $description = htmlspecialchars(trim($_POST['description']));
    $invoice_type = htmlspecialchars(trim($_POST['invoice_type']));
    
    // Validate inputs
    $errors = [];
    
    if (!$amount || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (!$due_date || strtotime($due_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Valid due date is required";
    }
    
    if (empty($errors)) {
        $sql = "UPDATE invoices 
                SET amount = ?, due_date = ?, description = ?, invoice_type = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsssi", $amount, $due_date, $description, $invoice_type, $invoice_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Invoice updated successfully!";
            logAction($_SESSION['user_id'], 'UPDATE_INVOICE', 'Updated invoice: ' . $invoice['invoice_number']);
            header('Location: view_invoice.php?id=' . $invoice_id);
            exit();
        } else {
            $errors[] = "Error updating invoice: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get students for dropdown
$students_sql = "SELECT id, student_id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name, last_name";
$students_result = $conn->query($students_sql);

// Get academic years
$years_sql = "SELECT id, year_name FROM academic_years WHERE id = 'is_current' ORDER BY year_name DESC";
$years_result = $conn->query($years_sql);

// Get terms
$terms_sql = "SELECT id, term_name FROM terms ORDER BY term_name";
$terms_result = $conn->query($terms_sql);

function logAction($user_id, $action, $details) {
    global $conn;
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - School Management System</title>
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
                    <h1><i class="fas fa-edit"></i> Edit Invoice</h1>
                    <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Invoice
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= implode('<br>', $errors) ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" class="modal-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Invoice Number</label>
                                <input type="text" value="<?= htmlspecialchars($invoice['invoice_number']) ?>" disabled class="form-control">
                                <small class="form-text">Invoice number cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Student</label>
                                <input type="text" value="<?= htmlspecialchars($invoice['student_name'] . ' (' . $invoice['student_number'] . ')') ?>" disabled class="form-control">
                                <small class="form-text">Student cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="invoice_type">Invoice Type *</label>
                                <select name="invoice_type" id="invoice_type" required>
                                    <option value="tuition" <?= $invoice['invoice_type'] === 'tuition' ? 'selected' : '' ?>>Tuition</option>
                                    <option value="transport" <?= $invoice['invoice_type'] === 'transport' ? 'selected' : '' ?>>Transport</option>
                                    <option value="meals" <?= $invoice['invoice_type'] === 'meals' ? 'selected' : '' ?>>Meals</option>
                                    <option value="books" <?= $invoice['invoice_type'] === 'books' ? 'selected' : '' ?>>Books</option>
                                    <option value="uniform" <?= $invoice['invoice_type'] === 'uniform' ? 'selected' : '' ?>>Uniform</option>
                                    <option value="other" <?= $invoice['invoice_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="amount">Amount (₵) *</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($invoice['amount']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="due_date">Due Date *</label>
                                <input type="date" name="due_date" id="due_date" value="<?= htmlspecialchars($invoice['due_date']) ?>" required>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" rows="4" placeholder="Additional notes or description..."><?= htmlspecialchars($invoice['description']) ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Invoice
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="js/dashboard.js"></script>
     <script src="js/darkmode.js"></script>
     
    <script>
        // Set minimum due date to today
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const dueDate = new Date(document.getElementById('due_date').value);
            const today = new Date();
            
            if (amount <= 0) {
                e.preventDefault();
                alert('Amount must be greater than zero');
                return;
            }
            
            if (dueDate < today.setHours(0,0,0,0)) {
                e.preventDefault();
                alert('Due date cannot be in the past');
                return;
            }
        });
    </script>
</body>
</html>

<style>
.form-container {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 800px;
    margin: 0 auto;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.form-group input:disabled {
    background: #f8f9fa;
    color: #666;
}

.form-text {
    color: #666;
    font-size: 0.8rem;
    margin-top: 5px;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #eee;
    padding-top: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-container {
        padding: 20px;
    }
}
</style>