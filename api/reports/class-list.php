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
    $academicYear = $_GET['academicYear'] ?? '';
    
    $query = "SELECT 
                c.id,
                c.class_name,
                c.academic_year,
                c.description,
                CONCAT(t.first_name, ' ', t.last_name) as class_teacher,
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT CASE WHEN s.gender = 'Male' THEN s.id END) as male_students,
                COUNT(DISTINCT CASE WHEN s.gender = 'Female' THEN s.id END) as female_students,
                c.created_at
              FROM classes c
              LEFT JOIN teachers t ON c.class_teacher_id = t.id
              LEFT JOIN students s ON c.id = s.class_id AND s.status = 'Active'
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $query .= " AND c.academic_year = (SELECT year_name FROM academic_years WHERE id = ?)";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    $query .= " GROUP BY c.id, c.class_name, c.academic_year, c.description, 
                         t.first_name, t.last_name, c.created_at
                ORDER BY c.class_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $classes,
        'count' => count($classes)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch class list',
        'message' => $e->getMessage()
    ]);
}
?>