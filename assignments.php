<?php
// assignments.php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access to teacher and admin only
if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}
checkAccess(['teacher', 'admin']);

$teacher_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

// For admin, they can view all assignments. For teacher, only their own.
if ($is_admin) {
    // Admin can view all assignments - no teacher profile check needed
} else {
    // Check if teacher profile is active
    $sql = "SELECT user_id FROM teachers WHERE user_id = ? AND TRIM(LOWER(status)) = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Your teacher profile is not active. Please contact administrator.";
        header("Location: login.php");
        exit();
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_assignment'])) {
        createAssignment($conn, $teacher_id, $is_admin);
        header("Location: assignments.php");
        exit();
    } elseif (isset($_POST['update_assignment'])) {
        updateAssignment($conn, $teacher_id, $is_admin);
        header("Location: assignments.php");
        exit();
    } elseif (isset($_POST['delete_assignment'])) {
        deleteAssignment($conn, $teacher_id, $is_admin);
        header("Location: assignments.php");
        exit();
    } elseif (isset($_POST['grade_assignment'])) {
        gradeAssignment($conn, $teacher_id, $is_admin);
        header("Location: assignments.php");
        exit();
    }
}

$filter_class = $_GET['class_id'] ?? '';
$filter_subject = $_GET['subject'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_teacher = $_GET['teacher_id'] ?? '';
$filter_academic_year = $_GET['academic_year'] ?? '';
$filter_term = $_GET['term'] ?? '';

// FIXED: Get data BEFORE using it
// ============================================
$classes = getAllClasses($conn, $teacher_id, $is_admin);
$subjects = getAllSubjects($conn);
$academic_years = getAllAcademicYears($conn);
$terms = getAllTerms($conn);
$teachers = $is_admin ? getAllTeachers($conn) : [];

// THEN get assignments with all filters
$assignments = getAssignments($conn, $teacher_id, $is_admin, $filter_class, $filter_subject, $filter_status, $filter_type, $filter_teacher, $filter_academic_year, $filter_term);

// Get statistics
$stats = getAssignmentStatistics($conn, $teacher_id, $is_admin);

if (!function_exists('showFlashMessage')) {
    function showFlashMessage() {
        if (!empty($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="flash-message">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ' . htmlspecialchars($_SESSION['error']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                  </div>';
            unset($_SESSION['error']);
        }
        if (!empty($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert" id="flash-message">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . htmlspecialchars($_SESSION['success']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                  </div>';
            unset($_SESSION['success']);
        }
        
        // Also show any form validation errors
        if (!empty($_SESSION['form_errors'])) {
            foreach ($_SESSION['form_errors'] as $error) {
                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ' . htmlspecialchars($error) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                      </div>';
            }
            unset($_SESSION['form_errors']);
        }
    }
}

function createAssignment($conn, $teacher_id, $is_admin) {
    // Log POST data for debugging (check server error logs)
    error_log("CREATE ASSIGNMENT - POST data: " . print_r($_POST, true));
    error_log("CREATE ASSIGNMENT - FILES data: " . print_r($_FILES, true));
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $academic_year = $_POST['academic_year'];
    $term_id = $_POST['term_id'];
    $assignment_date = $_POST['assignment_date'];
    $due_date = $_POST['due_date'];
    $max_marks = empty($_POST['max_marks']) ? 0 : (float)$_POST['max_marks'];
    $assignment_type = $_POST['assignment_type'];
    $instructions = trim($_POST['instructions']);
    $status = 'active';
    
    // Get user_id for assignment (users.id)
    $assigned_user_id = $is_admin ? ($_POST['teacher_id'] ?? '') : $teacher_id;
    
    // Map to teachers.id
    $assigned_teacher_id = getTeacherIdFromUserId($conn, $assigned_user_id);
    if (!$assigned_teacher_id) {
        $_SESSION['error'] = $is_admin ? "Invalid or inactive teacher selected." : "Your teacher profile is not active. Please contact administrator.";
        error_log("CREATE FAILED: Invalid teacher ID for user_id $assigned_user_id");
        return;
    }

    // Validate required fields
    if (empty($title) || empty($class_id) || empty($subject_id) || empty($academic_year) || empty($term_id) || empty($due_date)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        error_log("VALIDATION FAILED: Missing required fields");
        return;
    }

    // For teachers, verify they have access to the selected class
    if (!$is_admin) {
        if (!verifyTeacherClassAccess($conn, $teacher_id, $class_id)) {  // Still uses user_id for verification
            $_SESSION['error'] = "You don't have access to assign to this class.";
            error_log("ACCESS DENIED: Teacher $teacher_id cannot access class $class_id");
            return;
        }
    }

    // Get subject name from subject_id
    $subject_name = getSubjectName($conn, $subject_id);
    if (!$subject_name) {
        $_SESSION['error'] = "Invalid subject selected.";
        error_log("INVALID SUBJECT: Subject ID $subject_id not found");
        return;
    }

    // Log final data before insertion
    error_log("INSERTING - Title: $title, Class: $class_id, Teacher: $assigned_teacher_id (teachers.id), Subject: $subject_name, Year: $academic_year, Term: $term_id");

    // Updated SQL with academic_year and term_id columns
    $sql = "INSERT INTO assignments (title, description, class_id, teacher_id, subject, academic_year, term_id, assignment_date, due_date, max_marks, assignment_type, status, instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = "Database prepare error: " . $conn->error;
        error_log("PREPARE FAILED: " . $conn->error);
        return;
    }
    
    // FIXED: Correct bind types: s s i i s s i s s d s s s
    $stmt->bind_param("ssiississdsss", $title, $description, $class_id, $assigned_teacher_id, $subject_name, $academic_year, $term_id, $assignment_date, $due_date, $max_marks, $assignment_type, $status, $instructions);
    
    if ($stmt->execute()) {
        $assignment_id = $stmt->insert_id;
        error_log("INSERT SUCCESS: Assignment ID $assignment_id created");
        
        // Handle file upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
            $upload_result = handleAssignmentAttachment($conn, $assignment_id, $_FILES['attachment']);
            if (!$upload_result) {
                // Don't fail the whole operation, just log
                error_log("FILE UPLOAD FAILED for assignment $assignment_id");
            }
        }
        
        $_SESSION['success'] = "Assignment created successfully!";
    } else {
        $_SESSION['error'] = "Error creating assignment: " . $stmt->error;
        error_log("INSERT FAILED: " . $stmt->error);
    }
    $stmt->close();
}

function getTeacherIdFromUserId($conn, $user_id) {
    if (empty($user_id)) {
        return null;
    }
    $sql = "SELECT id FROM teachers WHERE user_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['id'];
        $stmt->close();
        return $teacher_id;
    }
    $stmt->close();
    return null;
}

function updateAssignment($conn, $teacher_id, $is_admin) {
    $assignment_id = $_POST['assignment_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $class_id = $_POST['class_id'];
    
    // Get subject name from subject_id
    $subject_id = $_POST['subject_id'];
    $subject = getSubjectName($conn, $subject_id);
    if (!$subject) {
        $_SESSION['error'] = "Invalid subject selected.";
        return;
    }
    
    $academic_year = $_POST['academic_year'];
    $term_id = $_POST['term_id'];  // Get term_id from form
    $assignment_date = $_POST['assignment_date'];
    $due_date = $_POST['due_date'];
    $max_marks = empty($_POST['max_marks']) ? 0 : (float)$_POST['max_marks'];
    $assignment_type = $_POST['assignment_type'];
    $instructions = trim($_POST['instructions']);
    $status = $_POST['status'];
    
    // Map to teachers.id (for admin changes)
    $assigned_user_id = $teacher_id;
    if ($is_admin && isset($_POST['teacher_id']) && !empty($_POST['teacher_id'])) {
        $assigned_user_id = $_POST['teacher_id'];
    }
    $assigned_teacher_id = getTeacherIdFromUserId($conn, $assigned_user_id);
    if ($is_admin && !$assigned_teacher_id) {
        $_SESSION['error'] = "Invalid or inactive teacher selected.";
        return;
    }
    // For non-admin, use their own (already validated at top)

    if (!$is_admin && !verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)) {
        $_SESSION['error'] = "You don't have permission to update this assignment.";
        return;
    }

    // FIXED: Use term_id column and correct bind types: s s i s s i s s d s s s i i (14 params)
    $sql = "UPDATE assignments SET title = ?, description = ?, class_id = ?, subject = ?, academic_year = ?, term_id = ?, assignment_date = ?, due_date = ?, max_marks = ?, assignment_type = ?, instructions = ?, status = ?, teacher_id = ?, updated_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = "Database prepare error: " . $conn->error;
        return;
    }
    $stmt->bind_param("ssississdsssii", $title, $description, $class_id, $subject, $academic_year, $term_id, $assignment_date, $due_date, $max_marks, $assignment_type, $instructions, $status, $assigned_teacher_id, $assignment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Assignment updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating assignment: " . $stmt->error;
        error_log("UPDATE FAILED: " . $stmt->error);
    }
    $stmt->close();
}

// Helper function to get subject name
function getSubjectName($conn, $subject_id) {
    $sql = "SELECT subject_name FROM subjects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['subject_name'];
    }
    
    return null;
}

function handleAssignmentAttachment($conn, $assignment_id, $file) {
    $upload_dir = 'uploads/assignments/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/jpeg', 'image/png', 'application/zip'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Allowed types: PDF, DOC, DOCX, TXT, JPG, PNG, ZIP";
        return false;
    }
    
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File too large. Maximum size is 10MB";
        return false;
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'assignment_' . $assignment_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update assignment with attachment path
        $sql = "UPDATE assignments SET attachment_path = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $upload_path, $assignment_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    
    return false;
}

function deleteAssignment($conn, $teacher_id, $is_admin) {
    $assignment_id = $_POST['assignment_id'];

    // Verify assignment belongs to teacher (unless admin)
    if (!$is_admin && !verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)) {
        $_SESSION['error'] = "You don't have permission to delete this assignment.";
        return;
    }

    // Check if there are submissions
    $check_sql = "SELECT COUNT(*) as submission_count FROM assignments_summary WHERE assignment_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $assignment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $submission_count = $result->fetch_assoc()['submission_count'];
    $check_stmt->close();

    if ($submission_count > 0) {
        $_SESSION['error'] = "Cannot delete assignment that has submissions. You can deactivate it instead.";
        return;
    }

    $sql = "DELETE FROM assignments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assignment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Assignment deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting assignment: " . $stmt->error;
        error_log("DELETE FAILED: " . $stmt->error);
    }
    $stmt->close();
}

function getAssignments($conn, $teacher_id, $is_admin, $class_id = '', $subject = '', $status = '', $type = '', $filter_teacher = '', $academic_year = '', $term = '') {
    if ($is_admin) {
        // Admin can see all assignments across all classes
        $sql = "SELECT a.*, c.class_name, CONCAT(u.full_name, ' ', u.username) as teacher_name,
                       (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
                       (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted') as pending_count
                FROM assignments a 
                LEFT JOIN classes c ON a.class_id = c.id 
                LEFT JOIN teachers t ON a.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if (!empty($class_id)) {
            $sql .= " AND a.class_id = ?";
            $params[] = $class_id;
            $types .= "i";
        }
        
        if (!empty($subject)) {
            $sql .= " AND a.subject = ?";
            $params[] = $subject;
            $types .= "s";
        }
        
        if (!empty($status)) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($type)) {
            $sql .= " AND a.assignment_type = ?";
            $params[] = $type;
            $types .= "s";
        }
        
        if (!empty($filter_teacher)) {
            $sql .= " AND a.teacher_id = ?";
            $params[] = $filter_teacher;
            $types .= "i";
        }
        
        if (!empty($academic_year)) {
            $sql .= " AND a.academic_year = ?";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        if (!empty($term)) {
            $sql .= " AND a.term_id = ?";
            $params[] = $term;
            $types .= "i";
        }
        
        $sql .= " ORDER BY a.due_date ASC, a.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        $stmt->close();
        return $assignments;
        
    } else {
        // Teacher can only see assignments for classes they teach
        // FIXED: Map user_id to teachers.id first
        $teacher_pk_id = getTeacherIdFromUserId($conn, $teacher_id);
        if (!$teacher_pk_id) {
            error_log("GET ASSIGNMENTS FAILED: No active teacher profile for user_id $teacher_id");
            return [];  // Empty if no profile
        }
        
        $sql = "SELECT a.*, c.class_name,
                       (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
                       (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted') as pending_count
                FROM assignments a 
                LEFT JOIN classes c ON a.class_id = c.id 
                WHERE a.teacher_id = ?";
        
        $params = [$teacher_pk_id];
        $types = "i";
        
        // Apply filters for teacher
        if (!empty($class_id)) {
            $sql .= " AND a.class_id = ?";
            $params[] = $class_id;
            $types .= "i";
        }
        
        if (!empty($subject)) {
            $sql .= " AND a.subject = ?";
            $params[] = $subject;
            $types .= "s";
        }
        
        if (!empty($status)) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($type)) {
            $sql .= " AND a.assignment_type = ?";
            $params[] = $type;
            $types .= "s";
        }
        
        if (!empty($academic_year)) {
            $sql .= " AND a.academic_year = ?";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        if (!empty($term)) {
            $sql .= " AND a.term_id = ?";
            $params[] = $term;
            $types .= "i";
        }
        
        $sql .= " ORDER BY a.due_date ASC, a.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        $stmt->close();
        return $assignments;
    }
}

// Add missing helper functions
function verifyTeacherClassAccess($conn, $teacher_id, $class_id) {
    // Verify access via teachers.id linked to user_id
    $sql = "SELECT c.id 
            FROM classes c 
            JOIN teachers t ON c.class_teacher_id = t.id 
            WHERE c.id = ? AND t.user_id = ? AND t.status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $class_id, $teacher_id);  // class_id, user_id (session teacher_id)
    $stmt->execute();
    $result = $stmt->get_result();
    $hasAccess = $result->num_rows > 0;
    $stmt->close();
    return $hasAccess;
}

function verifyAssignmentOwnership($conn, $assignment_id, $teacher_user_id) {  // Renamed param for clarity
    $teacher_id = getTeacherIdFromUserId($conn, $teacher_user_id);
    if (!$teacher_id) {
        return false;
    }
    $sql = "SELECT id FROM assignments WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasAccess = $result->num_rows > 0;
    $stmt->close();
    return $hasAccess;
}

function gradeAssignment($conn, $teacher_id, $is_admin) {
    // TODO: Implement grading logic
    $_SESSION['success'] = "Grading functionality coming soon.";
}

// Assume other functions like getAllClasses, getAllSubjects, etc., are defined elsewhere
// For getAllTeachers example:
if (!function_exists('getAllTeachers')) {
    function getAllTeachers($conn) {
        $sql = "SELECT t.user_id as id, CONCAT(u.first_name, ' ', u.last_name) as full_name 
                FROM teachers t 
                JOIN users u ON t.user_id = u.id 
                WHERE t.status = 'active' 
                ORDER BY full_name ASC";
        $result = $conn->query($sql);
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        return $teachers;
    }
}


// FIXED: Updated to fetch recent years (not just current)
function getAllAcademicYears($conn) {
    $sql = "SELECT year_name, start_date, end_date, is_current 
            FROM academic_years 
            ORDER BY start_date DESC 
            LIMIT 5";
    $result = $conn->query($sql);
    $years = [];
    
    while ($row = $result->fetch_assoc()) {
        $years[] = $row;
    }
    
    return $years;
}

function getAllTerms($conn) {
    $sql = "SELECT id, term_name 
            FROM terms  
            ORDER BY term_name DESC";
    $result = $conn->query($sql);
    $terms = [];
    
    while ($row = $result->fetch_assoc()) {
        $terms[] = $row;
    }
    
    return $terms;
}

function getAllSubjects($conn) {
    $sql = "SELECT id, subject_name, subject_code 
            FROM subjects 
            ORDER BY subject_name ASC";
    $result = $conn->query($sql);
    $subjects = [];
    
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    
    return $subjects;
}

function getAllClasses($conn, $teacher_id = null, $is_admin = false) {
    $classes = [];
    
    if ($is_admin) {
        // Admin can see all classes
        $sql = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
        $result = $conn->query($sql);
    } else if ($teacher_id) {
        // Teacher can only see classes assigned to them via teachers.id
        // Join to filter by user_id (session teacher_id)
        $sql = "SELECT c.id, c.class_name 
                FROM classes c 
                JOIN teachers t ON c.class_teacher_id = t.id 
                WHERE t.user_id = ? AND t.status = 'Active' 
                ORDER BY c.class_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);  // teacher_id is user_id from session
        $stmt->execute();
        $result = $stmt->get_result();
        
        // No fallback: If no classes, return empty array (UI will show "No classes available")
    } else {
        // Default: all classes (for filters when no specific teacher)
        $sql = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
        $result = $conn->query($sql);
    }
   
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
   
    if (isset($stmt)) {
        $stmt->close();
    }
   
    return $classes;
}

function getAllTeachers($conn) {
    $sql = "SELECT t.id, CONCAT(u.full_name, ' ', u.username) as full_name, u.username 
            FROM teachers t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.status = 'Active' 
            ORDER BY full_name ASC";
    $result = $conn->query($sql);
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    return $teachers;
}

function getAssignmentStatistics($conn, $teacher_id, $is_admin) {
    $stats = [];
    
    if ($is_admin) {
        // Admin statistics - all assignments (unchanged)
        $sql = "SELECT COUNT(*) as total FROM assignments";
        $result = $conn->query($sql);
        $stats['total_assignments'] = $result->fetch_assoc()['total'];
        
        $sql = "SELECT COUNT(DISTINCT a.id) as pending 
                FROM assignments a 
                JOIN assignment_submissions s ON a.id = s.assignment_id 
                WHERE s.status = 'submitted'";
        $result = $conn->query($sql);
        $stats['pending_grading'] = $result->fetch_assoc()['pending'];
        
        $sql = "SELECT COUNT(*) as overdue FROM assignments WHERE due_date < CURDATE() AND status = 'active'";
        $result = $conn->query($sql);
        $stats['overdue'] = $result->fetch_assoc()['overdue'];
        
    } else {
        // FIXED: Map to teachers.id for teacher statistics
        $teacher_pk_id = getTeacherIdFromUserId($conn, $teacher_id);
        if (!$teacher_pk_id) {
            // Return zeros if no profile
            $stats['total_assignments'] = 0;
            $stats['pending_grading'] = 0;
            $stats['overdue'] = 0;
            $stats['avg_submission_rate'] = 0;
            return $stats;
        }
        
        $sql = "SELECT COUNT(*) as total FROM assignments WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_assignments'] = $result->fetch_assoc()['total'];
        $stmt->close();
        
        $sql = "SELECT COUNT(DISTINCT a.id) as pending 
                FROM assignments a 
                JOIN assignment_submissions s ON a.id = s.assignment_id 
                WHERE a.teacher_id = ? AND s.status = 'submitted'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pending_grading'] = $result->fetch_assoc()['pending'];
        $stmt->close();
        
        $sql = "SELECT COUNT(*) as overdue FROM assignments WHERE teacher_id = ? AND due_date < CURDATE() AND status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['overdue'] = $result->fetch_assoc()['overdue'];
        $stmt->close();
    }
    
    // Average submission rate (same for both, unchanged)
    $sql = "SELECT AVG(submission_rate) as avg_rate 
            FROM (SELECT a.id, 
                         COUNT(s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE class_id = a.class_id AND status = 'active') as submission_rate 
                  FROM assignments a 
                  LEFT JOIN assignment_submissions s ON a.id = s.assignment_id 
                  WHERE a.due_date < CURDATE() 
                  GROUP BY a.id) as rates";
    $result = $conn->query($sql);
    $stats['avg_submission_rate'] = round($result->fetch_assoc()['avg_rate'] ?? 0, 1);
    
    return $stats;
}

function getAssignmentSubmissions($conn, $assignment_id) {
    $sql = "SELECT s.*, st.first_name, st.last_name, st.student_id 
            FROM assignments_summary s 
            JOIN students st ON s.student_id = st.id 
            WHERE s.assignment_id = ? 
            ORDER BY s.submission_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submissions = [];
    
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    
    $stmt->close();
    return $submissions;
}

function getClassName($conn, $class_id) {
    $sql = "SELECT class_name FROM classes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        return $row['class_name'];
    }
    return 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Assignments - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" 
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
      integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
      crossorigin="anonymous" 
      referrerpolicy="no-referrer">
      <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" type="text/css" href="css/assignments.css">
    <style>
        .assignment-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .submission-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .status-submitted { background: #e3f2fd; color: #1976d2; }
        .status-graded { background: #e8f5e8; color: #2e7d32; }
        .status-late { background: #ffebee; color: #c62828; }
        .due-date {
            font-weight: 600;
        }
        .due-date.overdue { color: #d63031; }
        .due-date.today { color: #e17055; }
        .due-date.future { color: #00b894; }
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <?php 

                showFlashMessage();
                ?>
  
                <div class="page-header">
                    <h1>
                        <i class="fas fa-tasks"></i> Assignments Management
                        <?php if ($is_admin): ?>
                            <span class="admin-badge">ADMIN VIEW</span>
                        <?php endif; ?>
                    </h1>
                    <div class="header-actions">
    <button id="createAssignmentBtn" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create Assignment
    </button>
</div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?= $stats['total_assignments'] ?></div>
                        <div class="stat-label">Total Assignments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?= $stats['pending_grading'] ?></div>
                        <div class="stat-label">Pending Grading</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-number"><?= $stats['overdue'] ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number"><?= $stats['avg_submission_rate'] ?>%</div>
                        <div class="stat-label">Avg Submission Rate</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="filter-form">
                        <div class="filter-row">
                  <?php if ($is_admin): ?>
                <div class="form-group">
                    <label for="teacher_id">Assign to Teacher *</label>
                    <select id="teacher_id" name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>">
                                <?= htmlspecialchars($teacher['full_name']) ?> 
                                (<?= htmlspecialchars($teacher['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                            

                        <div class="form-group">
                            <label for="class_id">Class</label>
                            <select id="class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php 
                                if (empty($classes)) {
                                    echo '<option value="" disabled>No classes available</option>';
                                } else {
                                    foreach ($classes as $class): ?>
                                        <option value="<?= $class['id'] ?>" <?= $filter_class == $class['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['class_name']) ?>
                                        </option>
                                    <?php endforeach;
                                } ?>
                            </select>
                        </div>
                            <div class="filter-group">
                                <label for="subject_filter">Subject</label>
                                <select id="subject_filter" name="subject">
                                    <option value="">All Subjects</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= htmlspecialchars($subject['subject_name']) ?>" 
                                                <?= $filter_subject == $subject['subject_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="status_filter">Status</label>
                                <select id="status_filter" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="type_filter">Type</label>
                                <select id="type_filter" name="type">
                                    <option value="">All Types</option>
                                    <option value="homework" <?= $filter_type == 'homework' ? 'selected' : '' ?>>Homework</option>
                                    <option value="project" <?= $filter_type == 'project' ? 'selected' : '' ?>>Project</option>
                                    <option value="quiz" <?= $filter_type == 'quiz' ? 'selected' : '' ?>>Quiz</option>
                                    <option value="exam" <?= $filter_type == 'exam' ? 'selected' : '' ?>>Exam</option>
                                </select>
                            </div>

                            <!-- FIXED: Use DB data for academic_year filter -->
                            <div class="filter-group">
                                <label for="academic_year_filter">Academic Year</label>
                                <select id="academic_year_filter" name="academic_year">
                                    <option value="">All Years</option>
                                    <?php 
                                    if (empty($academic_years)) {
                                        echo '<option value="" disabled>No academic years available</option>';
                                    } else {
                                        foreach ($academic_years as $year): ?>
                                            <option value="<?= htmlspecialchars($year['year_name']) ?>" <?= $filter_academic_year == $year['year_name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($year['year_name']) ?>
                                            </option>
                                        <?php endforeach;
                                    } ?>
                                </select>
                            </div>

                            <!-- FIXED: Use DB data for term filter -->
                            <div class="filter-group">
                                <label for="term_filter">Term</label>
                                <select id="term_filter" name="term">
                                    <option value="">All Terms</option>
                                    <?php 
                                    if (empty($terms)) {
                                        echo '<option value="" disabled>No terms available</option>';
                                    } else {
                                        foreach ($terms as $term_item): ?>
                                            <option value="<?= $term_item['id'] ?>" <?= $filter_term == $term_item['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($term_item['term_name']) ?>
                                            </option>
                                        <?php endforeach;
                                    } ?>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="assignments.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Assignments Table -->
                <div class="table-container">
                    <div class="table-actions">
                        <div class="table-actions-left">
                            <span class="pagination-info">
                                Showing <?= count($assignments) ?> assignments
                            </span>
                        </div>
                        <div class="table-actions-right">
                            <button class="export-btn" onclick="exportAssignments()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="export-btn" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <th>Teacher</th>
                                    <?php endif; ?>
                                    <th>Title</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Due Date</th>
                                    <th>Type</th>
                                    <th>Max Marks</th>
                                    <th>Submissions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($assignments)): ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <?php if ($is_admin): ?>
                                                <td>
                                                    <?= htmlspecialchars($assignment['teacher_name'] ?? 'N/A') ?>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                                <?php if ($assignment['attachment_path']): ?>
                                                    <br><small><i class="fas fa-paperclip"></i> Has attachment</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($assignment['class_name']) ?></td>
                                            <td><?= htmlspecialchars($assignment['subject']) ?></td>
                                            <td>
                                                <?php
                                                $due_date = $assignment['due_date'];
                                                $today = date('Y-m-d');
                                                $due_class = '';
                                                if ($due_date < $today) {
                                                    $due_class = 'overdue';
                                                } elseif ($due_date == $today) {
                                                    $due_class = 'today';
                                                } else {
                                                    $due_class = 'future';
                                                }
                                                ?>
                                                <span class="due-date <?= $due_class ?>">
                                                    <?= date('M j, Y', strtotime($due_date)) ?>
                                                </span>
                                            </td>
                                            <td><?= ucfirst($assignment['assignment_type']) ?></td>
                                            <td><?= $assignment['max_marks'] ?></td>
                                            <td>
                                                <span class="submission-status <?= $assignment['pending_count'] > 0 ? 'status-submitted' : '' ?>">
                                                    <?= $assignment['submission_count'] ?> submitted
                                                    <?= $assignment['pending_count'] > 0 ? "({$assignment['pending_count']} pending)" : '' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $assignment['status'] ?>">
                                                    <?= ucfirst($assignment['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="assignment-actions">
                                                    <button class="btn-icon small primary" 
                                                            onclick="viewAssignment(<?= $assignment['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-icon small success" 
                                                            onclick="viewSubmissions(<?= $assignment['id'] ?>)"
                                                            title="View Submissions">
                                                        <i class="fas fa-list"></i>
                                                    </button>
                                                    <?php if ($is_admin || verifyAssignmentOwnership($conn, $assignment['id'], $teacher_id)): ?>
                                                        <button class="btn-icon small" 
                                                                onclick="editAssignment(<?= $assignment['id'] ?>)"
                                                                title="Edit Assignment">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($assignment['submission_count'] == 0): ?>
                                                            <button class="btn-icon small danger" 
                                                                    onclick="deleteAssignment(<?= $assignment['id'] ?>)"
                                                                    title="Delete Assignment">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $is_admin ? '10' : '9' ?>" class="text-center">
                                            No assignments found. <a href="javascript:void(0)" onclick="showCreateModal()">Create your first assignment</a>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php
            $schoolName = 'Your School';
            $result = $conn->query("SELECT school_name FROM school_settings LIMIT 1");
            if ($result && $row = $result->fetch_assoc()) {
                $schoolName = htmlspecialchars($row['school_name']);
            }
            ?>
            
            <footer class="dashboard-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $schoolName; ?>. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Help Center</a>
                </div>
            </footer>
        </main>
    </div>
    
<!-- Create/Edit Assignment Modal -->
<div id="assignmentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Create New Assignment</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" enctype="multipart/form-data" id="assignmentForm" action="assignments.php">
            <div class="modal-body">
                <input type="hidden" name="assignment_id" id="assignment_id">
                <!-- FIXED: Use a single hidden input that gets updated -->
                <input type="hidden" id="form_action" name="create_assignment" value="1">
             <div class="form-grid">
                    <?php if ($is_admin): ?>
                    <div class="form-group">
                        <label for="teacher_id">Assign to Teacher *</label>
                        <select id="teacher_id" name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>">
                                    <?= htmlspecialchars($teacher['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title">Assignment Title *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_id">Class *</label>
                        <select id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php 
                            if (empty($classes)) {
                                echo '<option value="" disabled>No classes assigned to you. Contact administrator to get assigned.</option>';
                            } else {
                                foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>">
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach;
                            } ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject_id">Subject *</label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>">
                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                    <?php if (!empty($subject['subject_code'])): ?>
                                        (<?= htmlspecialchars($subject['subject_code']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year *</label>
                        <select id="academic_year" name="academic_year" required>
                            <option value="">Select Academic Year</option>
                            <?php 
                            if (empty($academic_years)) {
                                echo '<option value="" disabled>No academic years available</option>';
                            } else {
                                foreach ($academic_years as $year): ?>
                                    <option value="<?= htmlspecialchars($year['year_name']) ?>">
                                        <?= htmlspecialchars($year['year_name']) ?>
                                    </option>
                                <?php endforeach;
                            } ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_id">Term *</label>
                        <select id="term_id" name="term_id" required>
                            <option value="">Select Term</option>
                            <?php 
                            if (empty($terms)) {
                                echo '<option value="" disabled>No terms available</option>';
                            } else {
                                foreach ($terms as $term): ?>
                                    <option value="<?= $term['id'] ?>">
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach;
                            } ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_type">Assignment Type *</label>
                        <select id="assignment_type" name="assignment_type" required>
                            <option value="homework">Homework</option>
                            <option value="project">Project</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_date">Assignment Date *</label>
                        <input type="date" id="assignment_date" name="assignment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date *</label>
                        <input type="date" id="due_date" name="due_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_marks">Maximum Marks</label>
                        <input type="number" id="max_marks" name="max_marks" min="0" step="0.5" value="100">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Assignment description..."></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" rows="3" placeholder="Special instructions for students..."></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="attachment">Attachment</label>
                        <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip">
                        <small>Supported formats: PDF, DOC, DOCX, TXT, JPG, PNG, ZIP (Max: 10MB)</small>
                    </div>
                    
                    <div class="form-group" id="statusField" style="display: none;">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmit">Create Assignment</button>
            </div>
        </form>
    </div>
</div>  

<!-- Load jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then your custom scripts -->
<script src="js/assignments.js" defer></script>
<script src="js/dashboard.js"></script>

</body>
</html>