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
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    // Revenue by payment type
    $typeQuery = "SELECT 
                    p.payment_type,
                    COUNT(*) as transaction_count,
                    SUM(p.amount) as total_amount,
                    AVG(p.amount) as avg_amount
                  FROM payments p
                  WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $typeQuery .= " AND p.academic_year_id = ?";
        $params[] = $academicYear;
        $types .= "i";
    }
    
    if (!empty($term)) {
        $typeQuery .= " AND p.term_id = ?";
        $params[] = $term;
        $types .= "i";
    }
    
    if (!empty($dateFrom)) {
        $typeQuery .= " AND p.payment_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    
    if (!empty($dateTo)) {
        $typeQuery .= " AND p.payment_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    
    $typeQuery .= " GROUP BY p.payment_type ORDER BY total_amount DESC";
    
    $stmt = $conn->prepare($typeQuery);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $typeResult = $stmt->get_result();
    
    $byType = [];
    $grandTotal = 0;
    
    while ($row = $typeResult->fetch_assoc()) {
        $byType[] = $row;
        $grandTotal += $row['total_amount'];
    }
    
    // Revenue by payment method
    $methodQuery = "SELECT 
                      pm.name as payment_method,
                      COUNT(*) as transaction_count,
                      SUM(p.amount) as total_amount
                    FROM payments p
                    INNER JOIN payment_methods pm ON p.payment_method_id = pm.id
                    WHERE 1=1";
    
    $params2 = [];
    $types2 = "";
    
    if (!empty($academicYear)) {
        $methodQuery .= " AND p.academic_year_id = ?";
        $params2[] = $academicYear;
        $types2 .= "i";
    }
    
    if (!empty($term)) {
        $methodQuery .= " AND p.term_id = ?";
        $params2[] = $term;
        $types2 .= "i";
    }
    
    if (!empty($dateFrom)) {
        $methodQuery .= " AND p.payment_date >= ?";
        $params2[] = $dateFrom;
        $types2 .= "s";
    }
    
    if (!empty($dateTo)) {
        $methodQuery .= " AND p.payment_date <= ?";
        $params2[] = $dateTo;
        $types2 .= "s";
    }
    
    $methodQuery .= " GROUP BY pm.name ORDER BY total_amount DESC";
    
    $stmt2 = $conn->prepare($methodQuery);
    
    if (!empty($params2)) {
        $stmt2->bind_param($types2, ...$params2);
    }
    
    $stmt2->execute();
    $methodResult = $stmt2->get_result();
    
    $byMethod = [];
    while ($row = $methodResult->fetch_assoc()) {
        $byMethod[] = $row;
    }
    
    // Monthly revenue trend
    $trendQuery = "SELECT 
                     DATE_FORMAT(payment_date, '%Y-%m') as month,
                     COUNT(*) as transaction_count,
                     SUM(amount) as total_amount
                   FROM payments
                   WHERE 1=1";
    
    $params3 = [];
    $types3 = "";
    
    if (!empty($academicYear)) {
        $trendQuery .= " AND academic_year_id = ?";
        $params3[] = $academicYear;
        $types3 .= "i";
    }
    
    if (!empty($dateFrom)) {
        $trendQuery .= " AND payment_date >= ?";
        $params3[] = $dateFrom;
        $types3 .= "s";
    }
    
    if (!empty($dateTo)) {
        $trendQuery .= " AND payment_date <= ?";
        $params3[] = $dateTo;
        $types3 .= "s";
    }
    
    $trendQuery .= " GROUP BY month ORDER BY month";
    
    $stmt3 = $conn->prepare($trendQuery);
    
    if (!empty($params3)) {
        $stmt3->bind_param($types3, ...$params3);
    }
    
    $stmt3->execute();
    $trendResult = $stmt3->get_result();
    
    $trend = [];
    while ($row = $trendResult->fetch_assoc()) {
        $trend[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'by_type' => $byType,
        'by_method' => $byMethod,
        'monthly_trend' => $trend,
        'grand_total' => $grandTotal
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch revenue analysis',
        'message' => $e->getMessage()
    ]);
}
?>