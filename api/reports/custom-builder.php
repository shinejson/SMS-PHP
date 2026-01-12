<?php
header('Content-Type: application/json');
require_once '../../config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Get parameters
    $academicYear = $_GET['academicYear'] ?? '';
    $term = $_GET['term'] ?? '';
    $class = $_GET['class'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    $groupBy = $_GET['groupBy'] ?? 'student'; // student, class, term, payment_type, date
    $includeDetails = $_GET['includeDetails'] ?? 'true';
    
    // Build base query based on grouping
    $query = buildQueryForGrouping($groupBy, $academicYear, $term, $class, $dateFrom, $dateTo);
    
    $stmt = $conn->prepare($query['sql']);
    
    if (!empty($query['params'])) {
        $stmt->bind_param($query['types'], ...$query['params']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $grandTotal = 0;
    $totalTransactions = 0;
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $grandTotal += $row['total_amount'];
        $totalTransactions += $row['transaction_count'];
    }
    
    // Get details if requested
    $details = [];
    if ($includeDetails === 'true' && !empty($data)) {
        $details = getPaymentDetails($conn, $academicYear, $term, $class, $dateFrom, $dateTo);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'details' => $details,
        'summary' => [
            'group_by' => $groupBy,
            'total_amount' => $grandTotal,
            'total_transactions' => $totalTransactions,
            'record_count' => count($data)
        ],
        'filters' => [
            'academic_year' => $academicYear,
            'term' => $term,
            'class' => $class,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate custom report',
        'message' => $e->getMessage()
    ]);
}

function buildQueryForGrouping($groupBy, $academicYear, $term, $class, $dateFrom, $dateTo) {
    $params = [];
    $types = "";
    $whereConditions = ["1=1"];
    
    // Add filter conditions
    if (!empty($academicYear)) {
        $whereConditions[] = "p.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $whereConditions[] = "p.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $whereConditions[] = "s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "p.payment_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "p.payment_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    switch($groupBy) {
        case 'student':
            $sql = "SELECT 
                        s.student_id,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        c.class_name,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        SUM(CASE WHEN p.status = 'Paid' THEN p.amount ELSE 0 END) as paid_amount,
                        SUM(CASE WHEN p.status = 'Pending' THEN p.amount ELSE 0 END) as pending_amount,
                        GROUP_CONCAT(DISTINCT p.payment_type SEPARATOR ', ') as payment_types,
                        MIN(p.payment_date) as first_payment_date,
                        MAX(p.payment_date) as last_payment_date,
                        ay.year_name as academic_year,
                        t.term_name
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    LEFT JOIN classes c ON s.class_id = c.id
                    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
                    LEFT JOIN terms t ON p.term_id = t.id
                    WHERE {$whereClause}
                    GROUP BY s.id, s.student_id, student_name, c.class_name, ay.year_name, t.term_name
                    ORDER BY total_amount DESC";
            break;
            
        case 'class':
            $sql = "SELECT 
                        c.class_name,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment,
                        SUM(CASE WHEN p.status = 'Paid' THEN p.amount ELSE 0 END) as paid_amount,
                        SUM(CASE WHEN p.status = 'Pending' THEN p.amount ELSE 0 END) as pending_amount,
                        ay.year_name as academic_year,
                        t.term_name
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    INNER JOIN classes c ON s.class_id = c.id
                    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
                    LEFT JOIN terms t ON p.term_id = t.id
                    WHERE {$whereClause}
                    GROUP BY c.id, c.class_name, ay.year_name, t.term_name
                    ORDER BY total_amount DESC";
            break;
            
        case 'term':
            $sql = "SELECT 
                        t.term_name,
                        ay.year_name as academic_year,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment,
                        SUM(CASE WHEN p.status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
                        SUM(CASE WHEN p.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                        MIN(p.payment_date) as period_start,
                        MAX(p.payment_date) as period_end
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    INNER JOIN terms t ON p.term_id = t.id
                    INNER JOIN academic_years ay ON p.academic_year_id = ay.id
                    WHERE {$whereClause}
                    GROUP BY t.id, t.term_name, ay.id, ay.year_name
                    ORDER BY ay.year_name DESC, t.term_name";
            break;
            
        case 'payment_type':
            $sql = "SELECT 
                        p.payment_type,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment,
                        MIN(p.amount) as min_payment,
                        MAX(p.amount) as max_payment,
                        pm.name as payment_method,
                        ay.year_name as academic_year,
                        t.term_name
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
                    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
                    LEFT JOIN terms t ON p.term_id = t.id
                    WHERE {$whereClause}
                    GROUP BY p.payment_type, pm.name, ay.year_name, t.term_name
                    ORDER BY total_amount DESC";
            break;
            
        case 'date':
            $sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m-%d') as payment_date,
                        DATE_FORMAT(p.payment_date, '%W') as day_name,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment,
                        GROUP_CONCAT(DISTINCT p.payment_type SEPARATOR ', ') as payment_types,
                        GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as collectors
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    LEFT JOIN users u ON p.received_by = u.id
                    WHERE {$whereClause}
                    GROUP BY payment_date, day_name
                    ORDER BY payment_date DESC";
            break;
            
        case 'payment_method':
            $sql = "SELECT 
                        pm.name as payment_method,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment,
                        ay.year_name as academic_year,
                        t.term_name
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    INNER JOIN payment_methods pm ON p.payment_method_id = pm.id
                    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
                    LEFT JOIN terms t ON p.term_id = t.id
                    WHERE {$whereClause}
                    GROUP BY pm.id, pm.name, ay.year_name, t.term_name
                    ORDER BY total_amount DESC";
            break;
            
        default:
            $sql = "SELECT 
                        'Summary' as category,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(p.id) as transaction_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_payment
                    FROM payments p
                    INNER JOIN students s ON p.student_id = s.id
                    WHERE {$whereClause}";
    }
    
    return [
        'sql' => $sql,
        'params' => $params,
        'types' => $types
    ];
}

function getPaymentDetails($conn, $academicYear, $term, $class, $dateFrom, $dateTo) {
    $whereConditions = ["1=1"];
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $whereConditions[] = "p.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $whereConditions[] = "p.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($class)) {
        $whereConditions[] = "s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "p.payment_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "p.payment_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    $query = "SELECT 
                p.receipt_no,
                p.payment_date,
                TIME_FORMAT(p.created_at, '%h:%i:%s %p') as payment_time,
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.class_name,
                p.payment_type,
                p.amount,
                pm.name as payment_method,
                p.status,
                p.description,
                u.full_name as received_by,
                t.term_name,
                ay.year_name as academic_year
              FROM payments p
              INNER JOIN students s ON p.student_id = s.id
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
              LEFT JOIN users u ON p.received_by = u.id
              LEFT JOIN terms t ON p.term_id = t.id
              LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
              WHERE {$whereClause}
              ORDER BY p.payment_date DESC, p.created_at DESC
              LIMIT 1000";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = $row;
    }
    
    return $details;
}
?>