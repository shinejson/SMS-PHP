<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';
checkAccess(['admin', 'teacher', 'staff']);

// Get filters
$class_id = $_GET['class_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$attendance_date = $_GET['attendance_date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$academic_year_id = $_GET['academic_year_id'] ?? '';
$term_id = $_GET['term_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'summary';

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

// Generate report data
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'summary':
        $report_title = 'Attendance Summary Report';
        // DYNAMIC: Compute on-the-fly from attendance table
        if ($attendance_date) {
            // Single date summary
            $sql = "SELECT 
                        s.id as student_id,
                        s.first_name,
                        s.last_name,
                        s.student_id,
                        c.class_name,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_days,
                        COUNT(a.id) as total_days,
                        ROUND(COALESCE((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / NULLIF(COUNT(a.id), 0)), 0) * 100, 1) as attendance_percentage
                    FROM students s
                    LEFT JOIN attendance a ON a.student_id = s.id 
                        AND a.attendance_date = ?
                        AND a.status IS NOT NULL
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.status = 'active'";
            
            $params = [$attendance_date];
            $types = "s";
        } else {
            // Monthly summary
            $periodStart = "$year-$month-01";
            $periodEnd = date('Y-m-t', strtotime($periodStart));  // Last day of month
            
            $sql = "SELECT 
                        s.id as student_id,
                        s.first_name,
                        s.last_name,
                        s.student_id,
                        c.class_name,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_days,
                        COUNT(a.id) as total_days,
                        ROUND(COALESCE((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / NULLIF(COUNT(a.id), 0)), 0) * 100, 1) as attendance_percentage
                    FROM students s
                    LEFT JOIN attendance a ON a.student_id = s.id 
                        AND a.attendance_date BETWEEN ? AND ?
                        AND a.status IS NOT NULL
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.status = 'active'";
            
            $params = [$periodStart, $periodEnd];
            $types = "ss";
        }
        
        if ($class_id) {
            $sql .= " AND s.class_id = ?";
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
        
        $sql .= " GROUP BY s.id ORDER BY c.class_name, s.first_name, s.last_name";
        break;
        
    case 'detailed':
        $report_title = 'Detailed Attendance Report';
        if ($attendance_date) {
            $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, c.class_name, u.full_name as marked_by_name
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    JOIN classes c ON a.class_id = c.id
                    LEFT JOIN users u ON a.marked_by = u.id
                    WHERE a.attendance_date = ?";
            $params = [$attendance_date];
            $types = "s";
        } else {
            $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, c.class_name, u.full_name as marked_by_name
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    JOIN classes c ON a.class_id = c.id
                    LEFT JOIN users u ON a.marked_by = u.id
                    WHERE MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?";
            $params = [$month, $year];
            $types = "ss";
        }
        
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
        
        $sql .= " ORDER BY a.attendance_date DESC, c.class_name, s.first_name";
        break;
        
    case 'student':
        $report_title = 'Student-wise Attendance Report';
        $student_id = $_GET['student_id'] ?? '';
        
        if ($student_id) {
            if ($attendance_date) {
                $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, c.class_name
                        FROM attendance a
                        JOIN students s ON a.student_id = s.id
                        JOIN classes c ON a.class_id = c.id
                        WHERE s.id = ? AND a.attendance_date = ?";
                $params = [$student_id, $attendance_date];
                $types = "is";
            } else {
                $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, c.class_name
                        FROM attendance a
                        JOIN students s ON a.student_id = s.id
                        JOIN classes c ON a.class_id = c.id
                        WHERE s.id = ? AND MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?";
                $params = [$student_id, $month, $year];
                $types = "iss";
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
            
            $sql .= " ORDER BY a.attendance_date DESC";
        } else {
            $report_data = [];
            break;
        }
        break;
}

if (!empty($sql)) {
    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        $stmt->close();
    }
}

// Export functionality
if (isset($_GET['export'])) {
    exportReport($report_data, $report_type, $month, $year, $report_title, $attendance_date);
}

function exportReport($data, $type, $month, $year, $title, $date = '') {
    $filename_suffix = $date ? date('Y-m-d', strtotime($date)) : $month . '_' . $year;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . $type . '_' . $filename_suffix . '.csv');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    if ($type === 'summary') {
        fputcsv($output, ['Student Name', 'Student ID', 'Class', 'Total Days', 'Present', 'Absent', 'Late', 'Excused', 'Percentage']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['first_name'] . ' ' . $row['last_name'],
                $row['student_id'],
                $row['class_name'],
                $row['total_days'],
                $row['present_days'],
                $row['absent_days'],
                $row['late_days'],
                $row['excused_days'],
                number_format((float)($row['attendance_percentage'] ?? 0), 1) . '%'
            ]);
        }
    } elseif ($type === 'detailed') {
        fputcsv($output, ['Date', 'Student Name', 'Student ID', 'Class', 'Status', 'Time In', 'Time Out', 'Remarks', 'Marked By']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['attendance_date'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['student_id'],
                $row['class_name'],
                ucfirst($row['status']),
                $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-',
                $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-',
                $row['remarks'],
                $row['marked_by_name'] ?? 'System'
            ]);
        }
    } elseif ($type === 'student') {
        fputcsv($output, ['Date', 'Status', 'Time In', 'Time Out', 'Remarks']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['attendance_date'],
                ucfirst($row['status']),
                $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-',
                $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-',
                $row['remarks']
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Attendance Reports - School Management System</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
   <link rel="stylesheet" type="text/css" href="css/attendance-report.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
                    <div class="header-actions">
                        <a href="attendance.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Attendance
                        </a>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="report-filters">
                    <h3><i class="fas fa-filter"></i> Report Filters</h3>
                    <form method="GET" class="filters">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label>Report Type</label>
                                <select name="report_type" onchange="this.form.submit()">
                                    <option value="summary" <?= $report_type === 'summary' ? 'selected' : '' ?>>Summary Report</option>
                                    <option value="detailed" <?= $report_type === 'detailed' ? 'selected' : '' ?>>Detailed Report</option>
                                    <option value="student" <?= $report_type === 'student' ? 'selected' : '' ?>>Student Report</option>
                                </select>
                            </div>
                            
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
                            
                            <?php if ($report_type === 'student'): ?>
                            <div class="filter-group">
                                <label>Student</label>
                                <select name="student_id">
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>" <?= $student_id == $student['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="filter-group">
                                <label>Date (Optional - Overrides Month/Year)</label>
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
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Report Content -->
                <?php if (!empty($report_data)): ?>
                    <!-- Report Stats (for summary and detailed) -->
                    <?php if ($report_type === 'summary' || $report_type === 'detailed'): ?>
                        <div class="report-stats">
                            <div class="stat-card">
                                <h3><?= count($report_data) ?></h3>
                                <p>Records Found</p>
                            </div>
                            <?php if ($report_type === 'summary'): ?>
                                <?php
                                $totalPresent = array_sum(array_column($report_data, 'present_days'));
                                $totalStudents = count($report_data);
                                $avgPercentage = $totalStudents > 0 ? round(array_sum(array_map(function($p) { return (float)($p ?? 0); }, array_column($report_data, 'attendance_percentage'))) / $totalStudents, 1) : 0;
                                ?>
                                <div class="stat-card">
                                    <h3><?= $totalPresent ?></h3>
                                    <p>Total Present Days</p>
                                </div>
                                <div class="stat-card">
                                    <h3><?= $avgPercentage ?>%</h3>
                                    <p>Average Attendance</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="report-content">
                        <div class="report-header">
                            <h2 class="report-title"><?= htmlspecialchars($report_title) ?> - <?= $attendance_date ? date('M d, Y', strtotime($attendance_date)) : date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></h2>
                            <?php if ($class_id): ?>
                                <p>Filtered by Class: <?= htmlspecialchars($classes[array_search($class_id, array_column($classes, 'id'))]['class_name'] ?? 'Unknown') ?></p>
                            <?php endif; ?>
                            <a href="?<?= http_build_query($_GET + ['export' => 'csv']) ?>" class="export-btn">
                                <i class="fas fa-download"></i> Export CSV
                            </a>
                        </div>
                        
                        <?php if ($report_type === 'summary'): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Student ID</th>
                                        <th>Class</th>
                                        <th>Total Days</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Excused</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <?php
                                        $percentage = (float)($row['attendance_percentage'] ?? 0);
                                        $percentage_class = 'percentage-high';
                                        if ($percentage < 75) {
                                            $percentage_class = 'percentage-low';
                                        } elseif ($percentage < 90) {
                                            $percentage_class = 'percentage-medium';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                                            <td><?= htmlspecialchars($row['class_name']) ?></td>
                                            <td><?= $row['total_days'] ?></td>
                                            <td><?= $row['present_days'] ?></td>
                                            <td><?= $row['absent_days'] ?></td>
                                            <td><?= $row['late_days'] ?></td>
                                            <td><?= $row['excused_days'] ?></td>
                                            <td class="<?= $percentage_class ?>">
                                                <strong><?= number_format($percentage, 1) ?>%</strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($report_type === 'detailed'): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student Name</th>
                                        <th>Student ID</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Remarks</th>
                                        <th>Marked By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                                            <td><?= htmlspecialchars($row['class_name']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $row['status'] ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-' ?></td>
                                            <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-' ?></td>
                                            <td><?= htmlspecialchars($row['remarks']) ?></td>
                                            <td><?= htmlspecialchars($row['marked_by_name'] ?? 'System') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($report_type === 'student'): ?>
                            <?php if (!empty($report_data)): ?>
                                <?php $student = $report_data[0]; ?>
                                <div class="student-info" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                                    <h4>Student: <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                                    <p>Student ID: <?= htmlspecialchars($student['student_id']) ?> | Class: <?= htmlspecialchars($student['class_name']) ?></p>
                                </div>
                                
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $row['status'] ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-' ?></td>
                                                <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-' ?></td>
                                                <td><?= htmlspecialchars($row['remarks']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-center">Please select a student to view their attendance report.</p>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="report-content">
                        <div class="no-data">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <h3>No Data Found</h3>
                            <p>Please adjust your filters to generate a report. Ensure there is attendance data for the selected period.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    <script src="js/pwa.js"></script>
</body>
</html>