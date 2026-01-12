<?php
require_once 'config.php';  // DB connection
require_once 'session.php'; // User session validation
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// Get student_id from URL
$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    die("No student selected.");
}

// Fetch student info
$stmt = $conn->prepare("SELECT id, student_id, first_name, last_name, class_id FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found.");
}

// Get the student's class_id
$student_class_id = $student['class_id'];

// Fetch filters
$academic_year_id = $_GET['academic_year_id'] ?? '';
$term_id = $_GET['term_id'] ?? '';
$payment_type = $_GET['payment_type'] ?? '';

// Build query with correct joins - payments doesn't have class_id, so join through students
$sql = "SELECT p.id as payment_id, p.receipt_no, ay.year_name as academic_year, t.term_name, c.class_name, 
               p.payment_type, p.amount, p.payment_date, p.payment_method, p.status
        FROM payments p
        LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
        LEFT JOIN terms t ON p.term_id = t.id
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE p.student_id = ?";
$params = [$student_id];
$types = "i";

if ($academic_year_id) { 
    $sql .= " AND p.academic_year_id = ?"; 
    $params[] = $academic_year_id; 
    $types .= "i"; 
}
if ($term_id) { 
    $sql .= " AND p.term_id = ?"; 
    $params[] = $term_id; 
    $types .= "i"; 
}
if ($payment_type) { 
    $sql .= " AND p.payment_type = ?"; 
    $params[] = $payment_type; 
    $types .= "s"; 
}

$sql .= " ORDER BY p.payment_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get class name for display
$class_name = 'N/A';
if ($student_class_id) {
    $stmt = $conn->prepare("SELECT class_name FROM classes WHERE id = ?");
    $stmt->bind_param("i", $student_class_id);
    $stmt->execute();
    $class_result = $stmt->get_result()->fetch_assoc();
    $class_name = $class_result['class_name'] ?? 'N/A';
    $stmt->close();
}

// Calculate totals
$total_amount = 0;
foreach ($payments as $payment) {
    $total_amount += $payment['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Statement - <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            flex-wrap: wrap;
        }
        .student-info {
            flex: 1;
            min-width: 300px;
        }
        .summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .text-right {
            text-align: right;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #bee5eb;
        }
        .status-paid {
            color: #28a745;
            font-weight: bold;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="student-info">
                <h2>Student Payment Statement</h2>
                <p><strong>Student:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
                <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
                <p><strong>Class:</strong> <?= htmlspecialchars($class_name) ?></p>
            </div>
            <div>
                <a href="payment-ladger.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Ledger
                </a>
            </div>
        </div>

        <div class="summary">
            <h4>Payment Summary</h4>
            <p><strong>Total Payments:</strong> GH₵ <?= number_format($total_amount, 2) ?></p>
            <p><strong>Number of Transactions:</strong> <?= count($payments) ?></p>
            <?php if ($academic_year_id): ?>
                <p><strong>Academic Year Filter:</strong> <?= htmlspecialchars($academic_year_id) ?></p>
            <?php endif; ?>
            <?php if ($term_id): ?>
                <p><strong>Term Filter:</strong> <?= htmlspecialchars($term_id) ?></p>
            <?php endif; ?>
            <?php if ($payment_type): ?>
                <p><strong>Payment Type Filter:</strong> <?= htmlspecialchars($payment_type) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($payments)): ?>
            <div class="alert-info">
                <i class="fas fa-info-circle"></i> No payment records found for this student.
            </div>
        <?php else: ?>
            <table id="statementTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Payment Date</th>
                        <th>Academic Year</th>
                        <th>Term</th>
                        <th>Class</th>
                        <th>Payment Type</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Amount (GH₵)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['receipt_no'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['payment_date']) ?></td>
                        <td><?= htmlspecialchars($row['academic_year'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['term_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['class_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['payment_type']) ?></td>
                        <td><?= htmlspecialchars($row['payment_method'] ?? 'N/A') ?></td>
                        <td class="<?= ($row['status'] === 'Paid') ? 'status-paid' : 'status-pending' ?>">
                            <?= htmlspecialchars($row['status'] ?? 'N/A') ?>
                        </td>
                        <td class="text-right"><?= number_format($row['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="8" style="text-align:right">Total:</th>
                        <th class="text-right">GH₵ <?= number_format($total_amount, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function () {
            $('#statementTable').DataTable({
                responsive: true,
                scrollX: true,
                pageLength: 25,
                order: [[1, 'desc']], // Order by payment date descending
                dom: 'Blfrtip',
                buttons: [
                    { 
                        extend: 'copy', 
                        className: 'btn btn-secondary',
                        title: 'Student Statement - <?= htmlspecialchars(addslashes($student["first_name"] . " " . $student["last_name"])) ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    { 
                        extend: 'csv', 
                        className: 'btn btn-secondary',
                        title: 'Student Statement - <?= htmlspecialchars(addslashes($student["first_name"] . " " . $student["last_name"])) ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    { 
                        extend: 'excel', 
                        className: 'btn btn-secondary',
                        title: 'Student Statement - <?= htmlspecialchars(addslashes($student["first_name"] . " " . $student["last_name"])) ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    { 
                        extend: 'pdf', 
                        className: 'btn btn-secondary',
                        title: 'Student Statement - <?= htmlspecialchars(addslashes($student["first_name"] . " " . $student["last_name"])) ?>',
                        orientation: 'landscape',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    { 
                        extend: 'print', 
                        className: 'btn btn-secondary',
                        title: 'Student Statement - <?= htmlspecialchars(addslashes($student["first_name"] . " " . $student["last_name"])) ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                footerCallback: function (row, data, start, end, display) {
                    var api = this.api();
                    var total = api.column(8, {page: 'current'}).data().reduce(function (a, b) {
                        return parseFloat(a) + parseFloat(b);
                    }, 0);
                    $(api.column(8).footer()).html('GH₵ ' + total.toFixed(2));
                }
            });
        });
    </script>
</body>
</html>