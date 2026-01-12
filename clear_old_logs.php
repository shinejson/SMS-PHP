<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'functions/activity_logger.php';

checkAccess(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Delete logs older than 90 days
    $sql = "DELETE FROM activities WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute()) {
        $affected_rows = $conn->affected_rows;
        
        // Log this action using the corrected function
        logActivity($conn, 'Old Logs Cleared', "Cleared $affected_rows activity logs older than 90 days", 'system', 'fas fa-trash', $_SESSION['user_id']);
        
        echo json_encode(['success' => true, 'message' => "Cleared $affected_rows old logs"]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}