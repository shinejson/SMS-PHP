<?php
require_once 'config.php';
require_once 'session.php';
require_once 'functions/activity_logger.php';
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_action'])) {
        $form_action = $_POST['form_action'];

      
if ($form_action === 'add_payment') { 
    $student_id = intval($_POST['student_id']);
    $payment_date = $_POST['payment_date'];
    $term_id = intval($_POST['term_id']);
    $payment_type = $_POST['payment_type'];
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $description = $_POST['description'];
    $collected_by_user_id = $_SESSION['user_id'] ?? 1;
    $academic_year_id = intval($_POST['academic_year_id']);
    
    // Generate receipt number
    $receipt_no = '';
    do {
        $receipt_no = 'PY' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $check_sql = "SELECT id FROM payments WHERE receipt_no = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $receipt_no);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
    } while ($check_result->num_rows > 0);
    
    // Validation
    if (empty($academic_year_id)) {
        $_SESSION['error'] = "Academic year is required";
        header("Location: payments.php");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert payment record
        $sql = "INSERT INTO payments
                (receipt_no, student_id, payment_date, term_id, payment_type, amount, 
                 payment_method, description, collected_by_user_id, academic_year_id, status)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisssdssii',
            $receipt_no,
            $student_id,
            $payment_date,
            $term_id,
            $payment_type,
            $amount,
            $payment_method,
            $description,
            $collected_by_user_id,
            $academic_year_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Payment insert failed: " . $stmt->error);
        }
        
        $new_payment_id = $stmt->insert_id;
        $stmt->close();
        
        // Check if there's a linked account for this payment type
        $account_sql = "SELECT sa.id, sa.current_balance, sa.account_number
                        FROM payment_accounts pa
                        JOIN student_accounts sa ON pa.account_id = sa.id
                        WHERE pa.student_id = ? 
                        AND pa.payment_type = ? 
                        AND pa.is_active = 1
                        AND sa.status = 'active'
                        LIMIT 1";
        
        $account_stmt = $conn->prepare($account_sql);
        $account_stmt->bind_param('is', $student_id, $payment_type);
        $account_stmt->execute();
        $account_result = $account_stmt->get_result();
        
        if ($account_result->num_rows > 0) {
            $account = $account_result->fetch_assoc();
            $account_id = $account['id'];
            $current_balance = floatval($account['current_balance']);
            $account_number = $account['account_number'];
            
            // Calculate new balance (payment reduces the balance)
            $new_balance = $current_balance - $amount;
            
            // Update account balance
            $update_account_sql = "UPDATE student_accounts 
                                   SET current_balance = ?, 
                                       last_transaction_date = NOW(),
                                       updated_at = NOW()
                                   WHERE id = ?";
            $update_stmt = $conn->prepare($update_account_sql);
            $update_stmt->bind_param('di', $new_balance, $account_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Account update failed: " . $update_stmt->error);
            }
            $update_stmt->close();
            
            // Record account transaction
            $transaction_sql = "INSERT INTO account_transactions 
                                (account_id, transaction_type, amount, description, reference_number, created_by) 
                                VALUES (?, 'withdrawal', ?, ?, ?, ?)";
            $trans_stmt = $conn->prepare($transaction_sql);
            $trans_description = "Payment for $payment_type - Receipt: $receipt_no";
            $trans_stmt->bind_param('idssi', 
                $account_id, 
                $amount, 
                $trans_description, 
                $receipt_no, 
                $collected_by_user_id
            );
            
            if (!$trans_stmt->execute()) {
                throw new Exception("Transaction record failed: " . $trans_stmt->error);
            }
            $trans_stmt->close();
            
            $success_message = "Payment recorded successfully! Receipt No: $receipt_no. Account $account_number updated (New Balance: GHC " . number_format($new_balance, 2) . ")";
        } else {
            $success_message = "Payment recorded successfully! Receipt No: $receipt_no (No linked account)";
        }
        $account_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity(
            $conn,
            "Payment Added",
            "Receipt No: $receipt_no, Student ID: $student_id, Amount: $amount, Payment Type: $payment_type",
            "create",
            "fas fa-money-check-alt",
            $new_payment_id
        );
        
        $_SESSION['message'] = $success_message;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error recording payment: " . $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        error_log("Payment processing error: " . $e->getMessage());
    }
} elseif ($form_action === 'bulk_payment' && isset($_FILES['bulk_file']) && $_FILES['bulk_file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['bulk_file']['tmp_name'];
    $academic_year_id = (int)($_POST['bulk_academic_year_id'] ?? 0);
    $term_id          = (int)($_POST['bulk_term_id'] ?? 0);
    $payment_type     = trim($_POST['bulk_payment_type'] ?? '');
    $payment_method   = trim($_POST['bulk_payment_method'] ?? '');

    if ($academic_year_id <= 0 || $term_id <= 0 || empty($payment_type) || empty($payment_method)) {
        $_SESSION['error'] = "All bulk fields are required.";
        header("Location: payments.php");
        exit();
    }

    if (!in_array(pathinfo($_FILES['bulk_file']['name'], PATHINFO_EXTENSION), ['csv'])) {
        $_SESSION['error'] = "Only CSV files are allowed.";
        header("Location: payments.php");
        exit();
    }

    $handle = fopen($uploadedFile, 'r');
    if (!$handle) {
        $_SESSION['error'] = "Could not read uploaded file.";
        header("Location: payments.php");
        exit();
    }

    $header = fgetcsv($handle); // Skip header
    if ($header === false) {
        fclose($handle);
        $_SESSION['error'] = "Empty CSV file.";
        header("Location: payments.php");
        exit();
    }

    $conn->autocommit(false);
    $conn->begin_transaction();

    $inserted = 0;
    $failed   = 0;
    $failedRows = [];

    try {
        $insertSql = "INSERT INTO payments 
            (receipt_no, student_id, payment_date, term_id, payment_type, amount, 
             payment_method, description, collected_by_user_id, academic_year_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', NOW())";

        $stmt = $conn->prepare($insertSql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        while (($row = fgetcsv($handle)) !== false) {
            // Expected CSV columns: student_id, payment_date (Y-m-d), amount, description
            if (count($row) < 4) {
                $failed++;
                $failedRows[] = implode(',', $row);
                continue;
            }

            $student_id   = (int)trim($row[0]);
            $payment_date = trim($row[1]);
            $amount       = floatval(trim($row[2]));
            $description  = trim($row[3] ?? '');

            // Validate student exists
            $checkStudent = $conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'active'");
            $checkStudent->bind_param("i", $student_id);
            $checkStudent->execute();
            $res = $checkStudent->get_result();
            if ($res->num_rows === 0) {
                $failed++;
                $failedRows[] = "Invalid student ID: $student_id";
                $checkStudent->close();
                continue;
            }
            $checkStudent->close();

            // Validate date
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
                $failed++;
                $failedRows[] = "Invalid date: $payment_date";
                continue;
            }

            // Generate unique receipt_no
            $receipt_no = '';
            do {
                $receipt_no = 'PY' . date('Ymd', strtotime($payment_date)) . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $check = $conn->prepare("SELECT id FROM payments WHERE receipt_no = ?");
                $check->bind_param('s', $receipt_no);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();
            } while ($exists);

            $collected_by = $_SESSION['user_id'];

            $stmt->bind_param(
                'sisssdssii',
                $receipt_no,
                $student_id,
                $payment_date,
                $term_id,
                $payment_type,
                $amount,
                $payment_method,
                $description,
                $collected_by,
                $academic_year_id
            );

            if ($stmt->execute()) {
                $inserted++;
                $new_payment_id = $stmt->insert_id;

                // Optional: log each payment
                 logActivity($conn, "Bulk Payment", "Receipt: $receipt_no, Amount: $amount", "create", "fas fa-coins", $new_payment_id);
            } else {
                $failed++;
                $failedRows[] = "DB Error for student $student_id: " . $stmt->error;
            }
        }

        $stmt->close();
        fclose($handle);

        if ($inserted > 0) {
            $conn->commit();

            // MAIN BULK LOG (Admin summary)
            logActivity(
                $conn,
                "Bulk Payment Uploaded",
                "Inserted: $inserted, Failed: $failed, Academic Year: $academic_year_id, Term: $term_id",
                "create",
                "fas fa-file-import",
                null  // No single related_id – summary
            );

            $_SESSION['message'] = "Bulk upload complete! $inserted payments added.";
            if ($failed > 0) {
                $_SESSION['message'] .= " $failed failed.";
                $_SESSION['error_details'] = $failedRows;
            }
        } else {
            throw new Exception("No valid payments were processed.");
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Bulk upload failed: " . $e->getMessage();
        error_log("Bulk payment error: " . $e->getMessage());
    }

    $conn->autocommit(true);
    header("Location: payments.php");
    exit();
}
 // --- Update Payment with Prepared Statement ---
        elseif ($form_action === 'update_payment') {
            $id = intval($_POST['id']);
            $student_id = intval($_POST['student_id']);
            $payment_date = $_POST['payment_date'];
            $term_id = intval($_POST['term_id']);
            $payment_type = $_POST['payment_type'];
            $amount = floatval($_POST['amount']);
            $payment_method = $_POST['payment_method'];
            $description = $_POST['description'];
            $academic_year_id = intval($_POST['academic_year_id']);

            // FIXED: Added academic_year_id to UPDATE statement
            $sql = "UPDATE payments SET
                    student_id = ?,
                    payment_date = ?,
                    term_id = ?,
                    payment_type = ?,
                    amount = ?,
                    payment_method = ?,
                    description = ?,
                    academic_year_id = ?  
                WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            // FIXED: Added academic_year_id parameter and extra 'i' in type string
            $stmt->bind_param('isissssii',
                $student_id,
                $payment_date,
                $term_id,
                $payment_type,
                $amount,
                $payment_method,
                $description,
                $academic_year_id, 
                $id
            );

          if ($stmt->execute()) {
                // Log activity on success
                logActivity(
                    $conn,
                    "Payment Updated",
                    "Payment ID: $id, Student ID: $student_id, Amount: $amount",
                    "update",
                    "fas fa-edit",
                    $id  // related_id = payment ID
                );
                
                $_SESSION['message'] = "Payment updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating payment: " . $stmt->error;
            }
        }

        header("Location: payments.php");
        exit();
    }
}
    // --- Delete Payment with Prepared Statement ---
    elseif (isset($_POST['delete_payment'])) {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM payments WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);

      if ($stmt->execute()) {
                // Log activity on success
                logActivity(
                    $conn,
                    "Payment Updated",
                    "Payment ID: $id, Student ID: $student_id, Amount: $amount",
                    "update",
                    "fas fa-edit",
                    $id  // related_id = payment ID
                );
                
                $_SESSION['message'] = "Payment updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating payment: " . $stmt->error;
            }

        header("Location: payments.php");
        exit();
    }

// Get all payments (JOIN terms for term_name)
$payments = [];
// Update the SQL query to properly join academic_years
$sql = "
    SELECT p.*,
           s.first_name, s.last_name, s.student_id AS student_code, 
           s.class_id, 
           u.full_name AS collector_name,
           c.class_name AS class_name,
           t.id AS term_id,
           t.term_name,
           ay.id AS academic_year_id,
           ay.year_name AS academic_year,
           -- Subquery: total paid for that student/payment_type/term
           (SELECT SUM(p2.amount) 
            FROM payments p2 
            WHERE p2.student_id = p.student_id 
              AND p2.payment_type = p.payment_type 
              AND p2.term_id = p.term_id
              AND p2.academic_year_id = p.academic_year_id) AS total_paid,
           -- Subquery: billing amount
           (SELECT b.amount 
            FROM billing b 
            WHERE b.class_id = s.class_id 
              AND b.payment_type = p.payment_type 
              AND b.term_id = p.term_id 
              AND b.academic_year_id = p.academic_year_id
            LIMIT 1) AS billing_amount
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN users u ON p.collected_by_user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN terms t ON p.term_id = t.id
    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id  
    ORDER BY p.payment_date DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    // Compute status in PHP
    $billing = floatval($row['billing_amount'] ?? 0);
    $paid = floatval($row['total_paid'] ?? 0);
    $status = "Unknown"; // Initialize status variable

    if ($billing > 0) {
        if ($paid == 0) {
            $status = "Not Paid";
        } elseif ($paid < $billing) {
            $status = "Part Payment";
        } elseif ($paid == $billing) {
            $status = "Full Payment";
        } else {
            $status = "Overpayment";
        }
    }

    // Add status to the row data
    $row['status'] = $status;
    $row['status_class'] = strtolower(str_replace(' ', '-', $status));
    
    $payments[] = $row;
}

// Get students
$students = [];
$sql = "SELECT id, first_name, last_name, student_id, class_id FROM students ORDER BY first_name, last_name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get terms
$terms = [];
$sql = "SELECT id, term_name FROM terms ORDER BY term_order";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $terms[] = $row;
}

// Get academic years
$academic_years = [];
$sql = "SELECT id, year_name FROM academic_years ORDER BY year_name DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $academic_years[] = $row;
}

// Get current academic year
$current_academic_year_id = null;
$sql = "SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $current_academic_year_id = $result->fetch_assoc()['id'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Payment Management</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    @media (prefers-color-scheme: dark) {
    .btn-primary {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }
    .btn-success {
        background: linear-gradient(135deg, #059669, #10b981);

    }
}

/* -------------------------------------------------
   Action Buttons – Primary (Add) & Success (Bulk)
   ------------------------------------------------- */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    min-height: 44px;
}

.btn i {
    font-size: 1.1rem;
}

/* Primary Button – Add Payment */
.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #6d28d9);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}
.btn-primary:active {
    transform: translateY(0);
}

/* Success Button – Bulk Upload */
.btn-success {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}
.btn-success:hover {
    background: linear-gradient(135deg, #059669, #10b981);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.btn-success:active {
    transform: translateY(0);
}

/* Optional: Disabled state */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Responsive: Stack on small screens */
@media (max-width: 640px) {
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    .btn {
        width: 100%;
        justify-content: center;
    }
}

</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="main-content">
    <?php include 'topnav.php'; ?>
    <div class="content-wrapper">
        <div class="page-header">
            <h1>Payment Management</h1>
        </div>

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>


        <?php if (!empty($_SESSION['error_details'])): ?>
    <div class="alert alert-warning">
        <strong>Failed Rows:</strong>
        <ul>
            <?php foreach ($_SESSION['error_details'] as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php unset($_SESSION['error_details']); ?>
<?php endif; ?>

        <!-- Filter Controls -->
     <!-- Filter Controls -->
<div class="filter-controls">
    <div class="filter-group">
        <label for="classFilter">Filter by Class:</label>
        <select id="classFilter" class="filter-select">
            <option value="">All Classes</option>
            <?php
            $class_sql = "SELECT DISTINCT c.id, c.class_name 
                         FROM classes c 
                         JOIN students s ON c.id = s.class_id 
                         JOIN payments p ON s.id = p.student_id 
                         ORDER BY c.class_name";
            $class_result = $conn->query($class_sql);
            while ($class = $class_result->fetch_assoc()) {
                echo "<option value='{$class['class_name']}'>{$class['class_name']}</option>";
            }
            ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="studentFilter">Filter by Student:</label>
        <select id="studentFilter" class="filter-select">
            <option value="">All Students</option>
            <?php
            $student_sql = "SELECT DISTINCT s.id, s.first_name, s.last_name 
                           FROM students s 
                           JOIN payments p ON s.id = p.student_id 
                           ORDER BY s.first_name, s.last_name";
            $student_result = $conn->query($student_sql);
            while ($student = $student_result->fetch_assoc()) {
                $full_name = $student['first_name'] . ' ' . $student['last_name'];
                echo "<option value='{$full_name}'>{$full_name}</option>";
            }
            ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="termFilter">Filter by Term:</label>
        <select id="termFilter" class="filter-select">
            <option value="">All Terms</option>
            <?php
            $term_sql = "SELECT DISTINCT t.id, t.term_name 
                        FROM terms t 
                        JOIN payments p ON t.id = p.term_id 
                        ORDER BY t.term_order";
            $term_result = $conn->query($term_sql);
            while ($term = $term_result->fetch_assoc()) {
                echo "<option value='{$term['term_name']}'>{$term['term_name']}</option>";
            }
            ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="yearFilter">Academic Year:</label>
        <select id="yearFilter" class="filter-select">
            <option value="">All Academic Years</option>
            <?php
            $year_sql = "SELECT DISTINCT ay.id, ay.year_name 
                        FROM academic_years ay 
                        JOIN payments p ON ay.id = p.academic_year_id 
                        ORDER BY ay.year_name DESC";
            $year_result = $conn->query($year_sql);
            while ($year = $year_result->fetch_assoc()) {
                echo "<option value='{$year['year_name']}'>{$year['year_name']}</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label for="statusFilter">Payment Status:</label>
        <select id="statusFilter" class="filter-select">
            <option value="">All Statuses</option>
            <option value="Not Paid">Not Paid</option>
            <option value="Part Payment">Part Payment</option>
            <option value="Full Payment">Full Payment</option>
            <option value="Overpayment">Overpayment</option>
        </select>
    </div>
    
    <div class="filter-group">
        <button id="clearFilters" class="btn-clear-filters">Clear Filters</button>
    </div>
</div>       

        <div class="card">
            <div class="card-header">
                <h3>Payment Records</h3>
                <button class="btn-primary" id="addPaymentBtn"><i class="fas fa-plus"></i> Record Payment</button>
              <button class="btn btn-success" onclick="openModal('bulkPaymentModal')">
    <i class="fas fa-file-import"></i> Bulk Upload
</button>
            </div>
            <div class="card-body">
       <table id="paymentsTable" class="display nowrap" style="width:100%">
<thead>
    <tr>
        <th>Receipt No.</th>
        <th>Student</th>
        <th>Class</th> <!-- Added class column -->
        <th>Date</th>
        <th>Term</th>
        <th>Academic Year</th>
        <th>Amount</th>
        <th>Type</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
      <tbody>
    <?php 
    foreach ($payments as &$p) {
        $billing = floatval($p['billing_amount'] ?? 0);
        $paid = floatval($p['total_paid'] ?? 0);
        
        if ($billing > 0) {
            if ($paid == 0) {
                $status = "Not Paid";
                $status_class = "not-paid";
            } elseif ($paid < $billing) {
                $status = "Part Payment";
                $status_class = "part-payment";
            } elseif ($paid == $billing) {
                $status = "Full Payment";
                $status_class = "full-payment";
            } else {
                $status = "Overpayment";
                $status_class = "overpayment";
            }
        } else {
            $status = "Unknown";
            $status_class = "unknown";
        }
        
        // Add status to the row data
        $p['status'] = $status;
        $p['status_class'] = $status_class;
    ?>
    <tr>
        <td><?= $p['receipt_no']; ?></td>
        <td><?= $p['first_name'] . ' ' . $p['last_name']; ?></td>
        <td><?= $p['class_name']; ?></td> <!-- Added class name column -->
        <td><?= date('M d, Y', strtotime($p['payment_date'])); ?></td>
        <td><?= $p['term_name']; ?></td>
        <td><?= $p['academic_year'] ?? 'N/A'; ?></td>
        <td>GHC <?= number_format($p['amount'], 2); ?></td>
        <td><?= $p['payment_type']; ?></td>
        <td><span class="status <?= $p['status_class']; ?>"><?= $p['status']; ?></span></td>
        <td>
            <a href="print_receipt.php?id=<?= $p['id']; ?>" class="btn-icon">
                <i class="fas fa-eye"></i>
            </a>
            <button class="btn-icon edit-payment" data-id="<?= $p['id']; ?>">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn-icon delete-payment" data-id="<?= $p['id']; ?>">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
    <?php } ?>
</tbody>
    </table>
    
            </div>
        </div>
    </div>
</main>

<!-- Replace the payment modal section in payments.php -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Record New Payment</h2>
        <form id="paymentForm" method="POST">
            <input type="hidden" name="id" id="paymentId">
            <input type="hidden" name="form_action" id="formAction" value="add_payment">

            <div class="form-group">
                <label>Class*</label>
                <select id="class_id" name="class_id" required>
                    <option value="">Select Class</option>
                    <?php
                    $classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name ASC");
                    while ($row = $classes->fetch_assoc()) {
                        echo "<option value='{$row['id']}'>{$row['class_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="academic_year_id">Academic Year*</label>
                <select id="academic_year_id" name="academic_year_id" required>
                    <option value="">Select Academic Year</option>
                    <?php
                    $year_sql = "SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC";
                    $year_result = $conn->query($year_sql);
                    
                    if ($year_result && $year_result->num_rows > 0) {
                        while ($row = $year_result->fetch_assoc()) {
                            $selected = ($row['id'] == $current_academic_year_id) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>{$row['year_name']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Student*</label>
                <select id="student_id" name="student_id" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id']; ?>" data-class-id="<?= $s['class_id']; ?>">
                        <?= $s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['student_id'] . ')'; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-group" id="totalPaidContainer" style="display:none;">
                    <input type="text" id="total_paid" readonly class="total-paid-field" />
                </div>
            </div>

            <div class="form-group">
                <label>Payment Date*</label>
                <input type="date" name="payment_date" id="payment_date" required value="<?= date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="term_id">Term*</label>
                <select id="term_id" name="term_id" required>
                    <option value="">Select Term</option>
                    <?php
                    foreach ($terms as $term) {
                        echo "<option value='{$term['id']}'>{$term['term_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Payment Type*</label>
                <select id="payment_type" name="payment_type" required>
                    <option value="">Select Payment Type</option>
                    <?php
                    $billingTypes = $conn->query("SELECT DISTINCT payment_type FROM billing ORDER BY payment_type ASC");
                    while ($row = $billingTypes->fetch_assoc()) {
                        echo "<option value='{$row['payment_type']}'>{$row['payment_type']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- NEW: Account Number Display Field -->
            <div class="form-group" id="accountNumberContainer" style="display:none;">
                <label>Linked Account</label>
                <input type="text" id="account_number_display" readonly 
                       style="font-weight: 600; padding: 10px; border-radius: 6px; border: 2px solid #d1d5db;">
            </div>

            <div class="form-group">
                <label>Amount*</label>
                <input type="number" step="0.01" id="amount" name="amount" required>
                <input type="text" id="total_billing_amount" readonly style="display:none;" class="total-billing-field">
            </div>

            <div class="form-group">
                <label>Payment Method*</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Mobile Money">Mobile Money</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="description"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<style>
#account_number_display {
    transition: all 0.3s ease;
}

#accountNumberContainer {
    margin-top: 10px;
}
</style>


<!-- Bulk Payment Upload Modal -->
<div id="bulkPaymentModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" data-modal="bulkPaymentModal">&times;</span>
        <h2>Bulk Payment Upload (CSV)</h2>
        <form method="POST" enctype="multipart/form-data" id="bulkPaymentForm">
            <form method="POST" enctype="multipart/form-data" id="bulkPaymentForm">
            <input type="hidden" name="form_action" value="bulk_payment">
            <div class="form-group">
                <label>Academic Year*</label>
                <select name="bulk_academic_year_id" required>
                    <option value="">Select Year</option>
                    <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Term*</label>
                <select name="bulk_term_id" required>
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Type*</label>
                <select name="bulk_payment_type" required>
                    <option value="">Select Type</option>
                    <?php
                    $types = $conn->query("SELECT DISTINCT payment_type FROM billing ORDER BY payment_type");
                    while ($t = $types->fetch_assoc()) {
                        echo "<option value='{$t['payment_type']}'>{$t['payment_type']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method*</label>
                <select name="bulk_payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Mobile Money">Mobile Money</option>
                </select>
            </div>
            <div class="form-group">
                <label>CSV File* (student_id, payment_date, amount, description)</label>
                <input type="file" name="bulk_file" accept=".csv" required>
                <small class="text-muted">
                    <a href="sample_bulk_payment.csv" download>Download Sample CSV</a>
                </small>
            </div>
            <div class="form-actions">
               <button type="button" class="btn-cancel" data-modal="bulkPaymentModal">Cancel</button>
                <button type="submit" class="btn-submit">Upload & Process</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Delete</h2>
    <p id="deleteMessage">Are you sure you want to delete this payment?</p>
    <form id="deleteForm" method="POST" action="payments.php">
        <input type="hidden" name="id" id="deleteId">
        <input type="hidden" name="delete_payment" value="1">
        <div class="form-actions">
            <button type="button" class="btn-cancel">Cancel</button>
            <button type="submit" class="btn-submit">Delete</button>
        </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<!-- jQuery already exists -->

<script>
    window.allPaymentsData = <?= json_encode($payments); ?>;
    // In the PHP section where you output students
window.allStudents = <?= json_encode(array_map(function($s) {
    return [
        'id' => (int)$s['id'],
        'class_id' => (int)$s['class_id'],
        'first_name' => $s['first_name'],
        'last_name' => $s['last_name'],
        'student_id' => $s['student_id']
    ];
}, $students)); ?>;
</script>
<script src="js/student-filter.js"></script>
<script src="js/payments.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dropdown.js"></script>
<script src="js/dashboard.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".alert");
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add("fade-out"); // Start CSS fade
            setTimeout(() => alert.style.display = "none", 1000); // Remove after fade
        }, 5000); // 5 seconds delay
    });
});

</script>
<script>
/* -------------------------------------------------
   Modal Helpers – openModal() / closeModal()
   ------------------------------------------------- */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}
// Add this to your payments.js file after DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Specific handler for bulk payment modal
    const bulkModal = document.getElementById('bulkPaymentModal');
    if (bulkModal) {
        // Close when clicking X
        bulkModal.querySelector('.close').addEventListener('click', function() {
            bulkModal.style.display = 'none';
        });
        
        // Close when clicking outside
        bulkModal.addEventListener('click', function(e) {
            if (e.target === bulkModal) {
                bulkModal.style.display = 'none';
            }
        });
        
        // Close when clicking cancel button
        bulkModal.querySelector('.btn-cancel').addEventListener('click', function() {
            bulkModal.style.display = 'none';
        });
    }
});
</script>

</body>
</html>
