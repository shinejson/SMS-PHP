<?php
require_once 'config.php';

// Always return JSON
header('Content-Type: application/json');

// Disable direct error output to browser
ini_set('display_errors', 0);
error_reporting(E_ALL);

/**
 * Send JSON response and stop execution
 */
function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

/**
 * Check if a billing record exists for the same payment type, class, term, and academic year (excluding current record)
 */
function billingRecordExists(mysqli $conn, string $payment_type, int $class_id, int $term_id, int $academic_year_id, int $exclude_id = 0): bool {
    $sql = "SELECT id FROM billing WHERE payment_type = ? AND class_id = ? AND term_id = ? AND academic_year_id = ? AND id != ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("siiii", $payment_type, $class_id, $term_id, $academic_year_id, $exclude_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// --- MAIN SCRIPT ---
try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.');
    }

    // Check if billing_id is provided
    if (empty($_POST['billing_id'])) {
        jsonResponse(false, 'Missing billing ID.');
    }

    // Required fields
    $required = ['payment_type', 'term_id', 'academic_year_id', 'due_date', 'class_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(false, "Missing required field: $field");
        }
    }

    // Sanitize input
    $billing_id      = intval($_POST['billing_id']);
    $payment_type    = trim($_POST['payment_type']);
    $amount          = floatval($_POST['amount'] ?? 0);
    $term_id         = intval($_POST['term_id']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $due_date        = trim($_POST['due_date']);
    $description     = trim($_POST['description'] ?? '');
    $class_id        = intval($_POST['class_id']);

    // Prevent duplicates (excluding current record)
    if (billingRecordExists($conn, $payment_type, $class_id, $term_id, $academic_year_id, $billing_id)) {
        jsonResponse(false, "A billing record with this payment type, class, term, and academic year already exists.");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update billing table
        $stmt = $conn->prepare("
            UPDATE billing 
            SET payment_type = ?, amount = ?, term_id = ?, academic_year_id = ?, 
                due_date = ?, description = ?, class_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("sdiissii", $payment_type, $amount, $term_id, $academic_year_id, $due_date, $description, $class_id, $billing_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update billing record: " . $stmt->error);
        }

        // If payment type is Tuition, handle tuition details
        if ($payment_type === 'Tuition' && !empty($_POST['sub_fee_name']) && !empty($_POST['sub_fee_amount'])) {
            // First, delete existing tuition details
            $deleteStmt = $conn->prepare("DELETE FROM tuition_details WHERE billing_id = ?");
            if (!$deleteStmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $deleteStmt->bind_param("i", $billing_id);
            if (!$deleteStmt->execute()) {
                throw new Exception("Failed to delete old tuition details: " . $deleteStmt->error);
            }

            // Insert new tuition details
            $names = $_POST['sub_fee_name'];
            $amounts = $_POST['sub_fee_amount'];

            $tuitionStmt = $conn->prepare("
                INSERT INTO tuition_details (billing_id, sub_fee_name, sub_fee_amount)
                VALUES (?, ?, ?)
            ");
            if (!$tuitionStmt) {
                throw new Exception("Database error: " . $conn->error);
            }

            foreach ($names as $i => $name) {
                $subName = trim($name);
                $subAmount = floatval($amounts[$i] ?? 0);
                if ($subName && $subAmount > 0) {
                    $tuitionStmt->bind_param("isd", $billing_id, $subName, $subAmount);
                    if (!$tuitionStmt->execute()) {
                        throw new Exception("Failed to insert tuition detail: " . $tuitionStmt->error);
                    }
                }
            }
            $tuitionStmt->close();
        } else {
            // If not tuition, delete any existing tuition details
            $deleteStmt = $conn->prepare("DELETE FROM tuition_details WHERE billing_id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $billing_id);
                $deleteStmt->execute();
                $deleteStmt->close();
            }
        }

        // Commit transaction
        $conn->commit();
        jsonResponse(true, "Billing record updated successfully.", ['id' => $billing_id]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Throwable $e) {
    error_log("Update Billing Error: " . $e->getMessage());
    jsonResponse(false, "Server error occurred: " . $e->getMessage());
}