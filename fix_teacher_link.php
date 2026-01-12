<?php
require_once 'config.php';
require_once 'session.php';
require_once 'access_control.php';

// Restrict to admin or staff only
checkAccess(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_teacher_links.php');
    exit();
}

// CSRF-like protection (optional, add token if needed)
$user_id = (int)($_POST['user_id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($user_id <= 0 || empty($full_name) || empty($email)) {
    header('Location: verify_teacher_links.php?error=invalid_data');
    exit();
}

// Split full name into first and last
$name_parts = explode(' ', $full_name, 2);
$first_name = $name_parts[0];
$last_name = $name_parts[1] ?? '';

// Generate teacher_id: TCH + zero-padded user_id
$teacher_code = 'TCH' . str_pad($user_id, 6, '0', STR_PAD_LEFT); // e.g., TCH000007

// Begin transaction
$conn->autocommit(false);
$conn->begin_transaction();

try {
    // 1. Check if already linked
    $check_sql = "SELECT id FROM teachers WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        throw new Exception('Teacher record already exists.');
    }
    $check_stmt->close();

    // 2. Insert new teacher record
    $insert_sql = "INSERT INTO teachers 
        (user_id, teacher_id, first_name, last_name, email, phone, specialization, status, created_at)
        VALUES (?, ?, ?, ?, ?, NULL, NULL, 'active', NOW())";

    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $insert_stmt->bind_param("issss", $user_id, $teacher_code, $first_name, $last_name, $email);
    if (!$insert_stmt->execute()) {
        throw new Exception('Insert failed: ' . $insert_stmt->error);
    }
    $insert_stmt->close();

    // 3. Commit
    $conn->commit();
    $conn->autocommit(true);

    // Success
    $success_msg = urlencode("Teacher record created: $teacher_code ($first_name $last_name)");
    header("Location: verify_teacher_links.php?success=$success_msg");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    error_log("fix_teacher_link.php ERROR (user_id=$user_id): " . $e->getMessage());

    $error_msg = urlencode($e->getMessage());
    header("Location: verify_teacher_links.php?error=$error_msg");
    exit();
}
?>