<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Store current URL
    $_SESSION['login_message'] = "Please login to continue";
    header('Location: login.php');
    exit();
}

// Session timeout (30 minutes)
$inactive = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();
    session_destroy();
    $_SESSION['login_message'] = "Session expired. Please login again";
    header('Location: login.php');
    exit();
}

$_SESSION['last_activity'] = time(); // Update activity timestamp

// Set username for display
$username = htmlspecialchars($_SESSION['username'] ?? 'User');

// Security headers (optional but recommended)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>