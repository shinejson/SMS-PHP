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
    $term = $_GET['term'] ?? '';
    $class = $_GET['class'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    $query = "SELECT 
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.class_name,
                COUNT(a.id) as total_days,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN attendance a ON s.id = a.student_id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $query .= " AND a.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $query .= " AND a.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $query .= " AND s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $query .= " AND a.attendance_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $query .= " AND a.attendance_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $query .= " GROUP BY s.id, s.student_id, student_name, c.class_name
                ORDER BY student_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $attendance,
        'count' => count($attendance)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch attendance summary',
        'message' => $e->getMessage()
    ]);
}
?>