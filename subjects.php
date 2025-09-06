<?php
require_once 'config.php';
require_once 'session.php';

// Fetch all subjects from the database
$subjects = [];
$sql = "SELECT * FROM subjects ORDER BY subject_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - GEBSCO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" href="css/subjects.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Subject Management</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Subjects</span>
                </div>
            </div>

            <?php
            if (isset($_SESSION['message'])) {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . 
                     htmlspecialchars($_SESSION['message']) . '</div>';
                unset($_SESSION['message']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . 
                     htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>

            <div class="card">
                <div class="card-header">
                    <h3>School Subjects</h3>
                    <button id="addSubjectBtn" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New Subject
                    </button>
                </div>

                <div class="card-body">
                    <table id="subjectsTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th> <!-- Hidden column -->
                                <th>Subject Name</th>
                                <th>Subject Code</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($subject['description'] ?? ''); ?></td>
                                <td><?php echo date('M j, Y', strtotime($subject['created_at'] ?? '')); ?></td>
                                <td>
                                    <button class="btn-icon edit-subject" data-id="<?php echo htmlspecialchars($subject['id'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-subject" data-id="<?php echo htmlspecialchars($subject['id'] ?? ''); ?>">
                                        <i class="fas fa-trash"></i>
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

    <!-- Add/Edit Subject Modal -->
    <div id="subjectModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New Subject</h2>
            <form id="subjectForm" action="subject_control.php" method="POST">
                <input type="hidden" name="form_action" id="formAction">
                <input type="hidden" name="id" id="subjectId">
                <div class="form-group">
                    <label for="subject_name">Subject Name *</label>
                    <input type="text" id="subject_name" name="subject_name" required>
                </div>
                <div class="form-group" id="subjectCodeGroup">
                    <label for="subject_code">Subject Code</label>
                    <input type="text" id="subject_code" name="subject_code" readonly>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                 <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-submit">Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this subject? This action cannot be undone.</p>
            <form id="deleteForm" action="subject_control.php" method="POST">
                <input type="hidden" name="form_action" value="delete_subject">
                <input type="hidden" name="id" id="deleteId">
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-danger">Delete Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script> 
    <script src="js/darkmode.js"></script>
    <script src="js/subjects.js"></script>
</body>
</html>