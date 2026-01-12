<?php
// students.php - SECURE VERSION
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');

// Initialize CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get academic years and terms for filters
$academic_years = [];
$terms = [];
$current_academic_year = '';

// Fetch academic years from academic_years table
$year_sql = "SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC";
$year_result = $conn->query($year_sql);
if ($year_result && $year_result->num_rows > 0) {
    while ($row = $year_result->fetch_assoc()) {
        $academic_years[] = $row;
        if ($row['is_current']) {
            $current_academic_year = $row['id'];
        }
    }
    // If no active year found, use the first one
    if (empty($current_academic_year) && !empty($academic_years)) {
        $current_academic_year = $academic_years[0]['id'] ?? '';
    }
}

// Fetch terms
$term_sql = "SELECT DISTINCT term_name FROM terms ORDER BY term_name";
$term_result = $conn->query($term_sql);
if ($term_result && $term_result->num_rows > 0) {
    while ($row = $term_result->fetch_assoc()) {
        $terms[] = $row['term_name'];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: students.php");
        exit();
    }
    
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'add_student') {
        // Add new student with prepared statements
        
        // Validate required fields
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || 
            empty($_POST['gender']) || empty($_POST['class_id']) || 
            empty($_POST['academic_year']) || empty($_POST['class_status'])) {
            $_SESSION['error'] = "Please fill in all required fields!";
            header("Location: students.php");
            exit();
        }
        
        // Sanitize and validate input
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : NULL;
        $gender = $_POST['gender'];
        $address = trim($_POST['address'] ?? '');
        $parent_name = trim($_POST['parent_name'] ?? '');
        $parent_contact = trim($_POST['parent_contact'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: NULL;
        $class_id = intval($_POST['class_id']);
        $academic_year_id = intval($_POST['academic_year']);
        $class_status = $_POST['class_status'];
        $status = 'Active';
        
        // Generate unique student ID
        $student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Validate academic year exists
        $year_check = $conn->prepare("SELECT id FROM academic_years WHERE id = ?");
        $year_check->bind_param('i', $academic_year_id);
        $year_check->execute();
        if ($year_check->get_result()->num_rows === 0) {
            $_SESSION['error'] = "Invalid academic year selected!";
            header("Location: students.php");
            exit();
        }
        $year_check->close();
        
        // Validate class exists
        $class_check = $conn->prepare("SELECT id FROM classes WHERE id = ?");
        $class_check->bind_param('i', $class_id);
        $class_check->execute();
        if ($class_check->get_result()->num_rows === 0) {
            $_SESSION['error'] = "Invalid class selected!";
            header("Location: students.php");
            exit();
        }
        $class_check->close();
        
        // Check for duplicate student (same name and DOB)
        if ($dob) {
            $dup_check = $conn->prepare("SELECT id FROM students WHERE first_name = ? AND last_name = ? AND dob = ?");
            $dup_check->bind_param('sss', $first_name, $last_name, $dob);
            $dup_check->execute();
            if ($dup_check->get_result()->num_rows > 0) {
                $_SESSION['error'] = "A student with the same name and date of birth already exists!";
                header("Location: students.php");
                exit();
            }
            $dup_check->close();
        }
        
        // Insert student using prepared statement
        $sql = "INSERT INTO students (student_id, first_name, last_name, dob, gender, address, 
                parent_name, parent_contact, email, class_id, academic_year_id, class_status, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['error'] = "Database error: " . $conn->error;
            header("Location: students.php");
            exit();
        }
        
        $stmt->bind_param('sssssssssiiis', 
            $student_id, $first_name, $last_name, $dob, $gender, $address,
            $parent_name, $parent_contact, $email, $class_id, $academic_year_id, 
            $class_status, $status
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student added successfully! Student ID: $student_id";
            header("Location: students.php");
            exit();
        } else {
            $_SESSION['error'] = "Error adding student: " . $stmt->error;
            header("Location: students.php");
            exit();
        }
        $stmt->close();
        
    } elseif ($form_action === 'update_student') {
        // Update student with prepared statements
        
        // Validate required fields
        if (empty($_POST['id']) || empty($_POST['first_name']) || empty($_POST['last_name']) || 
            empty($_POST['gender']) || empty($_POST['class_id']) || 
            empty($_POST['academic_year']) || empty($_POST['class_status'])) {
            $_SESSION['error'] = "Please fill in all required fields!";
            header("Location: students.php");
            exit();
        }
        
        $id = intval($_POST['id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : NULL;
        $gender = $_POST['gender'];
        $address = trim($_POST['address'] ?? '');
        $parent_name = trim($_POST['parent_name'] ?? '');
        $parent_contact = trim($_POST['parent_contact'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: NULL;
        $class_id = intval($_POST['class_id']);
        $academic_year_id = intval($_POST['academic_year']);
        $class_status = $_POST['class_status'];
        
        // Get current student data
        $current_stmt = $conn->prepare("SELECT class_id, academic_year_id, class_status FROM students WHERE id = ?");
        $current_stmt->bind_param('i', $id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        if ($current_result->num_rows === 0) {
            $_SESSION['error'] = "Student not found!";
            header("Location: students.php");
            exit();
        }
        
        $current_data = $current_result->fetch_assoc();
        $current_class_id = $current_data['class_id'];
        $current_academic_year_id = $current_data['academic_year_id'];
        $current_class_status = $current_data['class_status'];
        $current_stmt->close();
        
        // Check if student is graduated - prohibit edits
        if ($current_class_status === 'graduated') {
            $_SESSION['error'] = "Cannot edit graduated students. Their records are final.";
            header("Location: students.php");
            exit();
        }
        
        // Validate academic year exists
        $year_check = $conn->prepare("SELECT id FROM academic_years WHERE id = ?");
        $year_check->bind_param('i', $academic_year_id);
        $year_check->execute();
        if ($year_check->get_result()->num_rows === 0) {
            $_SESSION['error'] = "Invalid academic year selected!";
            header("Location: students.php");
            exit();
        }
        $year_check->close();
        
        // Handle status transitions
        $status_changed = ($current_class_status !== $class_status);
        $class_or_year_changed = ($current_class_id != $class_id || $current_academic_year_id != $academic_year_id);
        
        // Case 1: Graduating a student
        if ($class_status === 'graduated' && $current_class_status !== 'graduated') {
            $sql = "UPDATE students SET first_name = ?, last_name = ?, dob = ?, gender = ?,
                    address = ?, parent_name = ?, parent_contact = ?, email = ?,
                    class_id = ?, academic_year_id = ?, class_status = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssssssiisi', 
                $first_name, $last_name, $dob, $gender, $address,
                $parent_name, $parent_contact, $email, $class_id, 
                $academic_year_id, $class_status, $id
            );
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Student graduated successfully! This action cannot be reversed.";
            } else {
                $_SESSION['error'] = "Error graduating student: " . $stmt->error;
            }
            $stmt->close();
            header("Location: students.php");
            exit();
        }
        
        // Case 2: Status change with class/year change (create historical record)
        if ($status_changed && $class_or_year_changed && 
            in_array($class_status, ['promoted', 'repeated', 'probation'])) {
            
            // Generate new student ID
            $new_student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Create new record
            $insert_sql = "INSERT INTO students (student_id, first_name, last_name, dob, gender,
                          address, parent_name, parent_contact, email, class_id, academic_year_id, 
                          class_status, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param('sssssssssiis',
                $new_student_id, $first_name, $last_name, $dob, $gender, $address,
                $parent_name, $parent_contact, $email, $class_id, $academic_year_id, $class_status
            );
            
            if ($insert_stmt->execute()) {
                // Mark old record as historical
                $update_old = $conn->prepare("UPDATE students SET class_status = 'historical' WHERE id = ?");
                $update_old->bind_param('i', $id);
                $update_old->execute();
                $update_old->close();
                
                $status_text = ucfirst($class_status);
                $_SESSION['message'] = "Student $status_text successfully! New record created with ID: $new_student_id";
            } else {
                $_SESSION['error'] = "Error updating student: " . $insert_stmt->error;
            }
            $insert_stmt->close();
            header("Location: students.php");
            exit();
        }
        
        // Case 3: Regular update (no status change or no class/year change)
        $sql = "UPDATE students SET first_name = ?, last_name = ?, dob = ?, gender = ?,
                address = ?, parent_name = ?, parent_contact = ?, email = ?,
                class_id = ?, academic_year_id = ?, class_status = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssssiisi',
            $first_name, $last_name, $dob, $gender, $address,
            $parent_name, $parent_contact, $email, $class_id,
            $academic_year_id, $class_status, $id
        );
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Student updated successfully!";
            } else {
                $_SESSION['message'] = "No changes were made.";
            }
        } else {
            $_SESSION['error'] = "Error updating student: " . $stmt->error;
        }
        $stmt->close();
        header("Location: students.php");
        exit();
        
    } elseif (isset($_POST['delete_student'])) {
        // Delete student
        $id = intval($_POST['id']);
        
        // Check for dependencies
        $dependencies = [];
        
        // Check payments
        $payment_check = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE student_id = ?");
        $payment_check->bind_param('i', $id);
        $payment_check->execute();
        $payment_result = $payment_check->get_result();
        $payments_count = $payment_result->fetch_assoc()['count'];
        if ($payments_count > 0) {
            $dependencies[] = "payment records ($payments_count)";
        }
        $payment_check->close();
        
        // Check attendance
        $attendance_check = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
        $attendance_check->bind_param('i', $id);
        $attendance_check->execute();
        $attendance_result = $attendance_check->get_result();
        $attendance_count = $attendance_result->fetch_assoc()['count'];
        if ($attendance_count > 0) {
            $dependencies[] = "attendance records ($attendance_count)";
        }
        $attendance_check->close();
        
        // If dependencies exist, prevent deletion
        if (!empty($dependencies)) {
            $dependency_list = implode(', ', $dependencies);
            $_SESSION['error'] = "Cannot delete student! This student has associated " . $dependency_list . 
                               ". Please remove these records first or contact administrator.";
            header("Location: students.php");
            exit();
        }
        
        // Delete student
        $delete_stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $delete_stmt->bind_param('i', $id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "Student deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting student: " . $delete_stmt->error;
        }
        $delete_stmt->close();
        header("Location: students.php");
        exit();
    }
}

// Get filter parameters
$class_filter = $_GET['class_filter'] ?? '';
$academic_year_filter = $_GET['academic_year_filter'] ?? $current_academic_year;
$status_filter = $_GET['status_filter'] ?? '';

// Build query with prepared statement
$sql = "SELECT s.*, c.class_name, ay.year_name as academic_year_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
        WHERE 1=1";

$types = '';
$params = [];

// Apply filters
if (!empty($class_filter)) {
    $sql .= " AND s.class_id = ?";
    $types .= 'i';
    $params[] = intval($class_filter);
}

if (!empty($academic_year_filter)) {
    $sql .= " AND s.academic_year_id = ?";
    $types .= 'i';
    $params[] = intval($academic_year_filter);
}

if (!empty($status_filter)) {
    $sql .= " AND s.class_status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

$sql .= " ORDER BY ay.year_name DESC, s.first_name, s.last_name";

// Execute query
$students = [];
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("SQL Prepare Error: " . $conn->error);
    $_SESSION['error'] = "Database error occurred. Please try again.";
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        error_log("SQL Execute Error: " . $stmt->error);
        $_SESSION['error'] = "Error loading student data.";
    }
    $stmt->close();
}

// Get classes for dropdown
$classes = [];
$class_sql = "SELECT id, class_name, academic_year FROM classes ORDER BY class_name";
$class_result = $conn->query($class_sql);

if ($class_result && $class_result->num_rows > 0) {
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Class status options
$class_status_options = ['active', 'promoted', 'repeated', 'probation', 'graduated'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Students Management - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/dropdown.css">
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

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

          <!-- Filter Section -->
<div class="filter-section">
    <form method="GET" action="students.php" id="filterForm">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="class_filter">Class</label>
                <select id="class_filter" name="class_filter">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['academic_year'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        <div class="filter-group">
                <label for="academic_year_filter">Academic Year</label>
                <select id="academic_year_filter" name="academic_year_filter">
                    <option value="">All Years</option>
                    <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo $academic_year_filter == $year['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="status_filter">Class Status</label>
                <select id="status_filter" name="status_filter">
                    <option value="">All Status</option>
                    <?php foreach ($class_status_options as $status_option): ?>
                        <option value="<?php echo $status_option; ?>" <?php echo $status_filter == $status_option ? 'selected' : ''; ?>>
                            <?php echo ucfirst($status_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="students.php" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>
</div>

            <div class="card">
                <div class="card-header">
                    <h3>Student Records</h3>
                    <button class="btn-primary" id="addStudentBtn">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>

                <div class="card-body">
                   <!-- In the HTML table section -->
<table id="studentsTable" class="display">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Class</th>
            <th>Academic Year</th> <!-- This will now show year_name from academic_years -->
            <th>Class Status</th>
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
            <td><?php echo htmlspecialchars($student['academic_year_name'] ?? 'N/A'); ?></td> <!-- FIXED: academic_year_name -->
            <td>
                <span class="status-badge status-<?php echo htmlspecialchars($student['class_status'] ?? 'active'); ?>">
                    <?php echo htmlspecialchars(ucfirst($student['class_status'] ?? 'Active')); ?>
                </span>
            </td>
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

    <!-- Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New Student</h2>
            <form id="studentForm" method="POST" action="students.php">
                <input type="hidden" name="form_action" id="formAction" value="add_student">
                <input type="hidden" name="id" id="studentId" value="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
                        <label for="academic_year">Academic Year*</label>
                        <select id="academic_year" name="academic_year" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['year_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="class_id">Class*</label>
                        <select id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                        </select>
                    </div>
                
                    <div class="form-group">
                        <label for="class_status">Class Status*</label>
                        <select id="class_status" name="class_status" required>
                            <option value="active">Active</option>
                            <option value="promoted">Promoted</option>
                            <option value="repeated">Repeated</option>
                            <option value="probation">Probation</option>
                            <option value="graduated">Graduated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="parent_name">Parent/Guardian Name</label>
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



    <!-- View and Delete Modals (unchanged) -->
    <!-- ... existing view and delete modal code ... -->
    
<!-- View Student Details Modal -->
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
            <p><strong>Academic Year:</strong> <span id="viewStudentAcademicYear"></span></p>
            <p><strong>Class Status:</strong> <span id="viewStudentClassStatus" class="status-badge"></span></p>
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

<script>
window.allStudentsData = <?php echo json_encode($students ?: []); ?>;
window.allClassesData = <?php echo json_encode($classes ?: []); ?>;
window.allAcademicYears = <?php echo json_encode($academic_years ?: []); ?>;
</script>

<!-- Load scripts in correct order -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<script src="js/students.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dashboard.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }, 5000);
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
// Debug script - add this temporarily
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    console.log('Students data:', window.allStudentsData);
    console.log('Table element:', document.getElementById('studentsTable'));
    
    // Check if DataTable is initialized
    setTimeout(function() {
        if ($.fn.DataTable.isDataTable('#studentsTable')) {
            console.log('DataTable initialized successfully');
        } else {
            console.log('DataTable NOT initialized');
        }
    }, 1000);
});
</script>
</body>
</html>