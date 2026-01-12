<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

try {
    $terms = [];
    
    // Fetch terms from the database
    $sql = "SELECT id, term_name FROM terms ORDER BY term_order ASC";
    
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $terms[] = $row;
        }
    }
    
    echo json_encode($terms);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
