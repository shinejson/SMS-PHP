<?php
require_once 'config.php';
require_once 'session.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    $sql = "SELECT * FROM remarks ORDER BY min_mark ASC";
    $result = $conn->query($sql);
    
    $remarks = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $remarks[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'remarks' => $remarks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching remarks: ' . $e->getMessage()
    ]);
}

$conn->close();
?>