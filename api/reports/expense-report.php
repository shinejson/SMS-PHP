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
    
    // Note: This is a placeholder since there's no expense table in your database
    // You can modify this to fetch actual expense data if you add an expenses table
    
    $query = "SELECT 
                'Salary' as expense_type,
                'Teachers Salary Payment' as description,
                5000.00 as amount,
                CURDATE() as expense_date,
                'Approved' as status
              UNION ALL
              SELECT 
                'Utilities' as expense_type,
                'Electricity Bill' as description,
                500.00 as amount,
                CURDATE() as expense_date,
                'Paid' as status
              UNION ALL
              SELECT 
                'Supplies' as expense_type,
                'Office Supplies' as description,
                300.00 as amount,
                CURDATE() as expense_date,
                'Paid' as status
              UNION ALL
              SELECT 
                'Maintenance' as expense_type,
                'Building Repairs' as description,
                1500.00 as amount,
                CURDATE() as expense_date,
                'Pending' as status";
    
    // Add date filters if provided
    $params = [];
    $types = "";
    
    $result = $conn->query($query);
    
    $expenses = [];
    $totalAmount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
        $totalAmount += $row['amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $expenses,
        'count' => count($expenses),
        'total_amount' => $totalAmount,
        'message' => 'This is sample expense data. Add an expenses table to track actual expenses.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch expense report',
        'message' => $e->getMessage()
    ]);
}
?>