<?php

//teachers control
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_teacher'])) {
        $first_name = $conn->real_escape_string($_POST['first_name']);
        $last_name = $conn->real_escape_string($_POST['last_name']);
        $email = $conn->real_escape_string($_POST['email']);
        
        // Check if teacher already exists
        $check_sql = "SELECT id FROM teachers WHERE email = '$email'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "A teacher with this email already exists!";
        } else {
            $teacher_id = 'TCH' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $phone = $conn->real_escape_string($_POST['phone']);
            $specialization = $conn->real_escape_string($_POST['specialization']);
            $status = $conn->real_escape_string($_POST['status']);
            
            $sql = "INSERT INTO teachers (teacher_id, first_name, last_name, email, phone, specialization, status)
                    VALUES ('$teacher_id', '$first_name', '$last_name', '$email', '$phone', '$specialization', '$status')";
            
            if ($conn->query($sql)) {
                $_SESSION['message'] = "Teacher added successfully! Teacher ID: $teacher_id";
            } else {
                $error = "Error adding teacher: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_teacher'])) {
        $id = intval($_POST['id']);
        $first_name = $conn->real_escape_string($_POST['first_name']);
        $last_name = $conn->real_escape_string($_POST['last_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $specialization = $conn->real_escape_string($_POST['specialization']);
        $status = $conn->real_escape_string($_POST['status']);
        
        // Check if email exists for another teacher
        $check_sql = "SELECT id FROM teachers WHERE email = '$email' AND id != $id";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Another teacher with this email already exists!";
        } else {
            $sql = "UPDATE teachers SET 
                    first_name = '$first_name',
                    last_name = '$last_name',
                    email = '$email',
                    phone = '$phone',
                    specialization = '$specialization',
                    status = '$status'
                    WHERE id = $id";
            
            if ($conn->query($sql)) {
                $_SESSION['message'] = "Teacher updated successfully!";
            } else {
                $error = "Error updating teacher: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_teacher'])) {
        $id = intval($_POST['id']);
        
        // Check if teacher is assigned to any class
        $check_sql = "SELECT COUNT(*) as count FROM classes WHERE class_teacher_id = $id";
        $check_result = $conn->query($check_sql);
        $assigned = $check_result->fetch_assoc()['count'] > 0;
        
        if ($assigned) {
            $_SESSION['error'] = "Cannot delete teacher assigned to a class!";
        } else {
            $sql = "DELETE FROM teachers WHERE id = $id";
            if ($conn->query($sql)) {
                $_SESSION['message'] = "Teacher deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting teacher: " . $conn->error;
            }
        }
    }
    header("Location: teachers.php");
    exit();
}

// Get all teachers
$teachers = [];
$sql = "SELECT * FROM teachers ORDER BY first_name, last_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

// Set username for display
$username = $_SESSION['username'] ?? 'User';



// control for classes
// Handle form submissions

?>


