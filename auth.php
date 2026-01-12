<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: login.php");
        exit;
    }

    // Fetch user with role
    $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_username, $hashed_password, $full_name, $email, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['last_activity'] = time();
            
            // Redirect based on role
            switch ($role) {
                case 'admin':
                    header("Location: index.php");
                    break;
                case 'teacher':
                    header("Location: teacher_dashboard.php");
                    break;
                case 'staff':
                    header("Location: staff_dashboard.php");
                    break;
                default:
                    header("Location: index.php");
            }
            exit;
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: login.php");
        exit;
    }

    $stmt->close();
    require_once 'functions/activity_logger.php';
logActivity($conn, "Login Successful", "Role: {$_SESSION['role']}", "login", "fas fa-sign-in-alt");
// After validation fails
logActivity($conn, "Attendance Failed", "Invalid data submitted", "system", "fas fa-exclamation-triangle");
}
$conn->close();
?>