<?php
require_once 'config.php';
require_once 'session.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // Check if username already exists
        $check_sql = "SELECT id FROM users WHERE username = '$username'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Username already exists! Please choose a different username.";
        } else {
            $sql = "INSERT INTO users (username, password, full_name, email, role) 
                    VALUES ('$username', '$password', '$full_name', '$email', '$role')";
            
            if ($conn->query($sql)) {
                $_SESSION['message'] = "User added successfully!";
            } else {
                $_SESSION['error'] = "Error adding user: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $username = $conn->real_escape_string($_POST['username']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        
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
            
            $sql = "UPDATE users SET 
                    username = '$username',
                    full_name = '$full_name',
                    email = '$email',
                    role = '$role'
                    $password_update
                    WHERE id = $id";
            
            if ($conn->query($sql)) {
                $_SESSION['message'] = "User updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating user: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = intval($_POST['id']);
        
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
    header("Location: users.php");
    exit();
}

// Get all users
$users = [];
$sql = "SELECT * FROM users ORDER BY role, full_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="animation: shake 0.5s;">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
    
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - GEBSCO</title>
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
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>System Users</h3>
                    <button class="btn-primary" id="addUserBtn">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
                
                <div class="card-body">
                    <table id="usersTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="tbdata">
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn-icon edit-user" data-id="<?php echo $user['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-icon delete-user" data-id="<?php echo $user['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add/Edit User Modal -->
  <div id="userModal" class="modal">
    <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New User</h2>
            <form id="userForm" method="POST">
                <input type="hidden" name="id" id="userId">
                <input type="hidden" name="add_user" id="formAction" value="1">
                
                <div class="form-group">
                    <label for="username">Username*</label>
                      <input type="text" id="username" name="username" required minlength="3" maxlength="20">
                </div>
              

                <div class="form-group">
                    <label for="password" id="passwordLabel">Password*</label>
                    <input type="password" id="password" name="password" minlength="6" 
       data-edit-mode="<?php echo isset($_POST['update_user']) ? 'true' : 'false'; ?>">
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
                    <label for="role">Role*</label>
                    <select id="role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-submit">Save</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add this with your other CSS links -->
<!-- Add this before your closing body tag with other JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="js/data-tables.js"></script>
<script src="js/users.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dropdown.js"></script>
</body>
</html>

