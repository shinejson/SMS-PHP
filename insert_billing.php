<?php
require_once 'config.php';

// Always return JSON
header('Content-Type: application/json');

// Disable direct error output to browser (avoid HTML leaks)
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
 * Check if a billing record exists for the same payment type, class, term, and academic year
 */
function billingRecordExists(mysqli $conn, string $payment_type, int $class_id, int $term_id, int $academic_year_id): bool {
    $sql = "SELECT id FROM billing WHERE payment_type = ? AND class_id = ? AND term_id = ? AND academic_year_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("siii", $payment_type, $class_id, $term_id, $academic_year_id);
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

    // Required fields
    $required = ['payment_type', 'term_id', 'academic_year_id', 'due_date', 'class_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(false, "Missing required field: $field");
        }
    }

    // Sanitize input
    $payment_type   = trim($_POST['payment_type']);
    $amount         = floatval($_POST['amount'] ?? 0);
    $term_id        = intval($_POST['term_id']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $due_date       = trim($_POST['due_date']);
    $description    = trim($_POST['description'] ?? '');
    $class_id       = intval($_POST['class_id']);

    // Prevent duplicates - now checking all relevant fields
    if (billingRecordExists($conn, $payment_type, $class_id, $term_id, $academic_year_id)) {
        jsonResponse(false, "A billing record with this payment type, class, term, and academic year already exists.");
    }

    // Insert into billing table
    $stmt = $conn->prepare("
        INSERT INTO billing (payment_type, amount, term_id, academic_year_id, due_date, description, class_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        jsonResponse(false, "Database error: " . $conn->error);
    }

   $stmt->bind_param("sdiissi", $payment_type, $amount, $term_id, $academic_year_id, $due_date, $description, $class_id);

    if (!$stmt->execute()) {
        jsonResponse(false, "Failed to insert billing record: " . $stmt->error);
    }

    $billing_id = $stmt->insert_id;

    // If payment type is Tuition, insert into tuition_details
    if ($payment_type === 'Tuition' && !empty($_POST['sub_fee_name']) && !empty($_POST['sub_fee_amount'])) {
        $names = $_POST['sub_fee_name'];
        $amounts = $_POST['sub_fee_amount'];

        $tuitionStmt = $conn->prepare("
            INSERT INTO tuition_details (billing_id, sub_fee_name, sub_fee_amount)
            VALUES (?, ?, ?)
        ");
        if (!$tuitionStmt) {
            jsonResponse(false, "Database error: " . $conn->error);
        }

        foreach ($names as $i => $name) {
            $subName = trim($name);
            $subAmount = floatval($amounts[$i] ?? 0);
            if ($subName && $subAmount > 0) {
                $tuitionStmt->bind_param("isd", $billing_id, $subName, $subAmount);
                if (!$tuitionStmt->execute()) {
                    error_log("Failed to insert tuition detail: " . $tuitionStmt->error);
                }
            }
        }
        $tuitionStmt->close();
    }

    jsonResponse(true, "Billing record added successfully.", ['id' => $billing_id]);

} catch (Throwable $e) {
    error_log("Insert Billing Error: " . $e->getMessage());
    jsonResponse(false, "Server error occurred.");
}