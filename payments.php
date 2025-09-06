<?php
require_once 'config.php';
require_once 'session.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_action'])) {
        $form_action = $_POST['form_action'];

        // --- Add Payment with Prepared Statement ---
        if ($form_action === 'add_payment') {
            // Get and sanitize all POST data
            $student_id = intval($_POST['student_id']);
            $payment_date = $_POST['payment_date'];
            $term_id = intval($_POST['term_id']);
            $payment_type = $_POST['payment_type'];
            $amount = floatval($_POST['amount']);
            $payment_method = $_POST['payment_method'];
            $description = $_POST['description'];
            $collected_by_user_id = $_SESSION['user_id'] ?? 1;

            // Loop to ensure a unique receipt number is generated
            $receipt_no = '';
            do {
                $receipt_no = 'PY' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $check_sql = "SELECT id FROM payments WHERE receipt_no = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param('s', $receipt_no);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
            } while ($check_result->num_rows > 0);
            
            // SQL query to insert new payment
        $sql = "INSERT INTO payments
        (receipt_no, student_id, payment_date, term_id, payment_type, amount, payment_method, description, collected_by_user_id, status)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisssdssi',
        $receipt_no,
        $student_id,
        $payment_date,
        $term_id,
        $payment_type,   // "Extra Class" will now save correctly
        $amount,
        $payment_method,
        $description,
        $collected_by_user_id
        );


            if ($stmt->execute()) {
                $_SESSION['message'] = "Payment recorded successfully! Receipt No: $receipt_no";
            } else {
                $_SESSION['error'] = "Error recording payment: " . $stmt->error;
                $_SESSION['form_data'] = $_POST;
            }
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

    $sql = "UPDATE payments SET
                student_id = ?,
                payment_date = ?,
                term_id = ?,
                payment_type = ?,
                amount = ?,
                payment_method = ?,
                description = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isissssi',
        $student_id,
        $payment_date,
        $term_id,
        $payment_type,
        $amount,
        $payment_method,
        $description,
        $id
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Payment updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating payment: " . $stmt->error;
        $_SESSION['form_data'] = $_POST;
    }
}


        header("Location: payments.php");
        exit();
    }

    // --- Delete Payment with Prepared Statement ---
    elseif (isset($_POST['delete_payment'])) {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM payments WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Payment deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting payment: " . $stmt->error;
        }

        header("Location: payments.php");
        exit();
    }
}

// Get all payments (JOIN terms for term_name)
// Get all payments with total paid & billing amount
$payments = [];
$sql = "
    SELECT p.*,
           s.first_name, s.last_name, s.student_id AS student_code,
           u.full_name AS collector_name,
           c.class_name AS class_name,
           t.id AS term_id,
           t.term_name,
           -- Subquery: total paid for that student/payment_type/term
           (SELECT SUM(p2.amount) 
            FROM payments p2 
            WHERE p2.student_id = p.student_id 
              AND p2.payment_type = p.payment_type 
              AND p2.term_id = p.term_id) AS total_paid,
           -- Subquery: billing amount
           (SELECT b.amount 
            FROM billing b 
            WHERE b.class_id = s.class_id 
              AND b.payment_type = p.payment_type 
              AND b.term_id = p.term_id 
            LIMIT 1) AS billing_amount,
           CASE
               WHEN (SELECT SUM(p2.amount) 
                     FROM payments p2 
                     WHERE p2.student_id = p.student_id 
                       AND p2.payment_type = p.payment_type 
                       AND p2.term_id = p.term_id) 
                    > (SELECT b.amount 
                       FROM billing b 
                       WHERE b.class_id = s.class_id 
                         AND b.payment_type = p.payment_type 
                         AND b.term_id = p.term_id 
                       LIMIT 1)
               THEN 'Overpayment'
               WHEN (SELECT SUM(p2.amount) 
                     FROM payments p2 
                     WHERE p2.student_id = p.student_id 
                       AND p2.payment_type = p.payment_type 
                       AND p2.term_id = p.term_id) 
                    = (SELECT b.amount 
                       FROM billing b 
                       WHERE b.class_id = s.class_id 
                         AND b.payment_type = p.payment_type 
                         AND b.term_id = p.term_id 
                       LIMIT 1)
               THEN 'Full Payment'
               WHEN (SELECT SUM(p2.amount) 
                     FROM payments p2 
                     WHERE p2.student_id = p.student_id 
                       AND p2.payment_type = p.payment_type 
                       AND p2.term_id = p.term_id) 
                    > 0
               THEN 'Part Payment'
               ELSE 'Not Paid'
           END AS status
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN users u ON p.collected_by_user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN terms t ON p.term_id = t.id
    ORDER BY p.payment_date DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    // Compute status in PHP
    $billing = floatval($row['billing_amount'] ?? 0);
    $paid = floatval($row['total_paid'] ?? 0);

    if ($billing > 0) {
        if ($paid == 0) {
            $row['status'] = "Not Paid";
        } elseif ($paid < $billing) {
            $row['status'] = "Part Payment";
        } elseif ($paid == $billing) {
            $row['status'] = "Full Payment";
        } else {
            $row['status'] = "Overpayment";
        }
    } else {
        $row['status'] = "Unknown";
    }

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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
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

        <div class="card">
            <div class="card-header">
                <h3>Payment Records</h3>
                <button class="btn-primary" id="addPaymentBtn"><i class="fas fa-plus"></i> Record Payment</button>
            </div>
            <div class="card-body">
                <table id="paymentsTable" class="display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Receipt No.</th>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Term</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                 <?php foreach ($payments as $p): ?>
            <?php
            $expected = floatval($p['billing_amount']);
            $totalPaid = floatval($p['total_paid']);

            if ($expected > 0) {
                if ($totalPaid == $expected) {
                    $status = "Full Payment";
                } elseif ($totalPaid < $expected) {
                    $status = "Part Payment";
                } elseif ($totalPaid > $expected) {
                    $status = "Over Payment";
                } else {
                    $status = "Unknown";
                }
            } else {
                $status = "Unknown";
            }
            ?>
<tr>
    <td><?= $p['receipt_no']; ?></td>
    <td><?= $p['first_name'] . ' ' . $p['last_name']; ?></td>
    <td><?= date('M d, Y', strtotime($p['payment_date'])); ?></td>
    <td><?= $p['term_name']; ?></td>
    <td>GHC<?= number_format($p['amount'], 2); ?></td>
    <td><?= $p['payment_type']; ?></td>
    <td><span class="status <?= strtolower(str_replace(' ', '-', $status)); ?>"><?= $status; ?></span></td>
    <td>
        <a href="print_receipt.php?id=<?= $p['id']; ?>" class="btn-icon"><i class="fas fa-eye"></i></a>
        <button class="btn-icon edit-payment" data-id="<?= $p['id']; ?>"><i class="fas fa-edit"></i></button>
        <button class="btn-icon delete-payment" data-id="<?= $p['id']; ?>"><i class="fas fa-trash"></i></button>
    </td>
</tr>
<?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

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
                <label for="academic_year">Academic Year*</label>
                <select id="academic_year" name="academic_year" required>
                    <?php
                    // Fetch academic years from DB
                    $sql = "SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Mark current academic year as selected
                            $selected = ($row['is_current'] == 1) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>' 
                                . htmlspecialchars($row['year_name']) . '</option>';
                        }
                    } else {
                        echo '<option value="">No academic years available</option>';
                    }
                    ?>
                </select>
      </div>


            <div class="form-group">
                <label for="term_id">Term*</label>
                <select id="term_id" name="term_id" required>
                    <option value="">Select Term</option>
                    <?php
                    $selected_term = $_SESSION['form_data']['term_id'] ?? '';
                    foreach ($terms as $term) {
                        $selected = ($selected_term == $term['id']) ? 'selected' : '';
                        echo "<option value='{$term['id']}' $selected>{$term['term_name']}</option>";
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
</body>
</html>
