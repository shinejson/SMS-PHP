<?php
require_once 'config.php';

// Always return JSON
header('Content-Type: application/json');

// Disable PHP error output to browser
ini_set('display_errors', 0);
error_reporting(E_ALL);

/**
 * JSON response helper
 */
function jsonResponse($success, $dataOrMessage, $extra = []) {
    if ($success) {
        echo json_encode(array_merge([
            'success' => true,
            'data'    => $dataOrMessage
        ], $extra));
    } else {
        echo json_encode([
            'success' => false,
            'error'   => $dataOrMessage
        ]);
    }
    exit;
}

try {
    // Validate ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        jsonResponse(false, 'Invalid billing ID.');
    }

    // Fetch billing record
    $sql = "SELECT * FROM billing WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonResponse(false, "Database error: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $billingResult = $stmt->get_result();

    if ($billingResult->num_rows === 0) {
        jsonResponse(false, 'Billing record not found.');
    }

    $billing = $billingResult->fetch_assoc();

    // Initialize fee_breakdown as empty array
    $billing['fee_breakdown'] = [];

    // If payment_type is Tuition, fetch tuition_details
    if (strtolower($billing['payment_type']) === 'tuition') {
        $detailsSql = "SELECT sub_fee_name AS name, sub_fee_amount AS amount 
                       FROM tuition_details 
                       WHERE billing_id = ?";
        $detailsStmt = $conn->prepare($detailsSql);
        if (!$detailsStmt) {
            jsonResponse(false, "Database error: " . $conn->error);
        }
        $detailsStmt->bind_param("i", $id);
        $detailsStmt->execute();
        $detailsResult = $detailsStmt->get_result();

        while ($row = $detailsResult->fetch_assoc()) {
            $billing['fee_breakdown'][] = [
                'name' => $row['name'],
                'amount' => floatval($row['amount']) // Ensure numeric type
            ];
        }
        $detailsStmt->close();
    }

    $stmt->close();
    jsonResponse(true, $billing);

} catch (Throwable $e) {
    error_log("Get Billing Error: " . $e->getMessage());
    jsonResponse(false, 'Server error occurred.');
}
?>