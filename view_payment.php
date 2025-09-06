<?php
// view_payment.php
require_once 'config.php';
require_once 'session.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payments.php");
    exit();
}

$payment_id = intval($_GET['id']);

$sql = "SELECT p.*, s.first_name, s.last_name, s.student_id as student_code, s.parent_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        WHERE p.id = $payment_id";

$result = $conn->query($sql);
$payment = $result->fetch_assoc();

if (!$payment) {
    header("Location: payments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - <?php echo htmlspecialchars($payment['receipt_no']); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="view-page">
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Payment Details</h1>
                <div class="breadcrumb">
                    <a href="payments.php">Payments</a> / <span>Details</span>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Receipt #<?php echo htmlspecialchars($payment['receipt_no']); ?></h3>
                </div>
                <div class="card-body view-details">
                    <p><strong>Student Name:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($payment['student_code']); ?></p>
                    <p><strong>Parent/Guardian:</strong> <?php echo htmlspecialchars($payment['parent_name']); ?></p>
                    <hr>
                    <p><strong>Payment Date:</strong> <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></p>
                    <p><strong>Payment Type:</strong> <?php echo htmlspecialchars($payment['payment_type']); ?></p>
                    <p><strong>Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($payment['description'])); ?></p>
                    <p><strong>Status:</strong> <span class="status <?php echo strtolower($payment['status']); ?>"><?php echo htmlspecialchars($payment['status']); ?></span></p>
                </div>
                <div class="form-actions">
                    <a href="payments.php" class="btn-cancel">Back to Payments</a>
                    <button class="btn-primary print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>


<?php
require_once 'config.php';
require_once 'session.php';
include 'classcontrol.php'; // Handles POST, sets $_SESSION flash, populates $classes and $teachers

// Simple escape helper
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - GEBSCO</title>

    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="css/classes.css">
    <link rel="stylesheet" href="css/dropdown.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php include 'topnav.php'; ?>

        <div class="content-wrapper">
            <div class="page-header">
                <h1>Class Management</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Classes</span>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success" role="status" aria-live="polite">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <?php echo e($_SESSION['message']); unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo e($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>School Classes</h3>
                    <button class="btn-primary" id="addClassBtn" type="button">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add Class
                    </button>
                </div>

                <div class="card-body">
                    <table id="classesTable" class="display">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Class Name</th>
                                <th scope="col">Academic Year</th>
                                <th scope="col">Class Teacher</th>
                                <th scope="col">Description</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?php echo e($class['id']); ?></td>
                                    <td><?php echo e($class['class_name']); ?></td>
                                    <td><?php echo e($class['academic_year']); ?></td>
                                    <td>
                                        <?php if (!empty($class['class_teacher_id'])): ?>
                                            <?php echo e($class['first_name'] . ' ' . $class['last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($class['description']); ?></td>
                                    <td>
                                        <button
                                            class="btn-icon edit-class"
                                            type="button"
                                            data-id="<?php echo e($class['id']); ?>"
                                            data-teacher-id="<?php echo e($class['class_teacher_id']); ?>"
                                            title="Edit"
                                            aria-label="Edit class <?php echo e($class['class_name']); ?>">
                                            <i class="fas fa-edit" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            class="btn-icon delete-class"
                                            type="button"
                                            data-id="<?php echo e($class['id']); ?>"
                                            title="Delete"
                                            aria-label="Delete class <?php echo e($class['class_name']); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Class Modal -->
    <div id="classModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-content" role="document">
            <button class="close" type="button" aria-label="Close modal" title="Close">&times;</button>
            <h2 id="modalTitle">Add New Class</h2>

            <!-- Post back to this same page -->
            <form id="classForm" method="POST" action="<?php echo e(basename($_SERVER['PHP_SELF'])); ?>" autocomplete="off" novalidate>
                <input type="hidden" name="id" id="classId">
                <input type="hidden" name="form_action" id="formAction" value="add_class">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <div class="form-group">
                    <label for="class_name">Class Name*</label>
                    <input type="text" id="class_name" name="class_name" required>
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
                    <label for="class_teacher_id">Class Teacher</label>
                    <select id="class_teacher_id" name="class_teacher_id">
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo e($teacher['id']); ?>">
                                <?php echo e($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
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
        <h2>Confirm Deletion</h2>
        <p>Are you sure you want to delete this class? This action cannot be undone.</p>
        <form id="deleteForm" method="POST" action="classes.php">
            <input type="hidden" name="id" id="deleteId">
            <div class="form-actions" style="justify-content: center;">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" name="delete_class" class="btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>
    <!-- Scripts (bottom) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.colVis.min.js"></script>

    <!-- Your existing scripts -->
    <script src="js/darkmode.js"></script>
    <script src="js/dropdown.js"></script>
    <script src="js/classes.js"></script>
</body>
</html>
