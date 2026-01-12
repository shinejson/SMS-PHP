<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

try {
    $academicYears = [];
    
    // Select data from the academic_years table
    $sql = "SELECT 
                id, 
                year_name,
                is_current,
                created_at
            FROM academic_years 
            ORDER BY year_name DESC";
    
    $result = $conn->query($sql);

    // If results are found, add them to the array
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $academicYears[] = $row;
        }
    }
    
    echo json_encode($academicYears);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
