<?php
// students.php
require_once 'config.php';
require_once 'session.php';
include 'control.php'; // Assuming this includes necessary functions/setup

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check the 'form_action' hidden input from the studentForm for add/update
    if (isset($_POST['form_action'])) {
        // Sanitize the action value (using $conn->real_escape_string for consistency)
        $form_action = $conn->real_escape_string($_POST['form_action']);

        if ($form_action === 'add_student') {
            // Add new student
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $dob = $conn->real_escape_string($_POST['dob']);
            $gender = $conn->real_escape_string($_POST['gender']);
            $address = $conn->real_escape_string($_POST['address']);
            $parent_name = $conn->real_escape_string($_POST['parent_name']);
            $parent_contact = $conn->real_escape_string($_POST['parent_contact']);
            $email = $conn->real_escape_string($_POST['email']);
            $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : 'NULL';
            $status = 'Active'; // Default status for new students, consider adding to form if needed

            // --- DUPLICATE CHECK START ---
            $check_sql = "SELECT id FROM students WHERE first_name = '$first_name' AND last_name = '$last_name' AND dob = '$dob'";
            $check_result = $conn->query($check_sql);

            if ($check_result && $check_result->num_rows > 0) {
                $_SESSION['message'] = "Error: A student with the same first name, last name, and date of birth already exists!";
                header("Location: students.php");
                exit();
            }
            // --- DUPLICATE CHECK END ---

            $student_id = 'ST' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
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
    } elseif (isset($_POST['delete_student'])) { // This condition is correct for the delete form
        // Delete student
        $id = intval($_POST['id']);

        $sql = "DELETE FROM students WHERE id = $id";

        if ($conn->query($sql)) {
            $_SESSION['message'] = "Student deleted successfully!";
            header("Location: students.php");
            exit();
        } else {
            $error = "Error deleting student: " . $conn->error;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - GEBSCO</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/dropdown.css">
    
<style>
/*
 * This CSS implements the grid layout for the form fields
 * and ensures it is responsive.
 */
    .btn-cancel {
    background-color: #f4f4f4;
    color: #333;
    border: 1px solid #ccc;
    padding: 10px 16px;
    font-size: 14px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-cancel:hover {
    background-color: #e0e0e0;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
    border: none;
    padding: 10px 16px;
    font-size: 14px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-danger:hover {
    background-color: #c0392b;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1; /* Span across both columns */
}

/* Responsive adjustments for smaller screens */
@media (max-width: 600px) {
    .form-grid {
        grid-template-columns: 1fr; /* Single column on mobile */
    }
}

/* Base form styles (reused from previous update) */
.form-group {
    margin-bottom: 0.75rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    /* Updated styles for reduced width and centering */
    width: 100%;
    max-width: 300px;
    display: block; /* Required for auto margins to work */
    margin: 0 auto;
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.95rem;
    box-sizing: border-box;
}

/* Override the max-width for the address textarea to make it full-width */
.form-group.full-width textarea {
    max-width: 100%;
    margin: 0;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4e73df;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
    gap: 0.75rem;
}

/* Dark mode compatibility for the form */
body.dark-mode .form-group label {
    color: #d1d3e2;
}

body.dark-mode .form-group input,
body.dark-mode .form-group select,
body.dark-mode .form-group textarea {
    background-color: #2e2e2e;
    border-color: #444;
    color: #f8f9fa;
}

body.dark-mode .modal-content {
    background-color: #1a1a1a;
    color: #f8f9fa;
}

/* ========================================= */
/* === View Modal Specific Styles (Redesigned) === */
/* ========================================= */
.view-details {
    padding: 1rem; /* Added padding around the details */
    border: 1px solid #eee; /* Light border for definition */
    border-radius: 8px;
    background-color: #f9f9f9; /* Slightly different background for visual separation */
    margin-bottom: 1.5rem; /* Space below the details block */
}

.view-details p {
    display: flex; /* Use flexbox for alignment */
    justify-content: space-between; /* Distribute space between label and value */
    align-items: baseline; /* Align text baselines */
    margin-bottom: 0.8rem; /* Increased margin between detail lines */
    line-height: 1.4; /* Improved line spacing */
    padding-bottom: 0.3rem; /* Small padding below each line */
    border-bottom: 1px dotted #e0e0e0; /* Dotted line separator */
}

.view-details p:last-child {
    border-bottom: none; /* No border for the last item */
    margin-bottom: 0;
    padding-bottom: 0;
}

.view-details strong {
    flex-shrink: 0; /* Prevent label from shrinking */
    width: 140px; /* Slightly wider fixed width for labels */
    font-weight: 600;
    color: #555;
    text-align: left; /* Align labels to the left */
    margin-right: 1rem; /* Space between label and value */
}

.view-details span {
    flex-grow: 1; /* Allow value to take remaining space */
    color: #333;
    text-align: right; /* Align values to the right */
    word-break: break-word; /* Break long words */
}

/* Dark mode adjustments for view modal */
body.dark-mode .view-details {
    background-color: #212121;
    border-color: #3a3a3a;
}

body.dark-mode .view-details strong {
    color: rgba(255,255,255,0.8);
}

body.dark-mode .view-details span {
    color: var(--dark-mode-text);
}

body.dark-mode .view-details p {
    border-bottom: 1px dotted #4a4a4a;
}
</style>
    </head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php include 'topnav.php'; ?>

        <div class="content-wrapper">
            <div class="page-header">
                <h1>Students Management</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Home</a> / <span>Students</span>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Student Records</h3>
                    <button class="btn-primary" id="addStudentBtn">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>

                <div class="card-body">
                    <table id="studentsTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Parent</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_contact']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($student['status']); ?>">
                                        <?php echo htmlspecialchars($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-icon view-student" data-id="<?php echo $student['id']; ?>" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-student" data-id="<?php echo $student['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-student" data-id="<?php echo $student['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

 <div id="studentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Add New Student</h2>
        <form id="studentForm" method="POST" action="students.php">
            <input type="hidden" name="form_action" id="formAction" value="add_student">
            <input type="hidden" name="id" id="studentId" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name*</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob">
                </div>
                <div class="form-group">
                    <label for="gender">Gender*</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="class_id">Class*</label>
                    <select id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <!-- Options will be populated by students.js -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="parent_name">Parent/Guardian Name</</label>
                    <input type="text" id="parent_name" name="parent_name">
                </div>
                <div class="form-group">
                    <label for="parent_contact">Parent Contact</label>
                    <input type="text" id="parent_contact" name="parent_contact">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="2"></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- View Student Details Modal -->
<div id="viewStudentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Student Details</h2>
        <div class="view-details">
            <p><strong>Student ID:</strong> <span id="viewStudentId"></span></p>
            <p><strong>Name:</strong> <span id="viewStudentName"></span></p>
            <p><strong>Date of Birth:</strong> <span id="viewStudentDob"></span></p>
            <p><strong>Gender:</strong> <span id="viewStudentGender"></span></p>
            <p><strong>Class:</strong> <span id="viewStudentClass"></span></p>
            <p><strong>Parent/Guardian:</strong> <span id="viewStudentParentName"></span></p>
            <p><strong>Parent Contact:</strong> <span id="viewStudentParentContact"></span></p>
            <p><strong>Email:</strong> <span id="viewStudentEmail"></span></p>
            <p><strong>Address:</strong> <span id="viewStudentAddress"></span></p>
            <p><strong>Status:</strong> <span id="viewStudentStatus" class="status"></span></p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>⚠️ Confirm Student Deletion</h2>
        <div class="warning-box" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;">
                <strong>Warning:</strong> This action cannot be undone. If this student has associated records 
                (payments, grades, attendance), the deletion will be prevented to maintain data integrity.
            </p>
        </div>
        <p>Are you sure you want to delete this student?</p>
        <form id="deleteForm" method="POST" action="students.php">
            <input type="hidden" name="id" id="deleteId">
            <div class="form-actions" style="justify-content: center;">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" name="delete_student" class="btn-danger">
                    <i class="fas fa-exclamation-triangle"></i> Delete Student
                </button>
            </div>
        </form>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    
    <script src="js/students.js"></script>
     <script src="js/darkmode.js"></script>
    <script src="js/dashboard.js"></script>
    <script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add fade-out animation after 5 seconds
        setTimeout(() => {
            alert.classList.add('fade-out');
            // Remove the alert from DOM after animation completes
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500); // 500ms matches the CSS transition duration
        }, 5000); // 5000ms = 5 seconds
    });
});

// Optional: Click to dismiss functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.alert')) {
        const alert = e.target.closest('.alert');
        alert.classList.add('fade-out');
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 500);
    }
});
</script>
    <script>
        window.allStudentsData = <?php echo json_encode($students); ?>;
        window.allClassesData = <?php echo json_encode($classes); ?>;
        // console.log(window.allStudentsData); // For debugging
        // console.log(window.allClassesData); // For debugging
    </script>
</body>
</html>