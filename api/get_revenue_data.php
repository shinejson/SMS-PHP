<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

try {
    $academicYearId = $_GET['academic_year_id'] ?? '';
    $termId = $_GET['term_id'] ?? '';

    if (empty($academicYearId)) {
        throw new Exception('Academic year ID is required');
    }

    // Get the academic year name
    $yearStmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
    $yearStmt->bind_param("i", $academicYearId);
    $yearStmt->execute();
    $yearResult = $yearStmt->get_result();
    
    if ($yearResult->num_rows === 0) {
        throw new Exception('Academic year not found');
    }
    
    $academicYear = $yearResult->fetch_assoc()['year_name'];
    $startYear = explode('-', $academicYear)[0];

    // Sample revenue data - replace with your actual payments query
    // Assuming you have a payments table with amount and payment_date
    $revenueData = [
        'Tuition Fees' => 15000,
        'Donations' => 5000,
        'Events' => 3000,
        'Other' => 2000
    ];


    $revenueQuery = "SELECT 
                        payment_type,
                        SUM(amount) as total_amount
                     FROM payments 
                     WHERE YEAR(payment_date) = ?
                     GROUP BY payment_type";
    
    $stmt = $conn->prepare($revenueQuery);
    $stmt->bind_param("i", $startYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $revenueData = [];
    while ($row = $result->fetch_assoc()) {
        $revenueData[$row['payment_type']] = $row['total_amount'];
    }
   

    $data = [
        'labels' => array_keys($revenueData),
        'values' => array_values($revenueData),
        'total_revenue' => array_sum(array_values($revenueData))
    ];

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>