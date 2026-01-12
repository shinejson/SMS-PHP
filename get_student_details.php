<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT s.first_name, s.last_name, s.class_id, c.class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'class_id' => $student['class_id'],
            'class_name' => $student['class_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    
} catch (Exception $e) {
    error_log("Error getting student details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>