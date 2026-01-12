<?php

require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access to teacher only
// Restrict access to teacher and admin only
if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}
checkAccess(['teacher', 'admin']);

// ---------------------------------------------------------------------
//  Teacher-specific validation – active profile required
// ---------------------------------------------------------------------
$teacher_id = $_SESSION['user_id'];  // Post-migration: teacher_id = user_id

$sql = "SELECT user_id 
        FROM teachers 
        WHERE user_id = ? 
          AND TRIM(LOWER(status)) = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // ---- Decide why the row is missing ----
    $checkAny = $conn->prepare("SELECT 1 FROM teachers WHERE user_id = ?");
    $checkAny->bind_param("i", $teacher_id);
    $checkAny->execute();
    $anyRow = $checkAny->get_result()->num_rows > 0;
    $checkAny->close();

    if ($anyRow) {
        $_SESSION['error'] = "Your teacher profile is currently <strong>inactive</strong>. "
                           . "Please contact the administrator to reactivate it.";
    } else {
        $_SESSION['error'] = "Teacher profile not found. "
                           . "Please contact the administrator to create your profile.";
    }

    $stmt->close();
    header("Location: login.php");
    exit();
}

$stmt->close();

// Get teacher-specific statistics
$stats = [];

// Function to safely get statistics
function getStatistic($conn, $query, $params = [], $default = 0) {
    try {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $types = str_repeat('i', count($params)); // Changed to 'i' for integers
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            return reset($data);
        }
        return $default;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return $default;
    }
}

// Get teacher's assigned subjects count (from assignments table)
$stats['assigned_subjects'] = getStatistic($conn, 
    "SELECT COUNT(DISTINCT subject) as total FROM assignments WHERE teacher_id = ?", 
    [$teacher_id], 0
);

// Get teacher's total students (students in teacher's classes – use class_teacher_id if no assignments)
$stats['total_students'] = getStatistic($conn, 
    "SELECT COUNT(DISTINCT s.id) as total 
     FROM students s 
     JOIN classes c ON s.class_id = c.id 
     WHERE (c.class_teacher_id = ? OR EXISTS (SELECT 1 FROM assignments a WHERE a.class_id = c.id AND a.teacher_id = ?))
       AND s.status = 'active'", 
    [$teacher_id, $teacher_id], 0
);

// Get pending assignments to grade
$stats['pending_grading'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM assignments 
     WHERE teacher_id = ? AND status = 'submitted'", 
    [$teacher_id], 0
);

// Get upcoming assignments due today
$stats['today_assignments'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM assignments 
     WHERE teacher_id = ? AND due_date = CURDATE() AND status != 'completed'", 
    [$teacher_id], 0
);

// Get teacher's recent activities
$recentActivities = [];

// Get recent assignments created
$sql_assignments = "SELECT title, created_at FROM assignments 
                   WHERE teacher_id = ? 
                   ORDER BY created_at DESC 
                   LIMIT 3";
$stmt = $conn->prepare($sql_assignments);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'icon' => 'fas fa-tasks',
            'description' => 'Created assignment: <strong>' . htmlspecialchars($row['title']) . '</strong>',
            'time' => $row['created_at']
        ];
    }
}
$stmt->close();

// Get recent attendance marks (from attendance table – use teacher_id directly)
// Fixed: removed a.created_at which doesn't exist
$sql_attendance = "SELECT CONCAT(s.first_name, ' ', s.last_name) as student_name, 
                          a.attendance_date, a.status 
                  FROM attendance a 
                  JOIN students s ON a.student_id = s.id 
                  WHERE a.teacher_id = ? 
                  ORDER BY a.attendance_date DESC, a.updated_at DESC
                  LIMIT 3";
$stmt = $conn->prepare($sql_attendance);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'icon' => 'fas fa-calendar-check',
            'description' => 'Marked attendance for: <strong>' . htmlspecialchars($row['student_name']) . '</strong> (' . ucfirst($row['status']) . ')',
            'time' => $row['attendance_date']
        ];
    }
}
$stmt->close();

// Get recent students interacted with (use attendance or classes)
// Fixed: removed a.created_at reference
$sql_students = "SELECT DISTINCT s.first_name, s.last_name, s.student_id, 
                        COALESCE(MAX(ass.created_at), MAX(att.updated_at)) as last_interaction 
                 FROM students s 
                 JOIN classes c ON s.class_id = c.id 
                 LEFT JOIN assignments ass ON c.id = ass.class_id AND ass.teacher_id = ?
                 LEFT JOIN attendance att ON s.id = att.student_id AND att.teacher_id = ?
                 WHERE (c.class_teacher_id = ? OR ass.id IS NOT NULL OR att.id IS NOT NULL) 
                   AND s.status = 'active' 
                 GROUP BY s.id, s.first_name, s.last_name, s.student_id
                 ORDER BY last_interaction DESC 
                 LIMIT 5";
$stmt = $conn->prepare($sql_students);
$stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$recentStudents = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentStudents[] = $row;
    }
}
$stmt->close();

// Today's assignments table
$todayAssignments = [];
$sql_today_assign = "SELECT title, due_date, class_id, max_marks, assignment_type 
                     FROM assignments 
                     WHERE teacher_id = ? AND due_date = CURDATE() AND status != 'completed' 
                     ORDER BY due_date ASC";
$stmt = $conn->prepare($sql_today_assign);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $todayAssignments[] = $row;
    }
}
$stmt->close();

// Recent attendance summary
$recentAttendance = [];
$sql_attend = "SELECT a.attendance_date, COUNT(*) as total_marked, 
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count 
               FROM attendance a 
               WHERE a.teacher_id = ? 
               GROUP BY a.attendance_date 
               ORDER BY a.attendance_date DESC 
               LIMIT 7";
$stmt = $conn->prepare($sql_attend);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentAttendance[] = $row;
    }
}
$stmt->close();

// Flash message function
if (!function_exists('showFlashMessage')) {
    function showFlashMessage() {
        if (!empty($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ' . $_SESSION['error'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                  </div>';
            unset($_SESSION['error']);
        }
        if (!empty($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . $_SESSION['success'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                  </div>';
            unset($_SESSION['success']);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Teacher Dashboard - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/attendance.css">
<link rel="stylesheet" href="css/tables.css">  <!-- Add this -->
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <?php showFlashMessage(); ?>
                
                <div class="page-header">
                    <h1><i class="fas fa-chalkboard-teacher"></i> Teacher Dashboard</h1>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="location.href='attendance.php'">
                            <i class="fas fa-clipboard-check"></i> Mark Attendance
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stat-number"><?= $stats['assigned_subjects'] ?></div>
                        <div class="stat-label">Assigned Subjects</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?= $stats['total_students'] ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?= $stats['pending_grading'] ?></div>
                        <div class="stat-label">Pending Grading</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-number"><?= $stats['today_assignments'] ?></div>
                        <div class="stat-label">Due Today</div>
                    </div>
                </div>

                <div class="grid-2">
                    <!-- Recent Activities -->
                    <div class="activity-card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        </div>
                        <div class="activity-list">
                            <?php if (!empty($recentActivities)): ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="<?= $activity['icon'] ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?= $activity['description'] ?></div>
                                            <div class="activity-time"><?= date('M j, Y g:i A', strtotime($activity['time'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center">No recent activities</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Students -->
<!-- Recent Attendance Summary -->
<div class="chart-card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
        <div class="header-actions">
            <button class="export-btn" onclick="exportAttendanceCSV()">
                <i class="fas fa-download"></i> Export CSV
            </button>
            <button class="export-btn" onclick="printAttendance()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="attendance-table-container">
        <!-- Actions Bar -->
        <div class="attendance-actions-bar">
            <div class="table-actions-left">
                <span class="pagination-info">
                    Total Records: <?= count($recentAttendance) ?>
                </span>
            </div>
            <div class="table-actions-right">
                <select class="form-select-sm" onchange="filterAttendance(this.value)">
                    <option value="all">All Dates</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
        </div>
        
        <!-- Scrollable Table Area -->
        <div class="attendance-table-wrapper">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0)">Date</th>
                        <th class="sortable" onclick="sortTable(1)">Total Marked</th>
                        <th class="sortable" onclick="sortTable(2)">Present</th>
                        <th class="sortable" onclick="sortTable(3)">Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentAttendance)): ?>
                        <?php foreach ($recentAttendance as $attend): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($attend['attendance_date'])) ?></td>
                                <td><?= $attend['total_marked'] ?></td>
                                <td><?= $attend['present_count'] ?></td>
                                <td class="percentage-cell <?= 
                                    ($attend['total_marked'] > 0 ? round(($attend['present_count'] / $attend['total_marked']) * 100, 1) : 0) >= 80 ? 'percentage-high' : 
                                    (($attend['total_marked'] > 0 ? round(($attend['present_count'] / $attend['total_marked']) * 100, 1) : 0) >= 60 ? 'percentage-medium' : 'percentage-low')
                                ?>">
                                    <?= $attend['total_marked'] > 0 ? round(($attend['present_count'] / $attend['total_marked']) * 100, 1) . '%' : '0%' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No recent attendance data</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination - Always Visible -->
        <div class="attendance-pagination">
            <div class="pagination-info">
                Showing 1-<?= count($recentAttendance) ?> of <?= count($recentAttendance) ?> records
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-numbers">
                    <span class="page-number active">1</span>
                </div>
                <button class="pagination-btn" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>
                </div>

                <div class="grid-2">
                    <!-- Recent Attendance Summary -->
                    <div class="chart-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Marked</th>
                                        <th>Present</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentAttendance)): ?>
                                        <?php foreach ($recentAttendance as $attend): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($attend['attendance_date'])) ?></td>
                                                <td><?= $attend['total_marked'] ?></td>
                                                <td><?= $attend['present_count'] ?></td>
                                                <td><?= $attend['total_marked'] > 0 ? round(($attend['present_count'] / $attend['total_marked']) * 100, 1) . '%' : '0%' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No recent attendance data</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Today's Assignments -->
                    <div class="activity-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Assignments Due Today</h3>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Class</th>
                                        <th>Max Marks</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($todayAssignments)): ?>
                                        <?php foreach ($todayAssignments as $assign): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($assign['title']) ?></td>
                                                <td><?= htmlspecialchars(getClassName($conn, $assign['class_id'])) ?></td>
                                                <td><?= $assign['max_marks'] ?></td>
                                                <td><?= ucfirst($assign['assignment_type']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No assignments due today</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
    
    <?php include 'script.php' ?>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>

    <script>
        // Attendance table functionality
function exportAttendanceCSV() {
    // CSV export implementation
    console.log('Exporting attendance data to CSV...');
    // Add your CSV export logic here
}

function printAttendance() {
    // Print implementation
    console.log('Printing attendance data...');
    window.print();
}

function filterAttendance(filter) {
    // Filter implementation
    console.log('Filtering attendance by:', filter);
    // Add your filtering logic here
}

function sortTable(columnIndex) {
    // Sorting implementation
    console.log('Sorting by column:', columnIndex);
    // Add your sorting logic here
}

// Ensure tables are properly sized on load
document.addEventListener('DOMContentLoaded', function() {
    const attendanceContainers = document.querySelectorAll('.attendance-table-container');
    attendanceContainers.forEach(container => {
        const wrapper = container.querySelector('.attendance-table-wrapper');
        const table = container.querySelector('table');
        
        // Ensure table takes full width
        if (table) {
            table.style.minWidth = '100%';
        }
        
        // Add resize observer to handle dynamic content
        const resizeObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                const { height } = entry.contentRect;
                if (height > wrapper.clientHeight) {
                    wrapper.style.overflowY = 'auto';
                }
            }
        });
        
        resizeObserver.observe(table);
    });
});
    </script>
</body>
</html>

<?php
// Helper function to get class name
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