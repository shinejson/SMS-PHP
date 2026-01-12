<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $academicYearId = $_GET['academic_year_id'] ?? '';
    $termId = $_GET['term_id'] ?? '';
    $classId = $_GET['class_id'] ?? '';

    error_log("Received parameters: academic_year_id=$academicYearId, term_id=$termId, class_id=$classId");

    if (empty($academicYearId)) {
        throw new Exception('Academic year ID is required');
    }

    // Get the academic year name for filtering
    $yearStmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
    if (!$yearStmt) {
        throw new Exception('Failed to prepare academic year query: ' . $conn->error);
    }
    
    $yearStmt->bind_param("i", $academicYearId);
    $yearStmt->execute();
    $yearResult = $yearStmt->get_result();
    
    if ($yearResult->num_rows === 0) {
        throw new Exception('Academic year not found');
    }
    
    $academicYear = $yearResult->fetch_assoc()['year_name'];
    error_log("Found academic year: $academicYear");

    // Check if students table exists and what columns it has
    $tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
    $studentsTableExists = ($tableCheck && $tableCheck->num_rows > 0);
    
    if (!$studentsTableExists) {
        // Try singular form
        $tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
        $studentTableExists = ($tableCheck && $tableCheck->num_rows > 0);
        $tableName = $studentTableExists ? 'students' : null;
    } else {
        $tableName = 'students';
    }
    
    if (!$tableName) {
        throw new Exception('Neither students nor student table found');
    }
    
    error_log("Using table: $tableName");

    // Build query based on filters - using created_at month for enrollment data
    $query = "SELECT 
                MONTH(created_at) as month,
                COUNT(*) as enrollment_count
              FROM $tableName 
              WHERE status = 'active'";
    
    $params = [];
    $paramTypes = "";

    // Filter by academic year (using created_at year)
    if (!empty($academicYear)) {
        // Extract year from academic year string (e.g., "2023-2024" -> check if created_at is in 2023)
        $startYear = explode('-', $academicYear)[0];
        $query .= " AND YEAR(created_at) = ?";
        $params[] = (int)$startYear;
        $paramTypes .= "i";
        error_log("Filtering by year: $startYear");
    }

    // Filter by class
    if (!empty($classId) && $classId !== 'all') {
        $query .= " AND class_id = ?";
        $params[] = (int)$classId;
        $paramTypes .= "i";
        error_log("Filtering by class_id: $classId");
    }

    $query .= " GROUP BY MONTH(created_at) 
                ORDER BY month ASC";

    error_log("Final query: $query");
    error_log("Parameters: " . implode(', ', $params));

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $monthlyData = [];
    while ($row = $result->fetch_assoc()) {
        $monthlyData[$row['month']] = (int)$row['enrollment_count'];
    }
    
    error_log("Monthly data: " . json_encode($monthlyData));

    // Prepare data for chart (12 months)
    $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $values = [];

    for ($month = 1; $month <= 12; $month++) {
        $values[] = $monthlyData[$month] ?? 0;
    }

    // Get total students for selected filters
    $totalQuery = "SELECT COUNT(*) as total FROM $tableName WHERE status = 'active'";
    $totalParams = [];
    $totalTypes = "";
    
    if (!empty($academicYear)) {
        $totalQuery .= " AND YEAR(created_at) = ?";
        $totalParams[] = (int)$startYear;
        $totalTypes .= "i";
    }

    if (!empty($classId) && $classId !== 'all') {
        $totalQuery .= " AND class_id = ?";
        $totalParams[] = (int)$classId;
        $totalTypes .= "i";
    }

    $totalStmt = $conn->prepare($totalQuery);
    if (!$totalStmt) {
        throw new Exception('Failed to prepare total query: ' . $conn->error);
    }
    
    if (!empty($totalParams)) {
        $totalStmt->bind_param($totalTypes, ...$totalParams);
    }
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalData = $totalResult->fetch_assoc();

    $data = [
        'success' => true,
        'labels' => $labels,
        'values' => $values,
        'total_enrollments' => (int)($totalData['total'] ?? 0),
        'filters' => [
            'academic_year' => $academicYear,
            'class_id' => $classId,
            'table_used' => $tableName
        ]
    ];

    error_log("Final response: " . json_encode($data));
    echo json_encode($data);

} catch (Exception $e) {
    error_log("Enrollment data error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>