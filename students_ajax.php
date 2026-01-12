<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';
checkAccess(['admin', 'teacher', 'staff']);

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['success' => false, 'message' => 'Unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $action = $_POST['formAction'] ?? '';
    $student_id = $_POST['studentId'] ?? '';

    switch ($action) {
        case 'add_student':
            $response = addStudent($conn);
            break;
            
        case 'update_student':
            if (empty($student_id)) {
                throw new Exception('Student ID is required for update.');
            }
            $response = updateStudent($conn, $student_id);
            break;
            
        case 'delete_student':
            if (empty($student_id)) {
                throw new Exception('Student ID is required for deletion.');
            }
            $response = deleteStudent($conn, $student_id);
            break;
            
        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);

function addStudent($conn) {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'class_id', 'academic_year', 'class_status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required."];
        }
    }

    // Generate student ID
    $student_id_number = generateStudentId($conn);
    
    // Prepare data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? null;
    $class_id = $_POST['class_id'];
    $academic_year_id = $_POST['academic_year'];
    $class_status = $_POST['class_status'];
    $parent_name = $_POST['parent_name'] ?? null;
    $parent_contact = $_POST['parent_contact'] ?? null;
    $email = $_POST['email'] ?? null;
    $address = $_POST['address'] ?? null;

    // Insert student
    $sql = "INSERT INTO students (
        student_id, first_name, last_name, dob, gender, 
        class_id, academic_year_id, class_status,
        parent_name, parent_contact, email, address, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'sssssiisssss',
        $student_id_number,
        $first_name,
        $last_name,
        $dob,
        $gender,
        $class_id,
        $academic_year_id,
        $class_status,
        $parent_name,
        $parent_contact,
        $email,
        $address
    );
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Student added successfully!'];
    } else {
        throw new Exception('Failed to add student: ' . $stmt->error);
    }
}

function updateStudent($conn, $student_id) {
    // Check if student exists and is not graduated
    $check_sql = "SELECT class_status FROM students WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Student not found.');
    }
    
    $student = $check_result->fetch_assoc();
    if ($student['class_status'] === 'graduated') {
        throw new Exception('Cannot edit graduated student.');
    }

    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'class_id', 'academic_year', 'class_status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required."];
        }
    }

    // Prepare data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? null;
    $class_id = $_POST['class_id'];
    $academic_year_id = $_POST['academic_year'];
    $class_status = $_POST['class_status'];
    $parent_name = $_POST['parent_name'] ?? null;
    $parent_contact = $_POST['parent_contact'] ?? null;
    $email = $_POST['email'] ?? null;
    $address = $_POST['address'] ?? null;

    // Update student
    $sql = "UPDATE students SET 
        first_name = ?, last_name = ?, dob = ?, gender = ?,
        class_id = ?, academic_year_id = ?, class_status = ?,
        parent_name = ?, parent_contact = ?, email = ?, address = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'ssssiisssssi',
        $first_name,
        $last_name,
        $dob,
        $gender,
        $class_id,
        $academic_year_id,
        $class_status,
        $parent_name,
        $parent_contact,
        $email,
        $address,
        $student_id
    );
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Student updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made.'];
        }
    } else {
        throw new Exception('Failed to update student: ' . $stmt->error);
    }
}

function deleteStudent($conn, $student_id) {
    // Check if student exists
    $check_sql = "SELECT first_name, last_name FROM students WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Student not found.');
    }

    // Soft delete (update status to 'inactive') or hard delete based on your preference
    // Option 1: Soft delete (recommended)
    $sql = "UPDATE students SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    
    // Option 2: Hard delete (uncomment if you want to permanently delete)
     $sql = "DELETE FROM students WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $student_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Student deleted successfully!'];
    } else {
        throw new Exception('Failed to delete student: ' . $stmt->error);
    }
}

function generateStudentId($conn) {
    // Generate a unique student ID (format: STU-YYYY-XXXXX)
    $year = date('Y');
    
    // Get the last student ID for this year
    $sql = "SELECT student_id FROM students WHERE student_id LIKE 'STU-$year-%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['student_id'];
        $last_number = intval(substr($last_id, -5));
        $new_number = str_pad($last_number + 1, 5, '0', STR_PAD_LEFT);
    } else {
        $new_number = '00001';
    }
    
    return "STU-$year-$new_number";
}
?>