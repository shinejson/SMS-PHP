<?php
require_once 'config.php';
header('Content-Type: application/json');

// Enable error logging for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check for required parameters
if (!isset($_GET['payment_type'], $_GET['class_id'], $_GET['term_id'], $_GET['academic_year_id'])) {
    error_log("Missing parameters: " . json_encode($_GET));
    echo json_encode(["success" => false, "error" => "Missing parameters", "debug" => $_GET]);
    exit();
}

try {
    $payment_type = $conn->real_escape_string($_GET['payment_type']);
    $class_id = intval($_GET['class_id']);
    $term_id = intval($_GET['term_id']);
    $academic_year_id = intval($_GET['academic_year_id']);

    // Validate parameters
    if ($class_id <= 0 || $term_id <= 0 || $academic_year_id <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid parameters", "received" => [
            'class_id' => $class_id,
            'term_id' => $term_id,
            'academic_year_id' => $academic_year_id
        ]]);
        exit();
    }

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT amount FROM billing 
                           WHERE payment_type = ? 
                             AND class_id = ? 
                             AND term_id = ? 
                             AND academic_year_id = ?
                           LIMIT 1");
    $stmt->bind_param("siii", $payment_type, $class_id, $term_id, $academic_year_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            "success" => true,
            "amount" => (float)$row['amount'],
            "debug" => [
                "payment_type" => $payment_type,
                "class_id" => $class_id,
                "term_id" => $term_id,
                "academic_year_id" => $academic_year_id
            ]
        ]);
    } else {
        // Debug query to see what's available
        $debug_query = $conn->prepare("SELECT payment_type, class_id, term_id, academic_year_id, amount 
                                     FROM billing 
                                     WHERE class_id = ? 
                                     LIMIT 10");
        $debug_query->bind_param("i", $class_id);
        $debug_query->execute();
        $debug_result = $debug_query->get_result();
        
        $debug_data = [];
        while ($debug_row = $debug_result->fetch_assoc()) {
            $debug_data[] = $debug_row;
        }
        
        echo json_encode([
            "success" => false,
            "error" => "No matching billing record found",
            "debug" => [
                "requested" => [
                    "payment_type" => $payment_type,
                    "class_id" => $class_id,
                    "term_id" => $term_id,
                    "academic_year_id" => $academic_year_id
                ],
                "available_records" => $debug_data
            ]
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Billing Amount Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "error" => "Server error occurred",
        "message" => $e->getMessage()
    ]);
}
?>