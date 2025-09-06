<?php
require_once 'config.php';
require_once 'session.php';
include 'control.php';
// Check authentication
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - GEBSCO</title>
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
     <link rel="stylesheet" type="text/css" href="css/db.css">
    <link rel="stylesheet" type="text/css" href="css/teachers.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="icon" href="images/favicon.ico">
   <link rel="stylesheet" href="css/dropdown.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Teacher Management</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Teachers</span>
                </div>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>School Teachers</h3>
                    <button class="btn-primary" id="addTeacherBtn">
                        <i class="fas fa-plus"></i> Add Teacher
                    </button>
                </div>
                
                <div class="card-body">
                    <table id="teachersTable" class="display">
                        <thead>
                            <tr>
                                <th>Teacher ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Specialization</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['specialization']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($teacher['status']); ?>">
                                        <?php echo htmlspecialchars($teacher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-icon edit-teacher" data-id="<?php echo $teacher['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-teacher" data-id="<?php echo $teacher['id']; ?>" title="Delete">
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
    
    <!-- Add/Edit Teacher Modal -->
    <div id="teacherModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New Teacher</h2>
            <form id="teacherForm" method="POST">
                <input type="hidden" name="id" id="teacherId">
                <input type="hidden" name="add_teacher" id="formAction" value="1">
                
                <div class="form-group">
                    <label for="first_name">First Name*</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email*</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization">
                </div>
                
                <div class="form-group">
                    <label for="status">Status*</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
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
        <p>Are you sure you want to delete this teacher? This action cannot be undone.</p>
        <form id="deleteForm" method="POST" action="teachers.php">
            <input type="hidden" name="id" id="deleteId">
            <div class="form-actions" style="justify-content: center;">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" name="delete_teacher" class="btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

    <!-- Add these script tags before your existing JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.colVis.min.js"></script>
    <script src="js/darkmode.js"></script>
      <script src="js/teachers.js"></script>
   <script>
    // Make teachers data available to JavaScript
    window.teachersData = <?php echo json_encode($teachers); ?>;
  </script>
    <script src="js/dropdown.js"></script>
</body>
</html>