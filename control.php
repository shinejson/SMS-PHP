<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check the 'form_action' hidden input from the studentForm for add/update
    if (isset($_POST['form_action'])) {
        // Sanitize the action value (using $conn->real_escape_string for consistency)
        $form_action = $conn->real_escape_string($_POST['form_action']); 

        if ($form_action === 'add_student') {
            // Add new student
            $student_id = 'ST' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $dob = $conn->real_escape_string($_POST['dob']);
            $gender = $conn->real_escape_string($_POST['gender']);
            $address = $conn->real_escape_string($_POST['address']);
            $parent_name = $conn->real_escape_string($_POST['parent_name']);
            $parent_contact = $conn->real_escape_string($_POST['parent_contact']);
            $email = $conn->real_escape_string($_POST['email']);
            $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : 'NULL'; // Handle empty class_id
            $status = 'Active'; // Default status for new students, consider adding to form if needed

                 $check_sql = "SELECT id FROM students WHERE first_name = '$first_name' AND last_name = '$last_name' AND dob = '$dob'";
            $check_result = $conn->query($check_sql);

            if ($check_result && $check_result->num_rows > 0) {
                $_SESSION['message'] = "Error: A student with the same first name, last name, and date of birth already exists!";
                header("Location: students.php");
                exit();
            }
            // --- DUPLICATE CHECK END ---


            $sql = "INSERT INTO students (student_id, first_name, last_name, dob, gender,
                    address, parent_name, parent_contact, email, class_id, status)
                    VALUES ('$student_id', '$first_name', '$last_name', '$dob', '$gender',
                    '$address', '$parent_name', '$parent_contact', '$email', $class_id, '$status')";

            if ($conn->query($sql)) {
                $_SESSION['message'] = "Student added successfully!";
                header("Location: students.php");
                exit();
            } else {
                $error = "Error adding student: " . $conn->error;
            }
        } elseif ($form_action === 'update_student') {
            // Update student
            $id = intval($_POST['id']);
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $dob = $conn->real_escape_string($_POST['dob']);
            $gender = $conn->real_escape_string($_POST['gender']);
            $address = $conn->real_escape_string($_POST['address']);
            $parent_name = $conn->real_escape_string($_POST['parent_name']);
            $parent_contact = $conn->real_escape_string($_POST['parent_contact']);
            $email = $conn->real_escape_string($_POST['email']);
            $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : 'NULL'; // Handle empty class_id
            // Assuming status is not updated via this form, or add a field for it if needed
            // $status = $conn->real_escape_string($_POST['status']);

            $sql = "UPDATE students SET
                    first_name = '$first_name',
                    last_name = '$last_name',
                    dob = '$dob',
                    gender = '$gender',
                    address = '$address',
                    parent_name = '$parent_name',
                    parent_contact = '$parent_contact',
                    email = '$email',
                    class_id = $class_id
                    WHERE id = $id";

            if ($conn->query($sql)) {
                $_SESSION['message'] = "Student updated successfully!";
                header("Location: students.php");
                exit();
            } else {
                $error = "Error updating student: " . $conn->error;
            }
        }
    } elseif  (isset($_POST['delete_student'])) {
    $id = intval($_POST['id']);
    
    // Check for dependencies before attempting deletion
    $dependencies = [];
    
    // Check payments table
    $check_payments = "SELECT COUNT(*) as count FROM payments WHERE student_id = $id";
    $result_payments = $conn->query($check_payments);
    $payments_count = $result_payments->fetch_assoc()['count'];
    
    if ($payments_count > 0) {
        $dependencies[] = "payment records ($payments_count)";
    }
    
    $check_attendance = "SELECT COUNT(*) as count FROM attendance WHERE student_id = $id";
    if ($conn->query($check_attendance)) {
        $result_attendance = $conn->query($check_attendance);
        $attendance_count = $result_attendance->fetch_assoc()['count'];
        if ($attendance_count > 0) {
            $dependencies[] = "attendance records ($attendance_count)";
        }
    }
    
    // If dependencies exist, show error message
    if (!empty($dependencies)) {
        $dependency_list = implode(', ', $dependencies);
        $_SESSION['error'] = "Cannot delete student! This student has associated " . $dependency_list . ". Please remove these records first or contact administrator.";
        header("Location: students.php");
        exit();
    }
    
    // If no dependencies, proceed with deletion
    $sql = "DELETE FROM students WHERE id = $id";
    
    if ($conn->query($sql)) {
        $_SESSION['message'] = "Student deleted successfully!";
        header("Location: students.php");
        exit();
    } else {
        $_SESSION['error'] = "Error deleting student: " . $conn->error;
        header("Location: students.php");
        exit();
    }
}
}

// Get all students
$students = [];
$sql = "SELECT s.*, c.class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        ORDER BY s.first_name, s.last_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get classes for dropdown
$classes = [];
$sql = "SELECT id, class_name FROM classes ORDER BY class_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}



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


