<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';
require_once 'functions/activity_logger.php';

// Access control
checkAccess(['admin', 'teacher', 'staff']);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Error & Exception Handlers
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "PHP Error: $message in $file on line $line"]);
    exit;
});

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    exit;
});

error_log("=== ATTENDANCE ACTION STARTED ===");
error_log("Raw POST data: " . print_r($_POST, true));

// Detect action
$action = $_POST['action'] ?? null;
if (!$action) {
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true);
    $action = $inputData['action'] ?? null;
}
error_log("Action: " . ($action ?? 'not set'));

/**
 * Check if user has active teacher record and return user_id if yes
 */
function getTeacherIdFromUserId($conn, $user_id) {
    $sql = "SELECT user_id FROM teachers 
            WHERE user_id = ? 
            AND TRIM(LOWER(status)) = 'active'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (getTeacherIdFromUserId): " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        error_log("Found teacher_user_id: " . $teacher['user_id'] . " for user_id: " . $user_id);
        return (int)$teacher['user_id'];
    }
    
    error_log("No ACTIVE teacher record found for user_id: " . $user_id);
    return null;
}

/**
 * Resolve teacher_id (now user_id) for attendance
 */
function getAttendanceTeacherId($conn, $user_id, $user_role, $class_id = null) {
    error_log("getAttendanceTeacherId called - user_id: $user_id, role: $user_role, class_id: " . ($class_id ?? 'null'));
    
    // 1. Teacher: must have linked record, return their user_id
    if ($user_role === 'teacher') {
        $teacherUserId = getTeacherIdFromUserId($conn, $user_id);
        if ($teacherUserId === null) {
            throw new Exception('Teacher profile not found or inactive. Contact administrator.');
        }
        return $teacherUserId;
    }
    
    // 2. Admin or Staff
    if ($user_role === 'admin' || $user_role === 'staff') {
        // Try class teacher first (now class_teacher_id references teachers.user_id, i.e., users.id)
        if ($class_id) {
            $stmt = $conn->prepare("SELECT class_teacher_id FROM classes WHERE id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['class_teacher_id']) {
                    error_log("Using class teacher_user_id: " . $row['class_teacher_id']);
                    $stmt->close();
                    return (int)$row['class_teacher_id'];
                }
            }
            $stmt->close();
        }

        // Try admin's own teacher record (return their user_id if exists)
        $adminTeacherId = getTeacherIdFromUserId($conn, $user_id);
        if ($adminTeacherId !== null) {
            error_log("Using admin's teacher_user_id: " . $adminTeacherId);
            return $adminTeacherId;
        }

        // Fallback: any active teacher's user_id
        $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE TRIM(LOWER(status)) = 'active' ORDER BY user_id ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            error_log("Using fallback teacher_user_id: " . $row['user_id']);
            $stmt->close();
            return (int)$row['user_id'];
        }
        $stmt->close();

        throw new Exception('No active teacher available in the system.');
    }

    throw new Exception('Invalid role for attendance.');
}

try {

    // === DELETE ATTENDANCE ===
    if ($action === 'delete_attendance') {
        $id = (int)($_POST['id'] ?? $inputData['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid attendance ID.');

        $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ?");
        if (!$stmt) throw new Exception('DB prepare error: ' . $conn->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception('Delete failed: ' . $stmt->error);
        $stmt->close();

        // Log activity on success
        logActivity(
            $conn,
            "Attendance Record Deleted",
            "Attendance ID: $id",
            "delete",
            "fas fa-trash",
            $id  // related_id = attendance id
        );

        $response['success'] = true;
        $response['message'] = 'Attendance record deleted.';
    }

    // === MARK / SAVE ATTENDANCE ===
    elseif ($action === 'mark_attendance' || $action === 'save_attendance') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $attendanceDate = $_POST['attendance_date'] ?? null;
        $academicYearId = (int)($_POST['academic_year_id'] ?? 0);
        $termId = (int)($_POST['term_id'] ?? 0);
        $markedBy = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['role'] ?? '';
        $generalRemarks = trim($_POST['general_remarks'] ?? '');

        error_log("Input: class_id=$classId, date=$attendanceDate, year_id=$academicYearId, term_id=$termId, user_role=$userRole");

        // Validation
        if (!$classId || !$attendanceDate || !$academicYearId || !$termId) {
            throw new Exception('Class, date, academic year, and term are required.');
        }

        // Resolve teacher_id (now a user_id)
        $teacherId = getAttendanceTeacherId($conn, $markedBy, $userRole, $classId);
        if (!$teacherId) {
            throw new Exception('Failed to resolve teacher for attendance.');
        }
        error_log("Final teacher_id (user_id): $teacherId");

        // Attendance data
        $attendanceData = $_POST['attendance'] ?? [];
        if (!is_array($attendanceData) || empty($attendanceData)) {
            throw new Exception('No student attendance data received.');
        }

        $conn->autocommit(false);
        $conn->begin_transaction();

        $recordsProcessed = $recordsInserted = $recordsUpdated = 0;

        foreach ($attendanceData as $studentIdStr => $status) {
            $studentId = (int)$studentIdStr;
            $status = strtolower(trim($status));

            if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
                $status = 'absent'; // default
            }

            // Check existing record
            $checkSql = "SELECT id FROM attendance 
                         WHERE class_id = ? AND student_id = ? AND attendance_date = ? 
                         AND academic_year_id = ? AND term_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("iisii", $classId, $studentId, $attendanceDate, $academicYearId, $termId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // UPDATE
                $existing = $checkResult->fetch_assoc();
                $attendanceId = $existing['id'];

                $updateSql = "UPDATE attendance SET 
                    status = ?, 
                    time_in = CASE WHEN ? IN ('present', 'late') AND time_in IS NULL THEN CURRENT_TIME ELSE time_in END,
                    time_out = NULL,
                    remarks = ?,
                    teacher_id = ?,
                    marked_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("sssiii", $status, $status, $generalRemarks, $teacherId, $markedBy, $attendanceId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Update failed for student $studentId: " . $updateStmt->error);
                }
                $updateStmt->close();
                $recordsUpdated++;
            } else {
                // INSERT
                $insertSql = "INSERT INTO attendance 
                    (student_id, class_id, academic_year_id, term_id, teacher_id, 
                     attendance_date, status, time_in, time_out, remarks, marked_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 
                            CASE WHEN ? IN ('present', 'late') THEN CURRENT_TIME ELSE NULL END, 
                            NULL, ?, ?, CURRENT_TIMESTAMP)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("iiiiissssi",
                    $studentId, $classId, $academicYearId, $termId, $teacherId,
                    $attendanceDate, $status, $status, $generalRemarks, $markedBy
                );
                if (!$insertStmt->execute()) {
                    throw new Exception("Insert failed for student $studentId: " . $insertStmt->error);
                }
                $insertStmt->close();
                $recordsInserted++;
            }
            $checkStmt->close();
            $recordsProcessed++;
        }

        $conn->commit();
        $conn->autocommit(true);

        // Log activity on success
        logActivity(
            $conn,
            "Attendance Marked/Updated",
            "Class ID: $classId, Date: $attendanceDate, Processed: $recordsProcessed (New: $recordsInserted, Updated: $recordsUpdated)",
            "create",
            "fas fa-clipboard-check",
            $classId  // related_id = class_id
        );

        $response['success'] = true;
        $response['message'] = "Attendance saved! $recordsProcessed record(s) processed ($recordsInserted new, $recordsUpdated updated).";
        error_log("SUCCESS: " . $response['message']);
    }

    else {
        throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $ex) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    $response['success'] = false;
    $response['message'] = $ex->getMessage();
    error_log("ERROR: " . $ex->getMessage());
    error_log("Stack trace: " . $ex->getTraceAsString());
}

error_log("=== ATTENDANCE ACTION COMPLETED ===");
echo json_encode($response);
exit;
?>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      