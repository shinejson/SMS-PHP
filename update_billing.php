<?php
require_once 'config.php';

// Always return JSON
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function jsonResponse($success, $message, $extra = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.');
    }

    // Validate required fields
    $required = ['billing_id', 'payment_type', 'term_id', 'academic_year', 'due_date', 'class_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(false, "Missing required field: $field");
        }
    }

    $billing_id = intval($_POST['billing_id']);
    $payment_type = trim($_POST['payment_type']);
    $amount = floatval($_POST['amount'] ?? 0);
    $term_id = intval($_POST['term_id']);
    $academic_year = trim($_POST['academic_year']);
    $due_date = trim($_POST['due_date']);
    $description = trim($_POST['description'] ?? '');
    $class_id = intval($_POST['class_id']);

    if ($billing_id <= 0) {
        jsonResponse(false, 'Invalid Billing ID.');
    }

    // Check for duplicate records
    $stmt = $conn->prepare("SELECT id FROM billing WHERE payment_type = ? AND class_id = ? AND id != ?");
    $stmt->bind_param("sii", $payment_type, $class_id, $billing_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(false, 'A billing record with this payment type and class already exists.');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update main billing record
        $stmt = $conn->prepare("
            UPDATE billing 
            SET payment_type = ?, amount = ?, term_id = ?, academic_year = ?, 
                due_date = ?, description = ?, class_id = ?
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param(
            "sdisssii",
            $payment_type, $amount, $term_id, $academic_year,
            $due_date, $description, $class_id, $billing_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to update billing record: ' . $stmt->error);
        }

        // Clear existing tuition details
        $deleteStmt = $conn->prepare("DELETE FROM tuition_details WHERE billing_id = ?");
        $deleteStmt->bind_param("i", $billing_id);
        $deleteStmt->execute();

        // Handle tuition sub-fees if applicable
        if ($payment_type === 'Tuition') {
            $subFeeNames = $_POST['sub_fee_name'] ?? [];
            $subFeeAmounts = $_POST['sub_fee_amount'] ?? [];

            if (count($subFeeNames) !== count($subFeeAmounts)) {
                throw new Exception('Mismatched sub-fee data');
            }

            $insertStmt = $conn->prepare("
                INSERT INTO tuition_details (billing_id, sub_fee_name, sub_fee_amount)
                VALUES (?, ?, ?)
            ");

            foreach ($subFeeNames as $i => $name) {
                $subName = trim($name);
                $subAmount = floatval($subFeeAmounts[$i] ?? 0);
                
                if ($subName !== '' && $subAmount > 0) {
                    $insertStmt->bind_param("isd", $billing_id, $subName, $subAmount);
                    if (!$insertStmt->execute()) {
                        throw new Exception('Failed to insert sub-fee: ' . $insertStmt->error);
                    }
                }
            }
        }

        $conn->commit();
        jsonResponse(true, 'Billing record updated successfully.');

    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, $e->getMessage());
    }

} catch (Throwable $e) {
    error_log("Update Billing Error: " . $e->getMessage());
    jsonResponse(false, 'Server error occurred.');
}