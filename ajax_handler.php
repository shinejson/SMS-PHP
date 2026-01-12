<?php
// ajax_handler.php
require_once 'config.php';
require_once 'session.php';

if ($_POST['action'] === 'get_students' && isset($_POST['class_id'])) {
    $class_id = intval($_POST['class_id']);
    
    $sql = "SELECT id, first_name, last_name, student_id 
            FROM students 
            WHERE class_id = ? AND status = 'active' 
            ORDER BY first_name, last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($students);
    exit;
}
?>