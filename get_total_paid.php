<?php
require_once 'config.php';
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_GET['student_id'], $_GET['payment_type'], $_GET['term_id'])) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        exit();
    }

    $student_id   = intval($_GET['student_id']);
    $payment_type = $conn->real_escape_string($_GET['payment_type']);
    $term_id      = intval($_GET['term_id']);

    if ($student_id <= 0 || $term_id <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid parameters"]);
        exit();
    }

    // Prepared statement with all 3 filters
    $stmt = $conn->prepare("
        SELECT SUM(amount) AS total_paid 
        FROM payments 
        WHERE student_id = ? AND payment_type = ? AND term_id = ?
    ");
    $stmt->bind_param("isi", $student_id, $payment_type, $term_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $totalPaid = floatval($row['total_paid'] ?? 0);
        echo json_encode([
            "success"    => true, 
            "total_paid" => $totalPaid,
            "student_id" => $student_id,
            "term_id"    => $term_id,
            "payment_type" => $payment_type
        ]);
    } else {
        echo json_encode([
            "success"    => true, 
            "total_paid" => 0,
            "student_id" => $student_id,
            "term_id"    => $term_id,
            "payment_type" => $payment_type
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Get Total Paid Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "error"   => "Server error occurred",
        "total_paid" => 0
    ]);
}
?>
