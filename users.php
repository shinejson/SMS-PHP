<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');

// Get available teachers without user accounts
$available_teachers = [];
$teacher_sql = "SELECT t.id, t.first_name, t.last_name, t.email, t.phone 
                FROM teachers t 
                WHERE t.user_id IS NULL OR t.user_id = ''";
$teacher_result = $conn->query($teacher_sql);
if ($teacher_result) {
    while ($row = $teacher_result->fetch_assoc()) {
        $available_teachers[] = $row;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Handle teacher user creation
    if (isset($_POST['create_teacher_user'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = 'teacher';
        
        // Get teacher details
        $teacher_sql = "SELECT first_name, last_name, email FROM teachers WHERE id = ?";
        $stmt = $conn->prepare($teacher_sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $teacher_result = $stmt->get_result();
        
        if ($teacher_result->num_rows > 0) {
            $teacher = $teacher_result->fetch_assoc();
            $full_name = $teacher['first_name'] . ' ' . $teacher['last_name'];
            $email = $teacher['email'];
            
            // Check if username exists
            $check_sql = "SELECT id FROM users WHERE username = '$username'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = "Username already exists!";
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert user
                    $sql = "INSERT INTO users (username, password, full_name, email, role) 
                            VALUES ('$username', '$password', '$full_name', '$email', '$role')";
                    
                    if ($conn->query($sql)) {
                        $user_id = $conn->insert_id;
                        
                        // Update teacher record with user_id
                        $update_sql = "UPDATE teachers SET user_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param("ii", $user_id, $teacher_id);
                        
                        if ($stmt->execute()) {
                            $conn->commit();
                            $_SESSION['message'] = "Teacher user account created and linked successfully!";
                        } else {
                            throw new Exception("Error linking teacher account: " . $conn->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Error creating user: " . $conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                }
            }
        } else {
            $_SESSION['error'] = "Teacher not found!";
        }
    }
 // Example for add_user (replace raw queries)
elseif (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $signature = $_POST['signature'] ?? '';
    $profile_image = '';

     // Handle file upload
        $profile_image = '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                    $profile_image = $conn->real_escape_string($fileName);
                }
            }
        }

    // Check username with prepared
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Username already exists!";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, signature, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssss", $username, $password, $full_name, $email, $role, $signature, $profile_image);
        if ($insert_stmt->execute()) {
            $_SESSION['message'] = "User added successfully!";
        } else {
            $_SESSION['error'] = "Error adding user: " . $conn->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}
// Apply similar fixes to update_user, delete_user, etc.
elseif (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $username = $conn->real_escape_string($_POST['username']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        $signature = $conn->real_escape_string($_POST['signature']);
        
        // Check if username exists for another user
        $check_sql = "SELECT id FROM users WHERE username = '$username' AND id != $id";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Username already exists for another user!";
        } else {
            // Update password only if provided
            $password_update = "";
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_update = ", password = '$password'";
            }
            
            // Handle file upload
            $image_update = "";
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $imageFileType = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($imageFileType, $allowedTypes)) {
                    // Delete old image if exists
                    $oldImageQuery = $conn->query("SELECT profile_image FROM users WHERE id = $id");
                    if ($oldImageQuery->num_rows > 0) {
                        $oldImage = $oldImageQuery->fetch_assoc()['profile_image'];
                        if ($oldImage && file_exists($uploadDir . $oldImage)) {
                            unlink($uploadDir . $oldImage);
                        }
                    }
                    
                    $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                        $image_update = ", profile_image = '" . $conn->real_escape_string($fileName) . "'";
                    }
                }
            }
            
            $sql = "UPDATE users SET 
                    username = '$username',
                    full_name = '$full_name',
                    email = '$email',
                    role = '$role',
                    signature = '$signature'
                    $password_update
                    $image_update
                    WHERE id = $id";
            
            if ($conn->query($sql)) {
                $_SESSION['message'] = "User updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating user: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = intval($_POST['id']);
        
        // Check if user is linked to a teacher
        $teacher_check_sql = "SELECT id FROM teachers WHERE user_id = $id";
        $teacher_check_result = $conn->query($teacher_check_sql);
        
        if ($teacher_check_result->num_rows > 0) {
            $_SESSION['error'] = "Cannot delete user: This account is linked to a teacher profile. Please unlink the teacher first.";
        } else {
            // Delete associated image file
            $imageQuery = $conn->query("SELECT profile_image FROM users WHERE id = $id");
            if ($imageQuery->num_rows > 0) {
                $image = $imageQuery->fetch_assoc()['profile_image'];
                if ($image && file_exists($uploadDir . $image)) {
                    unlink($uploadDir . $image);
                }
            }
            
            // Prevent deleting current user
            if ($id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own account!";
            } else {
                $sql = "DELETE FROM users WHERE id = $id";
                if ($conn->query($sql)) {
                    $_SESSION['message'] = "User deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting user: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['unlink_teacher'])) {
        $user_id = intval($_POST['user_id']);
        
        // Remove user_id from teacher record
        $sql = "UPDATE teachers SET user_id = NULL WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Teacher profile unlinked successfully!";
        } else {
            $_SESSION['error'] = "Error unlinking teacher profile: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: users.php");
    exit();
}

// Get all users with teacher linkage info
$users = [];
$sql = "SELECT u.*, t.id as teacher_id, t.first_name as teacher_first_name, t.last_name as teacher_last_name
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id 
        ORDER BY u.role, u.full_name";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // FIXED: Skip null/false rows to prevent warnings
        if ($row !== null && $row !== false && isset($row['id'])) {
            $users[] = $row;
        }
    }
    $result->free(); // Good practice: Free result set
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>User Management - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="css/users.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/dropdown.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1>User Management</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Users</span>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" style="animation: shake 0.5s;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Teacher User Creation Card -->
            <?php if (!empty($available_teachers)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Create Teacher User Accounts</h3>
                    <span class="badge badge-info"><?php echo count($available_teachers); ?> teachers available</span>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> All teachers already have user accounts linked.
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- System Users Card -->
            <div class="card">
    <div class="card-header">
        <h3>System Users</h3>
        <div class="header-actions">
            <?php if (!empty($available_teachers)): ?>
            <button class="btn-primary" id="addTeacherUserBtn" style="margin-right: 10px;">
                <i class="fas fa-chalkboard-teacher"></i> Add Teacher User
            </button>
            <?php endif; ?>
            <button class="btn-primary" id="addUserBtn">
                <i class="fas fa-plus"></i> Add Regular User
            </button>
        </div>
    </div>
                
                <div class="card-body">
<table id="usersTable" class="display">
    <thead>
        <tr>
            <th>Username</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <!-- Other columns like Signature, Profile, Linked Teacher, etc. -->
            <th>Linked Teacher</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $row): ?>
            <?php if (isset($row['id']) && $row !== null): ?> <!-- FIXED: Safeguard against null rows -->
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? 'N/A') ?></td>
                    <td>
                        <span class="role-badge role-<?= strtolower($row['role'] ?? '') ?>">
                            <?= ucfirst($row['role'] ?? 'Unknown') ?>
                        </span>
                    </td>
                    <!-- Add other <td> for signature, profile_image if present -->
                    <td><?= ($row['teacher_id'] ?? null) ? 'Yes (ID: ' . htmlspecialchars($row['teacher_id'] ?? '') . ')' : 'No' ?></td>
                    <td class="actions">
                        <button class="btn-icon edit-user" data-id="<?= htmlspecialchars($row['id']) ?>"
                                data-is-teacher="<?= ($row['teacher_id'] ?? null) ? '1' : '0' ?>"
                                data-teacher-id="<?= htmlspecialchars($row['teacher_id'] ?? '') ?>"
                                data-signature="<?= htmlspecialchars($row['signature'] ?? '') ?>"
                                data-image="<?= htmlspecialchars($row['profile_image'] ?? '') ?>"
                                title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (($row['teacher_id'] ?? null)): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Unlink this teacher from the user account?')">
                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id']) ?>">
                                <input type="hidden" name="unlink_teacher" value="1">
                                <button type="submit" class="btn-icon" title="Unlink Teacher">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn-icon delete-user" data-id="<?= htmlspecialchars($row['id']) ?>"
                                data-is-teacher="<?= ($row['teacher_id'] ?? null) ? '1' : '0' ?>"
                                title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endif; ?> <!-- End safeguard -->
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="6" class="text-center">No users found.</td> <!-- Adjusted colspan: 6 columns now -->
            </tr>
        <?php endif; ?>
    </tbody>
</table>
                </div>
            </div>
        </div>
    </main>
    
<!-- Add/Edit User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Add New User</h2>
        
        <!-- Tab Navigation -->
        <div class="modal-tabs">
            <button type="button" class="tab-btn active" data-tab="regular-user">Regular User</button>
            <button type="button" class="tab-btn" data-tab="teacher-user">Teacher User</button>
        </div>
        
        <!-- Regular User Form -->
        <div id="regular-user-tab" class="tab-content active">
            <form id="userForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="userId">
                <input type="hidden" name="add_user" id="formAction" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username*</label>
                        <input type="text" id="username" name="username" required minlength="3" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role*</label>
                        <select id="role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" id="passwordLabel">Password*</label>
                    <input type="password" id="password" name="password" minlength="6">
                    <small id="passwordHelp">Leave blank to keep current password</small>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name*</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email*</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="signature">Signature</label>
                    <input type="text" id="signature" name="signature" placeholder="User's official signature">
                </div>
                
                <div class="form-group">
                    <label for="profile_image">Profile Image</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                    <small>Accepted formats: JPG, JPEG, PNG, GIF (Max: 2MB)</small>
                    <div id="imagePreview" style="margin-top: 10px; display: none;">
                        <img id="previewImg" src="#" alt="Preview" style="max-width: 100px; max-height: 100px;">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-submit">Save User</button>
                </div>
            </form>
        </div>
        
        <!-- Teacher User Form -->
        <div id="teacher-user-tab" class="tab-content">
            <form method="POST" id="teacherUserForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="teacher_id">Select Teacher*</label>
                        <select id="teacher_id" name="teacher_id" required class="form-select">
                            <option value="">Select a teacher</option>
                            <?php foreach ($available_teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>" 
                                        data-firstname="<?= htmlspecialchars($teacher['first_name']) ?>"
                                        data-lastname="<?= htmlspecialchars($teacher['last_name']) ?>"
                                        data-email="<?= htmlspecialchars($teacher['email']) ?>">
                                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?> 
                                    - <?= htmlspecialchars($teacher['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_username">Username*</label>
                        <input type="text" id="teacher_username" name="username" required minlength="3" maxlength="20" class="form-input">
                        <small class="form-help">Suggested: first name or combination of first and last name</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="teacher_password">Password*</label>
                    <input type="password" id="teacher_password" name="password" required minlength="6" class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="teacher_full_name">Full Name</label>
                        <input type="text" id="teacher_full_name" name="full_name" readonly class="form-input readonly-field">
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_email">Email</label>
                        <input type="email" id="teacher_email" name="email" readonly class="form-input readonly-field">
                    </div>
                </div>
                
                <input type="hidden" name="create_teacher_user" value="1">
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Create Teacher Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rest of the file remains the same... -->

<?php
require_once 'config.php'; 
// Fetch school name from school_settings table
$schoolName = 'Your School'; // fallback if query fails
$result = $conn->query("SELECT school_name FROM school_settings LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $schoolName = htmlspecialchars($row['school_name']);
}
?>

<footer class="dashboard-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php echo $schoolName; ?>. All rights reserved.</p>
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Help Center</a>
    </div>
</footer>

<!-- jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Then DataTable and its dependencies -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<!-- Then your custom scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/data-tables.js"></script>
<script src="js/users.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dashboard.js"></script>

<script>
// Auto-fill teacher form when teacher is selected
document.getElementById('teacher_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const firstName = selectedOption.getAttribute('data-firstname');
    const lastName = selectedOption.getAttribute('data-lastname');
    const email = selectedOption.getAttribute('data-email');
    
    // Auto-fill fields
    document.getElementById('teacher_full_name').value = firstName + ' ' + lastName;
    document.getElementById('teacher_email').value = email;
    
    // Suggest username (first name lowercase)
    if (firstName) {
        document.getElementById('teacher_username').value = firstName.toLowerCase();
    }
});

$(document).ready(function() {
    // Initialize DataTable with export buttons
    $('#usersTable').DataTable({
        dom: '<"top"lfB>rt<"bottom"ip><"clear">',
        buttons: [
            {
                extend: 'copy',
                className: 'btn-dt-copy',
                text: '<i class="fas fa-copy"></i> Copy',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude actions column
                }
            },
            {
                extend: 'csv',
                className: 'btn-dt-csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude actions column
                }
            },
            {
                extend: 'excel',
                className: 'btn-dt-excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude actions column
                }
            },
            {
                extend: 'pdf',
                className: 'btn-dt-pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude actions column
                }
            },
            {
                extend: 'print',
                className: 'btn-dt-print',
                text: '<i class="fas fa-print"></i> Print',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude actions column
                },
                customize: function (win) {
                    $(win.document.body)
                        .css('font-size', '10pt')
                        .prepend(
                            '<h2>System Users Report</h2><p>Generated on: <?php echo date("F j, Y, g:i A"); ?></p>'
                        );
                    
                    $(win.document.body).find('table')
                        .addClass('compact')
                        .css('font-size', 'inherit');
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },



columnDefs: [
    { 
        orderable: false, 
        targets: [5], // Actions column (now index 5)
        searchable: false
    }
    // Remove the targets: [0] block since ID is gone
],
order: [[0, 'asc']], // Now orders by Username (index 0)
responsive: true,
        stateSave: true // Remember table state
    });
});
// Tab functionality for modal
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all tabs and contents
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding content
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
    });
});

// Button click handlers
document.getElementById('addUserBtn').addEventListener('click', function() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.querySelector('[data-tab="regular-user"]').click();
    document.getElementById('userModal').style.display = 'block';
});

document.getElementById('addTeacherUserBtn').addEventListener('click', function() {
    document.getElementById('modalTitle').textContent = 'Create Teacher User Account';
    document.querySelector('[data-tab="teacher-user"]').click();
    document.getElementById('userModal').style.display = 'block';
});

// Auto-fill teacher form when teacher is selected
document.getElementById('teacher_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const firstName = selectedOption.getAttribute('data-firstname');
    const lastName = selectedOption.getAttribute('data-lastname');
    const email = selectedOption.getAttribute('data-email');
    
    // Auto-fill fields
    document.getElementById('teacher_full_name').value = firstName + ' ' + lastName;
    document.getElementById('teacher_email').value = email;
    
    // Suggest username (first name lowercase)
    if (firstName) {
        document.getElementById('teacher_username').value = firstName.toLowerCase();
    }
});

// Close modal when clicking X
document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('userModal').style.display = 'none';
});

// Close modal when clicking cancel buttons
document.querySelectorAll('.btn-cancel').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('userModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>
<style>
.role-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.role-admin {
    background-color: #dc3545;
    color: white;
}

.role-teacher {
    background-color: #007bff;
    color: white;
}

.role-staff {
    background-color: #28a745;
    color: white;
}

.badge {
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.badgee {
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.form-select, .form-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-help {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}

.modal-tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
}

.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 14px;
    color: #666;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
    font-weight: bold;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.readonly-field {
    background-color: #f8f9fa !important;
    cursor: not-allowed;
}

</style>
</body>
</html>