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
                p.receipt_no,
                p.payment_date,
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.class_name,
                p.payment_type,
                p.amount,
                pm.name as payment_method,
                p.status,
                t.term_name,
                ay.year_name as academic_year,
                u.full_name as received_by
              FROM payments p
              INNER JOIN students s ON p.student_id = s.id
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
              LEFT JOIN terms t ON p.term_id = t.id
              LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
              LEFT JOIN users u ON p.received_by = u.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $query .= " AND p.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $query .= " AND p.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $query .= " AND s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $query .= " AND p.payment_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $query .= " AND p.payment_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $query .= " ORDER BY p.payment_date DESC, p.receipt_no";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    $totalAmount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
        $totalAmount += $row['amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments,
        'count' => count($payments),
        'total_amount' => $totalAmount
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch payment summary',
        'message' => $e->getMessage()
    ]);
}
?>