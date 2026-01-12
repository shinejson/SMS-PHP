<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $query = "SELECT 
                t.id,
                t.teacher_id,
                CONCAT(t.first_name, ' ', t.last_name) as full_name,
                t.first_name,
                t.last_name,
                t.email,
                t.phone,
                t.specialization,
                t.status,
                GROUP_CONCAT(DISTINCT c.class_name SEPARATOR ', ') as assigned_classes,
                COUNT(DISTINCT c.id) as total_classes,
                t.created_at
              FROM teachers t
              LEFT JOIN classes c ON t.id = c.class_teacher_id
              WHERE 1=1
              GROUP BY t.id, t.teacher_id, t.first_name, t.last_name, 
                       t.email, t.phone, t.specialization, t.status, t.created_at
              ORDER BY t.first_name, t.last_name";
    
    $result = $conn->query($query);
    
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $teachers,
        'count' => count($teachers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch teacher list',
        'message' => $e->getMessage()
    ]);
}
?>