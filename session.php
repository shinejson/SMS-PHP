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
// session.php - Enhanced session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class SessionManager {
    const TIMEOUT = 1800; // 30 minutes
    
    public static function checkTimeout() {
        if (isset($_SESSION['user_id'])) {
            if (!isset($_SESSION['LAST_ACTIVITY'])) {
                $_SESSION['LAST_ACTIVITY'] = time();
            }
            
            if (time() - $_SESSION['LAST_ACTIVITY'] > self::TIMEOUT) {
                self::destroy();
                header('Location: login.php?reason=timeout');
                exit();
            }
            
            $_SESSION['LAST_ACTIVITY'] = time();
        }
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', 0, '/');
    }
    
    public static function isExpired() {
        return isset($_SESSION['LAST_ACTIVITY']) && 
               (time() - $_SESSION['LAST_ACTIVITY'] > self::TIMEOUT);
    }
}

// Check session timeout on every request
SessionManager::checkTimeout();
require_once 'functions/activity_logger.php';
// After setting session
logActivity($conn, "User Login", "IP: {$_SERVER['REMOTE_ADDR']}", "login", "fas fa-sign-in-alt");
?>
