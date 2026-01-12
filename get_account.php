<?php
// get_account.php - Fetch account details for editing
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$allowed_roles = ['admin', 'accountant', 'finance'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$account_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($account_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit();
}

// Get account details
$sql = "SELECT sa.*, 
               s.first_name, s.last_name, s.student_id as student_code,
               (SELECT GROUP_CONCAT(payment_type) FROM payment_accounts WHERE account_id = sa.id AND is_active = 1) as payment_types
        FROM student_accounts sa
        JOIN students s ON sa.student_id = s.id
        WHERE sa.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $account_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $account = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'id' => $account['id'],
        'account_number' => $account['account_number'],
        'student_id' => $account['student_id'],
        'student_name' => $account['first_name'] . ' ' . $account['last_name'],
        'student_code' => $account['student_code'],
        'account_type' => $account['account_type'],
        'current_balance' => $account['current_balance'],
        'status' => $account['status'],
        'notes' => $account['notes'],
        'payment_types' => $account['payment_types'] ? explode(',', $account['payment_types']) : []
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
}

$stmt->close();
$conn->close();
?>