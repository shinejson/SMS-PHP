<?php
session_start();
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log the received data
error_log("Delete Invoice Request: " . print_r($input, true));
error_log("Invoice ID: " . ($input['invoice_id'] ?? 'NOT SET'));

$invoice_id = filter_var($input['invoice_id'] ?? null, FILTER_VALIDATE_INT);

if (!$invoice_id) {
    error_log("Invalid invoice ID received");
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

error_log("Processing invoice ID: " . $invoice_id);

try {
    // Verify invoice exists and get details for logging
    $check_sql = "SELECT invoice_number, status FROM invoices WHERE id = ? AND status != 'deleted'";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("i", $invoice_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Invoice not found or already deleted: " . $invoice_id);
        echo json_encode(['success' => false, 'message' => 'Invoice not found or already deleted']);
        exit();
    }

    $invoice = $result->fetch_assoc();
    $check_stmt->close();

    error_log("Found invoice: " . $invoice['invoice_number'] . " with status: " . $invoice['status']);

    // Check if invoice can be deleted (only unpaid/overdue invoices can be deleted)
    if ($invoice['status'] === 'paid') {
        error_log("Cannot delete paid invoice: " . $invoice['invoice_number']);
        echo json_encode(['success' => false, 'message' => 'Cannot delete paid invoices. Please cancel instead.']);
        exit();
    }

    // Soft delete (update status to 'deleted')
    $sql = "UPDATE invoices SET status = 'deleted', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $invoice_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        error_log("Update executed. Affected rows: " . $affected_rows);
        
        if ($affected_rows > 0) {
            // Log the action
            logAction($_SESSION['user_id'], 'DELETE_INVOICE', 'Deleted invoice: ' . $invoice['invoice_number']);
            
            error_log("Invoice successfully deleted: " . $invoice['invoice_number']);
            echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
        } else {
            error_log("No rows affected - invoice may already be deleted");
            echo json_encode(['success' => false, 'message' => 'No changes made - invoice may already be deleted']);
        }
    } else {
        error_log("Execute failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Error deleting invoice: ' . $conn->error]);
    }

    $stmt->close();
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
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