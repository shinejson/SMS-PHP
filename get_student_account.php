<?php
// get_student_account.php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$payment_type = isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';

if ($student_id <= 0 || empty($payment_type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Query to get the account linked to this student and payment type
$sql = "SELECT sa.account_number, sa.current_balance, sa.account_type, sa.status
        FROM payment_accounts pa
        JOIN student_accounts sa ON pa.account_id = sa.id
        WHERE pa.student_id = ? 
        AND pa.payment_type = ? 
        AND pa.is_active = 1
        AND sa.status = 'active'
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $student_id, $payment_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $account = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'account_number' => $account['account_number'],
        'balance' => $account['current_balance'],
        'account_type' => $account['account_type'],
        'status' => $account['status']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No active account found for this payment type'
    ]);
}

$stmt->close();
$conn->close();
?>