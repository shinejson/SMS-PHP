<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Get filter parameters
    $academicYear = $_GET['academicYear'] ?? '';
    $term = $_GET['term'] ?? '';
    $class = $_GET['class'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    // Build query
    $query = "SELECT 
                s.id,
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as full_name,
                s.first_name,
                s.last_name,
                c.class_name,
                s.gender,
                s.dob,
                s.status,
                s.parent_name,
                s.parent_contact,
                s.email,
                s.address,
                ay.year_name as academic_year,
                s.class_status,
                s.created_at
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add filters
    if (!empty($academicYear)) {
        $query .= " AND s.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $query .= " AND s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $query .= " AND s.created_at >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $query .= " AND s.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
        $types .= "s";
    }
    
    $query .= " ORDER BY s.first_name, s.last_name";
    
    // Prepare and execute
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $students,
        'count' => count($students)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch student list',
        'message' => $e->getMessage()
    ]);
}
?>