<?php
require_once 'config.php';
require_once 'session.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid CSRF token. Please refresh the page and try again.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_remarks') {
    try {
        // Validate that remarks data exists
        if (!isset($_POST['remarks']) || !is_array($_POST['remarks'])) {
            throw new Exception("No grading data received");
        }
        
        $conn->begin_transaction();
        
        // DEBUG: Check if table exists and has data
        $checkTable = $conn->query("SHOW TABLES LIKE 'remarks'");
        if ($checkTable->num_rows === 0) {
            throw new Exception("Remarks table does not exist");
        }
        
        $checkData = $conn->query("SELECT COUNT(*) as count FROM remarks");
        $row = $checkData->fetch_assoc();
        error_log("Current remarks count: " . $row['count']);
        
        // Clear existing remarks - use TRUNCATE for better performance
        $deleteResult = $conn->query("TRUNCATE TABLE remarks");
        if (!$deleteResult) {
            // If TRUNCATE fails (due to foreign key constraints), try DELETE
            $deleteResult = $conn->query("DELETE FROM remarks");
            if (!$deleteResult) {
                throw new Exception("Failed to clear existing grades: " . $conn->error);
            }
        }
        
        // Prepare insert statement
        $insertStmt = $conn->prepare("INSERT INTO remarks (min_mark, max_mark, grade, remark) VALUES (?, ?, ?, ?)");
        if (!$insertStmt) {
            throw new Exception("Failed to prepare insert statement: " . $conn->error);
        }
        
        $insertedCount = 0;
        foreach ($_POST['remarks'] as $remarkData) {
            // Validate and sanitize data
            $min_mark = floatval($remarkData['min_mark'] ?? 0);
            $max_mark = floatval($remarkData['max_mark'] ?? 0);
            $grade = trim(strtoupper($remarkData['grade'] ?? ''));
            $remark_text = trim($remarkData['remark'] ?? '');
            
            // Validate data
            if ($min_mark < 0 || $max_mark > 100 || $min_mark > $max_mark || empty($grade) || empty($remark_text)) {
                throw new Exception("Invalid data in grading system: Min=$min_mark, Max=$max_mark, Grade='$grade', Remark='$remark_text'");
            }
            
            $insertStmt->bind_param("ddss", $min_mark, $max_mark, $grade, $remark_text);
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to insert grade: " . $insertStmt->error);
            }
            $insertedCount++;
        }
        
        $conn->commit();
        $insertStmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => "Grading system updated successfully! $insertedCount grade ranges saved."
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error saving remarks: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Error saving grading system: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method or missing action'
    ]);
}

$conn->close();
?>