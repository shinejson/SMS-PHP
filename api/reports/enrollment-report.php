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
    $class = $_GET['class'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    $query = "SELECT 
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.gender,
                s.dob,
                TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) as age,
                c.class_name,
                s.parent_name,
                s.parent_contact,
                s.status,
                s.class_status,
                ay.year_name as academic_year,
                DATE_FORMAT(s.created_at, '%Y-%m-%d') as enrollment_date
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
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
        $query .= " AND DATE(s.created_at) >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $query .= " AND DATE(s.created_at) <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.created_at DESC, student_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $enrollments = [];
    $stats = [
        'total' => 0,
        'male' => 0,
        'female' => 0,
        'active' => 0,
        'inactive' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $enrollments[] = $row;
        $stats['total']++;
        
        if ($row['gender'] == 'Male') $stats['male']++;
        if ($row['gender'] == 'Female') $stats['female']++;
        if ($row['status'] == 'Active') $stats['active']++;
        if ($row['status'] == 'Inactive') $stats['inactive']++;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $enrollments,
        'count' => count($enrollments),
        'statistics' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch enrollment report',
        'message' => $e->getMessage()
    ]);
}
?>