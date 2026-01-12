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
    
    $query = "SELECT 
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.class_name,
                s.parent_name,
                s.parent_contact,
                b.payment_type,
                b.amount as total_fee,
                COALESCE(SUM(p.amount), 0) as amount_paid,
                (b.amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                b.due_date,
                CASE 
                    WHEN b.due_date < CURDATE() THEN 'Overdue'
                    WHEN b.due_date = CURDATE() THEN 'Due Today'
                    ELSE 'Pending'
                END as payment_status,
                t.term_name,
                ay.year_name as academic_year
              FROM students s
              INNER JOIN classes c ON s.class_id = c.id
              INNER JOIN billing b ON c.id = b.class_id
              LEFT JOIN academic_years ay ON b.academic_year_id = ay.id
              LEFT JOIN terms t ON b.term_id = t.id
              LEFT JOIN payments p ON s.id = p.student_id 
                  AND p.payment_type = b.payment_type
                  AND p.term_id = b.term_id
                  AND p.academic_year_id = b.academic_year_id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $query .= " AND b.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $query .= " AND b.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $query .= " AND s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    $query .= " GROUP BY s.id, s.student_id, student_name, c.class_name, 
                         s.parent_name, s.parent_contact, b.payment_type, 
                         b.amount, b.due_date, t.term_name, ay.year_name
                HAVING outstanding_amount > 0
                ORDER BY b.due_date ASC, student_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $outstanding = [];
    $totalOutstanding = 0;
    
    while ($row = $result->fetch_assoc()) {
        $outstanding[] = $row;
        $totalOutstanding += $row['outstanding_amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $outstanding,
        'count' => count($outstanding),
        'total_outstanding' => $totalOutstanding
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch outstanding fees',
        'message' => $e->getMessage()
    ]);
}
?>