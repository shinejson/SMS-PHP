<?php
// get_assignment.php - Fixed JSON output
// NO OUTPUT before this point!

// Prevent any output
ob_start();

// Suppress all errors from displaying
ini_set('display_errors', 0);
error_reporting(0);

function getTeacherIdFromUserId($conn, $user_id) {
    if (empty($user_id)) return null;
    $sql = "SELECT id FROM teachers WHERE user_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return null;
}

function verifyAssignmentOwnership($conn, $assignment_id, $teacher_user_id) {
    $teacher_id = getTeacherIdFromUserId($conn, $teacher_user_id);
    if (!$teacher_id) return false;
    
    $sql = "SELECT id FROM assignments WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasAccess = $result->num_rows > 0;
    $stmt->close();
    return $hasAccess;
}

function getSubjectIdByName($conn, $subject_name) {
    if (empty($subject_name)) return 0;
    
    // Direct string comparison since subject is stored as text
    $sql = "SELECT id FROM subjects WHERE subject_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    
    $stmt->bind_param("s", $subject_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $stmt->close();
        return $id;
    }
    
    $stmt->close();
    return 0;
}

try {
    require_once 'config.php';
    require_once 'session.php';
    
    // Don't include files that might have output
    // require_once 'rbac.php';
    // require_once 'access_control.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        throw new Exception('Access denied');
    }

    $teacher_id = $_SESSION['user_id'];
    $is_admin = ($_SESSION['role'] === 'admin');

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid ID');
    }
    
    $assignment_id = (int)$_GET['id'];

    if (!$is_admin && !verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)) {
        throw new Exception('Access denied');
    }

    // Simple query - subject is just text, not a foreign key
    $sql = "SELECT 
                a.id,
                a.title,
                a.description,
                a.class_id,
                a.teacher_id,
                a.subject,
                a.academic_year,
                a.term_id,
                a.assignment_date,
                a.due_date,
                a.max_marks,
                a.assignment_type,
                a.instructions,
                a.status,
                a.attachment_path,
                t.user_id as teacher_user_id
            FROM assignments a
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Query failed');
    }

    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$row = $result->fetch_assoc()) {
        throw new Exception('Assignment not found');
    }
    
    $stmt->close();
    
    // Look up subject_id from subject name
    $subject_id = getSubjectIdByName($conn, $row['subject']);
    
    // Build clean response
    $response = [
        'id' => (int)$row['id'],
        'title' => $row['title'] ?? '',
        'description' => $row['description'] ?? '',
        'class_id' => (int)$row['class_id'],
        'teacher_id' => (int)($row['teacher_user_id'] ?? 0),
        'subject' => $row['subject'] ?? '',
        'subject_id' => $subject_id,
        'academic_year' => $row['academic_year'] ?? '',
        'term_id' => (int)($row['term_id'] ?? 0),
        'term' => (int)($row['term_id'] ?? 0),
        'assignment_date' => $row['assignment_date'] ?? '',
        'due_date' => $row['due_date'] ?? '',
        'max_marks' => (float)($row['max_marks'] ?? 0),
        'assignment_type' => $row['assignment_type'] ?? '',
        'instructions' => $row['instructions'] ?? '',
        'status' => $row['status'] ?? 'active',
        'attachment_path' => $row['attachment_path'] ?? ''
    ];
    
    // Clear any output buffer
    ob_end_clean();
    
    // Send clean JSON
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Clear buffer
    ob_end_clean();
    
    // Send error JSON
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(400);
    
    echo json_encode(['error' => $e->getMessage()]);
    exit;
    
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}