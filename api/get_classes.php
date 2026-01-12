<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

try {
    $classes = [];
    
    // Select data from the classes table, joining with teachers
    // Use CONCAT to combine first_name and last_name for the full name
    $sql = "SELECT 
                c.id, 
                c.class_name,
                c.academic_year,
                c.description,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM classes c
            LEFT JOIN teachers t ON c.class_teacher_id = t.id
            ORDER BY c.class_name ASC";
    
    $result = $conn->query($sql);

    // If results are found, add them to the array
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
    
    echo json_encode($classes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
