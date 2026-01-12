<?php
// Add these at the very top to catch any output
ob_start(); // Start output buffering

require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');

// Clear any previous output
ob_clean();

// Set header first
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get and validate parameters
$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$term_id = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
$academic_year_id = isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0;
$conduct = isset($_POST['conduct']) ? trim($_POST['conduct']) : '';
$attitude = isset($_POST['attitude']) ? trim($_POST['attitude']) : '';
$promoted_to = isset($_POST['promoted_to']) ? trim($_POST['promoted_to']) : '';
$teacher_remark = isset($_POST['teacher_remark']) ? trim($_POST['teacher_remark']) : '';

// Debug logging (remove in production)
error_log("Saving remarks for student: $student_id, term: $term_id, year: $academic_year_id");

if (!$student_id || !$term_id || !$academic_year_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Check if record exists
    $checkSql = "SELECT id FROM report_remarks WHERE student_id = ? AND term_id = ? AND academic_year_id = ?";
    $stmt = $conn->prepare($checkSql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iii", $student_id, $term_id, $academic_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $updateSql = "UPDATE report_remarks SET conduct = ?, attitude = ?, promoted_to = ?, teacher_remark = ?, updated_at = NOW() 
                      WHERE student_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($updateSql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssiii", $conduct, $attitude, $promoted_to, $teacher_remark, 
                         $student_id, $term_id, $academic_year_id);
    } else {
        // Insert new record
        $insertSql = "INSERT INTO report_remarks (student_id, term_id, academic_year_id, conduct, attitude, promoted_to, teacher_remark) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iiissss", $student_id, $term_id, $academic_year_id, 
                         $conduct, $attitude, $promoted_to, $teacher_remark);
    }
    
    if ($stmt->execute()) {
        $response = [
            'status' => 'success',
            'message' => 'Remarks saved successfully',
            'remarks' => [
                'conduct' => $conduct,
                'attitude' => $attitude,
                'promoted_to' => $promoted_to,
                'teacher_remark' => $teacher_remark
            ]
        ];
        echo json_encode($response);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error saving remarks: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering and send
ob_end_flush();
?>