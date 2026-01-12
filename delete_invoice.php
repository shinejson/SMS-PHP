<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // AJAX request - soft delete
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $invoice_id = filter_var($input['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$invoice_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
        exit();
    }
    
    // Verify invoice exists and get details for logging
    $check_sql = "SELECT invoice_number FROM invoices WHERE id = ? AND status != 'deleted'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $invoice_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    
    $invoice = $result->fetch_assoc();
    $check_stmt->close();
    
    // Soft delete (update status to 'deleted')
    $sql = "UPDATE invoices SET status = 'deleted', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    
    if ($stmt->execute()) {
        // Log the action
        logAction($_SESSION['user_id'], 'DELETE_INVOICE', 'Deleted invoice: ' . $invoice['invoice_number']);
        
        echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting invoice: ' . $conn->error]);
    }
    
    $stmt->close();
} else {
    // Regular form submission - redirect back
    $invoice_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$invoice_id) {
        $_SESSION['error_message'] = 'Invalid invoice ID';
        header('Location: invoice.php');
        exit();
    }
    
    // Verify invoice exists
    $check_sql = "SELECT invoice_number FROM invoices WHERE id = ? AND status != 'deleted'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $invoice_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Invoice not found';
        header('Location: invoice.php');
        exit();
    }
    
    $invoice = $result->fetch_assoc();
    $check_stmt->close();
    
    // Soft delete
    $sql = "UPDATE invoices SET status = 'deleted', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    
    if ($stmt->execute()) {
        // Log the action
        logAction($_SESSION['user_id'], 'DELETE_INVOICE', 'Deleted invoice: ' . $invoice['invoice_number']);
        
        $_SESSION['success_message'] = 'Invoice deleted successfully';
    } else {
        $_SESSION['error_message'] = 'Error deleting invoice: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: invoice.php');
    exit();
}

function logAction($user_id, $action, $details) {
    global $conn;
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $stmt->execute();
    $stmt->close();
}
?>