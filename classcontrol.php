<?php

// CSRF check (top of POST section)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid or missing CSRF token.';
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // --- Add Class ---
    if ($action === 'add_class') {
        $class_name = trim($_POST['class_name']);
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : NULL;
        $academic_year = trim($_POST['academic_year']);
        $description = trim($_POST['description']);

        if (empty($class_name) || empty($academic_year)) {
            $_SESSION['error'] = "Class name and academic year are required.";
        } else {
            // Check if class already exists for same year
            $check_stmt = $conn->prepare("SELECT 1 FROM classes WHERE class_name = ? AND academic_year = ?");
            $check_stmt->bind_param("ss", $class_name, $academic_year);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $_SESSION['error'] = "A class with this name already exists for the selected academic year.";
            } else {
                $stmt = $conn->prepare("INSERT INTO classes (class_name, class_teacher_id, academic_year, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param("siss", $class_name, $class_teacher_id, $academic_year, $description);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "New class added successfully!";
                    } else {
                        $_SESSION['error'] = "Error adding class: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Error preparing statement: " . $conn->error;
                }
            }
            $check_stmt->close();
        }
    }

    // --- Update Class ---
    elseif ($action === 'update_class') {
        $id = intval($_POST['id']);
        $class_name = trim($_POST['class_name']);
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : NULL;
        $academic_year = trim($_POST['academic_year']);
        $description = trim($_POST['description']);

        if ($id <= 0 || empty($class_name) || empty($academic_year)) {
            $_SESSION['error'] = "Invalid data provided for update.";
        } else {
            // Check for duplicates only if name/year changed
            $check_stmt = $conn->prepare("SELECT 1 FROM classes WHERE class_name = ? AND academic_year = ? AND id <> ?");
            $check_stmt->bind_param("ssi", $class_name, $academic_year, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $_SESSION['error'] = "A class with this name already exists for the selected academic year.";
            } else {
                $stmt = $conn->prepare("UPDATE classes SET class_name = ?, class_teacher_id = ?, academic_year = ?, description = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sissi", $class_name, $class_teacher_id, $academic_year, $description, $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['message'] = "Class updated successfully!";
                        } else {
                            $_SESSION['error'] = "No changes were made.";
                        }
                    } else {
                        $_SESSION['error'] = "Error updating record: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Error preparing update statement: " . $conn->error;
                }
            }
            $check_stmt->close();
        }
    }

    // --- Delete Class (with foreign key check) ---
    elseif (isset($_POST['delete_class'])) {
        $id = intval($_POST['id']);
        if ($id > 0) {
            // First, check if this class is referenced in other tables
            $dependencies = [];
            
            // Check billing table
            $check_billing = $conn->prepare("SELECT COUNT(*) as count FROM billing WHERE class_id = ?");
            if ($check_billing) {
                $check_billing->bind_param("i", $id);
                $check_billing->execute();
                $result = $check_billing->get_result();
                $billing_count = $result->fetch_assoc()['count'];
                if ($billing_count > 0) {
                    $dependencies[] = "billing records ($billing_count)";
                }
                $check_billing->close();
            }
            
            // Check students table (if exists)
            $check_students = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE class_id = ?");
            if ($check_students) {
                $check_students->bind_param("i", $id);
                $check_students->execute();
                $result = $check_students->get_result();
                $students_count = $result->fetch_assoc()['count'];
                if ($students_count > 0) {
                    $dependencies[] = "students ($students_count)";
                }
                $check_students->close();
            }
            
            // Check assignments table
            $assignments_count = 0;
            $check_assignments = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE class_id = ?");
            if ($check_assignments) {
                $check_assignments->bind_param("i", $id);
                if ($check_assignments->execute()) {
                    $result = $check_assignments->get_result();
                    if ($result) {
                        $assignments_count = $result->fetch_assoc()['count'];
                        if ($assignments_count > 0) {
                            $dependencies[] = "assignments ($assignments_count)";
                        }
                    }
                }
                $check_assignments->close();
            }
            
            // Check attendance table
            $attendance_count = 0;
            $check_attendance = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ?");
            if ($check_attendance) {
                $check_attendance->bind_param("i", $id);
                if ($check_attendance->execute()) {
                    $result = $check_attendance->get_result();
                    if ($result) {
                        $attendance_count = $result->fetch_assoc()['count'];
                        if ($attendance_count > 0) {
                            $dependencies[] = "attendance records ($attendance_count)";
                        }
                    }
                }
                $check_attendance->close();
            }
            
            // If there are dependencies, prevent deletion
            if (!empty($dependencies)) {
                $dependency_list = implode(', ', $dependencies);
                $_SESSION['error'] = "Cannot delete this class because it has associated " . $dependency_list . ". Please remove or reassign these records first, or consider marking the class as inactive instead of deleting it.";
            } else {
                // Safe to delete - no dependencies found
                $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['message'] = "Class deleted successfully!";
                        } else {
                            $_SESSION['error'] = "Class not found or already deleted.";
                        }
                    } else {
                        $_SESSION['error'] = "Error deleting record: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Error preparing delete statement: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error'] = "Invalid class ID for deletion.";
        }
    }

    header("Location: classes.php");
    exit();
}

// Fetch all classes with teacher names and dependency counts for better UI
$classes = [];
$sql = "SELECT c.*, 
               t.first_name, 
               t.last_name,
               (SELECT COUNT(*) FROM billing b WHERE b.class_id = c.id) as billing_count,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count,
               (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
               (SELECT COUNT(*) FROM attendance att WHERE att.class_id = c.id) as attendance_count
        FROM classes c
        LEFT JOIN teachers t ON c.class_teacher_id = t.id
        ORDER BY c.class_name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Fetch teachers for dropdown
$teachers = [];
$sql = "SELECT id, first_name, last_name FROM teachers ORDER BY first_name, last_name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

// Fetch academic years from academic_years table
$academic_years = [];
$sql = "SELECT * FROM academic_years ORDER BY year_name DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $academic_years[] = $row;
    }
}
?>