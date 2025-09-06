<?php
require_once 'config.php';
header('Content-Type: application/json');

// Check for required parameters
if (!isset($_GET['payment_type'], $_GET['class_id'], $_GET['term_id'])) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit();
}

$payment_type = $conn->real_escape_string($_GET['payment_type']);
$class_id = intval($_GET['class_id']);
$term_id = intval($_GET['term_id']);

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT amount FROM billing 
                       WHERE payment_type = ? 
                         AND class_id = ? 
                         AND term_id = ? 
                       LIMIT 1");
$stmt->bind_param("sii", $payment_type, $class_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "amount" => (float)$row['amount']
    ]);
} else {
    // Check if any record exists for debugging
    $debug = $conn->query("SELECT COUNT(*) AS count FROM billing 
                          WHERE payment_type = '$payment_type' 
                            AND class_id = $class_id 
                            AND term_id = $term_id");
    $debugData = $debug->fetch_assoc();
    
    echo json_encode([
        "success" => false,
        "error" => "No matching billing record found",
        "debug" => $debugData['count']
    ]);
}