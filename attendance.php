<?php
// In attendance.php, update the access control section
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

// Check if user can take attendance (admin or teacher)
checkAccess(['admin', 'teacher', 'staff']);

// Get filters
$class_id = $_GET['class_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$attendance_date = $_GET['attendance_date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$academic_year_id = $_GET['academic_year_id'] ?? '';
$term_id = $_GET['term_id'] ?? '';

// Get classes for filter
$classes = [];
$class_sql = "SELECT id, class_name FROM classes ORDER BY class_name";
$class_result = $conn->query($class_sql);
if ($class_result) {
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Get students for filter
$students = [];
$student_sql = "SELECT id, first_name, last_name, student_id FROM students WHERE status = 'active' ORDER BY first_name, last_name";
$student_result = $conn->query($student_sql);
if ($student_result) {
    while ($row = $student_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get academic years
$academic_years = [];
$year_sql = "SELECT id, year_name FROM academic_years ORDER BY start_date DESC";
$year_result = $conn->query($year_sql);
if ($year_result) {
    while ($row = $year_result->fetch_assoc()) {
        $academic_years[] = $row;
    }
}

// Get terms
$terms = [];
$term_sql = "SELECT id, term_name FROM terms ORDER BY start_date DESC";
$term_result = $conn->query($term_sql);
if ($term_result) {
    while ($row = $term_result->fetch_assoc()) {
        $terms[] = $row;
    }
}

// Get today's attendance (add academic_year_id and term_id filters)
$today_attendance = [];
if ($attendance_date) {
    $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, c.class_name, 
                   u.full_name as marked_by_name 
            FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            JOIN classes c ON a.class_id = c.id 
            LEFT JOIN users u ON a.marked_by = u.id 
            WHERE a.attendance_date = ?";
    $params = [$attendance_date];
    $types = "s";
    
    if ($class_id) {
        $sql .= " AND a.class_id = ?";
        $params[] = $class_id;
        $types .= "i";
    }
    
    if ($academic_year_id) {
        $sql .= " AND a.academic_year_id = ?";
        $params[] = $academic_year_id;
        $types .= "i";
    }
    
    if ($term_id) {
        $sql .= " AND a.term_id = ?";
        $params[] = $term_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY s.first_name, s.last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $today_attendance[] = $row;
    }
    $stmt->close();
}

// Get attendance summary (DYNAMIC: Compute on-the-fly from attendance table)
$attendance_summary = [];
if ($month && $year) {
    // Build base query for the period
    $periodStart = "$year-$month-01";
    $periodEnd = date('Y-m-t', strtotime($periodStart));  // Last day of month
    
    $sql = "SELECT 
                s.id as student_id,
                s.first_name,
                s.last_name,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_days,
                COUNT(a.id) as total_days,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / NULLIF(COUNT(a.id), 0)) * 100, 1) as attendance_percentage
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id 
                AND a.attendance_date BETWEEN ? AND ?
                AND a.status IS NOT NULL";
    
    $params = [$periodStart, $periodEnd];
    $types = "ss";
    
    if ($class_id) {
        $sql .= " AND a.class_id = ?";
        $params[] = $class_id;
        $types .= "i";
    }
    
    if ($academic_year_id) {
        $sql .= " AND a.academic_year_id = ?";
        $params[] = $academic_year_id;
        $types .= "i";
    }
    
    if ($term_id) {
        $sql .= " AND a.term_id = ?";
        $params[] = $term_id;
        $types .= "i";
    }
    
    if ($student_id) {
        $sql .= " AND s.id = ?";
        $params[] = $student_id;
        $types .= "i";
    }
    
    $sql .= " WHERE s.status = 'active' GROUP BY s.id ORDER BY s.first_name, s.last_name";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Ensure student names are included (from LEFT JOIN)
            $attendance_summary[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Attendance Management - School Management System</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/attendance.css">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/alert.css">
  
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php
// Flash-message helper – add this block to define the function
if (!function_exists('showFlashMessage')) {
    function showFlashMessage() {
        if (!empty($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ' . $_SESSION['error'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['error']);
        }
        if (!empty($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . $_SESSION['success'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['success']);
        }
    }
}
?>
            <?php showFlashMessage(); ?>
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-check"></i> Attendance Management</h1>
                    <div class="header-actions">
                        <a href="attendance_report.php" class="btn btn-secondary">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <button class="btn btn-primary" onclick="markAttendance()">
                            <i class="fas fa-plus"></i> Mark Attendance
                        </button>
                    </div>
                </div>

<!-- Filters -->
<div class="filters-card">
    <h3>Filters</h3>
    <form method="GET" class="filters">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="academic_year_id">
                    <option value="">All Years</option>
                    <?php foreach ($academic_years as $year_item): ?>
                        <option value="<?= $year_item['id'] ?>" <?= $academic_year_id == $year_item['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year_item['year_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Term</label>
                <select name="term_id">
                    <option value="">All Terms</option>
                    <?php foreach ($terms as $term): ?>
                        <option value="<?= $term['id'] ?>" <?= $term_id == $term['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($term['term_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Class</label>
                <select name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Student</label>
                <select name="student_id" class="student-filter">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $student_id == $student['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="attendance_date" value="<?= $attendance_date ?>">
            </div>
            
            <div class="filter-group">
                <label>Month</label>
                <select name="month">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= sprintf('%02d', $i) ?>" <?= $month == sprintf('%02d', $i) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Year</label>
                <select name="year">
                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="attendance.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

                <!-- Today's Attendance and Summary -->
                <div class="attendance-container">
                    <!-- Today's Attendance -->
                    <div class="attendance-card">
                        <div class="card-header">
                            <h3>Today's Attendance (<?= date('M d, Y', strtotime($attendance_date)) ?>)</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($today_attendance)): ?>
                                        <?php foreach ($today_attendance as $record): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                                <td><?= htmlspecialchars($record['class_name']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $record['status'] ?>">
                                                        <?= ucfirst($record['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-' ?></td>
                                                <td><?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?></td>
                                                <td class="attendance-actions">
                                                    <button class="btn-icon small" onclick="editAttendance(<?= $record['id'] ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-icon small danger" onclick="deleteAttendance(<?= $record['id'] ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No attendance records found for today</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="attendance-card">
                        <div class="card-header">
                            <h3>Monthly Summary (<?= date('F Y', strtotime($year . '-' . $month . '-01')) ?>)</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Excused</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                             <tbody>
    <?php if (!empty($attendance_summary)): ?>
        <?php foreach ($attendance_summary as $summary): ?>
            <?php
            $percentage_class = 'percentage-high';
            if ($summary['attendance_percentage'] < 75) {
                $percentage_class = 'percentage-low';
            } elseif ($summary['attendance_percentage'] < 90) {
                $percentage_class = 'percentage-medium';
            }
            ?>
            <tr>
                <td><?= htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']) ?></td>
                <td><?= $summary['present_days'] ?></td>
                <td><?= $summary['absent_days'] ?></td>
                <td><?= $summary['late_days'] ?></td>
                <td><?= $summary['excused_days'] ?></td>
                <td class="percentage-cell <?= $percentage_class ?>">
                    <?= $summary['attendance_percentage'] ?>%
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6" class="text-center">No summary data found for selected period</td>
        </tr>
    <?php endif; ?>
</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<!-- Mark Attendance Modal -->
<div id="markAttendanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> STUDENTS ATTENDANCE SHEET</h3>
            <span class="close">&times;</span>
        </div>
        <form id="attendanceForm" method="POST" action="attendance_action.php">
            <input type="hidden" name="action" value="mark_attendance">
            <input type="hidden" name="attendance_id" id="attendance_id">
            
            <div class="modal-body">
                <!-- Header Information -->
                <div class="attendance-header">
                    <div class="header-grid">
                        <div class="header-item">
                            <label><strong>Today's Date:</strong></label>
                            <input type="date" name="attendance_date" id="attendance_date" value="<?= date('Y-m-d') ?>" required class="header-input">
                        </div>
<div class="header-item">
    <label><strong>Academic Year:</strong></label>
    <select name="academic_year_id" id="academic_year_id" required class="header-input">
        <option value="">Select Academic Year</option>
        <?php foreach ($academic_years as $year): ?>
            <option value="<?= $year['id'] ?>" data-year-name="<?= htmlspecialchars(trim($year['year_name'])) ?>">
                <?= trim(htmlspecialchars($year['year_name'])) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                        <div class="header-item">
                            <label><strong>Term:</strong></label>
                            <select name="term_id" id="term_id" required class="header-input">
                                <option value="">Select Term</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['id'] ?>">
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="header-item">
                            <label><strong>Class:</strong></label>
                            <select name="class_id" id="class_id" required class="header-input" onchange="loadStudents(this.value)">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Student Selection (Optional) -->
                <div class="student-selection">
                    <div class="form-group">
                        <label for="student_select"><strong>Select Individual Student (Optional):</strong></label>
                       <select name="student_select" id="student_select" onchange="loadStudentDetails(this.value)" class="student-select">
                            <option value="">All Students in Class</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

              <!-- Attendance Sheet Table -->
<div class="attendance-sheet-container">
    <div class="sheet-header">
        <h4>ATTENDANCE RECORD</h4>
    </div>
    <div class="table-container">
        <table class="attendance-sheet-table">
            <thead>
                <tr>
                    <th width="40%">STUDENTS</th>
                    <th width="20%">PRESENT</th>
                    <th width="20%">ABSENT</th>
                    <th width="20%">LATE</th>
                </tr>
            </thead>
            <tbody id="studentsAttendanceList">
                <!-- Students will be loaded here -->
                <tr>
                    <td colspan="4" class="text-center">Please select a class to view students</td>
                </tr>
            </tbody>
        </table>
    </div>
</div> 
                <!-- Additional Notes -->
                <div class="attendance-notes">
                    <div class="form-group">
                        <label for="general_remarks"><strong>General Remarks:</strong></label>
                        <textarea name="general_remarks" id="general_remarks" placeholder="Enter any general remarks for today's attendance..." rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Success Message Modal -->
<div id="successModal" class="success-modal">
    <div class="success-modal-content">
        <div class="success-modal-header">
            <i class="fas fa-check-circle"></i>
            <h3>Success!</h3>
        </div>
        <div class="success-modal-body">
            <p id="successMessage">Attendance saved successfully!</p>
        </div>
        <div class="success-modal-footer">
            <button class="success-modal-btn" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>
</div>
    <script src="js/dashboard.js"></script>
    <script src="js/attendance.js"></script>
    <script src="js/darkmode.js"></script>
   <script src="js/pwa.js"></script>
</body>
</html>