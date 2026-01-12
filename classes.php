<?php
require_once 'config.php';
require_once 'session.php';
include 'classcontrol.php'; // Handles POST, sets $_SESSION flash, populates $classes and $teachers
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// --- CSRF Token (create if not set) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Simple escape helper
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Class Management - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="css/classes.css">
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
                                <th scope="col">Class Name</th>
                                <th scope="col">Academic Year</th>
                                <th scope="col">Class Teacher</th>
                                <th scope="col">Students</th>
                                <th scope="col">Description</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                                <td>
                                    <?php 
                                    if ($class['class_teacher_id']) {
                                        echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']);
                                    } else {
                                        echo '<span style="color: #6c757d; font-style: italic;">No teacher assigned</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="student-count <?php echo $class['student_count'] > 0 ? 'has-students' : 'no-students'; ?>">
                                        <?php echo $class['student_count']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($class['description']); ?></td>
                                <td>
                                    <button class="btn-icon edit-class" 
                                            data-id="<?php echo $class['id']; ?>"
                                            data-class-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                            data-academic-year="<?php echo htmlspecialchars($class['academic_year']); ?>"
                                            data-teacher-id="<?php echo $class['class_teacher_id']; ?>"
                                            data-description="<?php echo htmlspecialchars($class['description']); ?>"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="btn-icon delete-class" 
                                            data-id="<?php echo $class['id']; ?>"
                                            data-class-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                            data-billing-count="<?php echo $class['billing_count']; ?>"
                                            data-student-count="<?php echo $class['student_count']; ?>"
                                            data-assignment-count="<?php echo $class['assignment_count']; ?>"
                                            data-attendance-count="<?php echo $class['attendance_count']; ?>"
                                            title="Delete"
                                            <?php if ($class['billing_count'] > 0 || $class['student_count'] > 0 || $class['assignment_count'] > 0 || $class['attendance_count'] > 0): ?>
                                                style="color: #ffc107 !important; background-color: rgba(255, 193, 7, 0.1) !important;"
                                            <?php endif; ?>
                                            >
                                        <i class="fas fa-trash"></i>
                                        <?php if ($class['billing_count'] > 0 || $class['student_count'] > 0 || $class['assignment_count'] > 0 || $class['attendance_count'] > 0): ?>
                                            <i class="fas fa-exclamation-triangle" style="font-size: 10px; margin-left: 2px;"></i>
                                        <?php endif; ?>
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
                        <option value="">Select Academic Year</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo e($year['year_name']); ?>" <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                <?php echo e($year['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
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

    <!-- Enhanced Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="delete-modal-header">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <h2>Confirm Deletion</h2>
            </div>
            <p>Are you sure you want to delete the class "<span id="deleteClassName"></span>"? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" action="classes.php">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="id" id="deleteId">
                <input type="hidden" name="delete_class" value="1">
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-danger">Delete Class</button>
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
    <script src="js/dashboard.js"></script>
    <script src="js/classes.js"></script>
    <!-- Add this script block before your classes.js inclusion -->
    <script>
        // Make CSRF token available to JavaScript
        window.csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    </script>
</body>
</html>
