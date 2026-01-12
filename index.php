<?php

require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'functions.php';
require_once 'access_control.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}


// Restrict access to admin only
if ($_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}
checkAccess(['admin']);

// Set username for display
$username = $_SESSION['username'] ?? 'User';

// Get all users
$users = [];
$sql = "SELECT * FROM users ORDER BY role, full_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get actual statistics from database
$stats = [];

// Function to safely get statistics
function getStatistic($conn, $query, $default = 0) {
    try {
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            return reset($data); // Get first value from result
        }
        return $default;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return $default;
    }
}

// Get basic counts - CORRECTED QUERIES FOR YOUR TABLE STRUCTURE
$stats['total_students'] = getStatistic($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'active'", 0);
$stats['total_teachers'] = getStatistic($conn, "SELECT COUNT(*) as total FROM teachers WHERE status = 'active'", 0);
$stats['total_subjects'] = getStatistic($conn, "SELECT COUNT(*) as total FROM subjects", 0); // No status column
$stats['total_revenue'] = getStatistic($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'", 0);

// Simple trend calculation (optional - you can remove if not needed)
try {
    // Student trend (new students this month vs last month)
    $current_month_students = getStatistic($conn, 
        "SELECT COUNT(*) as total FROM students 
         WHERE status = 'active' 
         AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())", 0);
    
    $last_month_students = getStatistic($conn, 
        "SELECT COUNT(*) as total FROM students 
         WHERE status = 'active' 
         AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
         AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))", 0);
    
    if ($last_month_students > 0) {
        $stats['student_trend_percent'] = round((($current_month_students - $last_month_students) / $last_month_students) * 100, 1);
        $stats['student_trend'] = $current_month_students > $last_month_students ? 'up' : 'down';
    } else {
        $stats['student_trend_percent'] = 0;
        $stats['student_trend'] = 'up';
    }

    // Revenue trend (this month vs last month)
    $current_month_revenue = getStatistic($conn, 
        "SELECT COALESCE(SUM(amount), 0) as total FROM payments 
         WHERE status = 'completed' 
         AND MONTH(payment_date) = MONTH(CURRENT_DATE()) 
         AND YEAR(payment_date) = YEAR(CURRENT_DATE())", 0);
    
    $last_month_revenue = getStatistic($conn, 
        "SELECT COALESCE(SUM(amount), 0) as total FROM payments 
         WHERE status = 'completed' 
         AND MONTH(payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
         AND YEAR(payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))", 0);
    
    if ($last_month_revenue > 0) {
        $stats['revenue_trend_percent'] = round((($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100, 1);
        $stats['revenue_trend'] = $current_month_revenue > $last_month_revenue ? 'up' : 'down';
    } else {
        $stats['revenue_trend_percent'] = 0;
        $stats['revenue_trend'] = 'up';
    }

} catch (Exception $e) {
    error_log("Trend calculation error: " . $e->getMessage());
}

// Set default values for teachers and subjects trends (no trend calculation for these)
$stats['teacher_trend'] = 'up';
$stats['teacher_trend_percent'] = 0;
$stats['subject_trend'] = 'up';
$stats['subject_trend_percent'] = 0;



// Get recent activities from multiple tables
$recentActivities = [];

// 1. Recent student enrollments (from students table)
$sql_enrollments = "SELECT student_id, first_name, last_name, created_at 
                   FROM students 
                   WHERE status = 'active' 
                   ORDER BY created_at DESC 
                   LIMIT 3";
$result = $conn->query($sql_enrollments);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
        $recentActivities[] = [
            'icon' => 'fas fa-user-plus',
            'description' => '<strong>' . $full_name . '</strong> enrolled',
            'time' => $row['created_at']
        ];
    }
}

// 2. Recent payments
$sql_payments = "SELECT p.amount, s.first_name, s.last_name, p.payment_date 
                FROM payments p 
                INNER JOIN students s ON p.student_id = s.student_id 
                WHERE p.status = 'completed' 
                ORDER BY p.payment_date DESC 
                LIMIT 2";
$result = $conn->query($sql_payments);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
        $recentActivities[] = [
            'icon' => 'fas fa-money-bill-wave',
            'description' => 'Payment received from <strong>' . $full_name . '</strong>',
            'time' => $row['payment_date']
        ];
    }
}

// 3. Recent events (from events table)
$sql_events = "SELECT event_title, event_date FROM events 
              WHERE event_date >= CURDATE() 
              ORDER BY event_date ASC 
              LIMIT 1";
$result = $conn->query($sql_events);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $recentActivities[] = [
        'icon' => 'fas fa-calendar-check',
        'description' => 'Upcoming event: <strong>' . htmlspecialchars($row['event_title']) . '</strong>',
        'time' => $row['event_date']
    ];
}

// 4. Recent course/subject additions (optional)
$sql_subjects = "SELECT subject_name, created_at FROM subjects 
                ORDER BY created_at DESC 
                LIMIT 1";
$result = $conn->query($sql_subjects);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $recentActivities[] = [
        'icon' => 'fas fa-book',
        'description' => 'New course added: <strong>' . htmlspecialchars($row['subject_name']) . '</strong>',
        'time' => $row['created_at']
    ];
}

// Sort by time and get latest 5
usort($recentActivities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$recentActivities = array_slice($recentActivities, 0, 5);

// Function to get time ago string
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $current = time();
    $diff = $current - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<?php include 'head.php'?>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
     <?php include 'sidebar.php'?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
         <?php include 'topnav.php'?>
            
            <!-- Dashboard Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="breadcrumb">
                        <a href="#">Home</a> / <span>Dashboard</span>
                    </div>
                     <h1><i class="fas fa-tachometer-alt"></i>Headmaster/Admin Dashboard</h1>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!</p>
                </div>
   <div class="admin-widgets">
                    <!-- Your admin widgets and content -->
                           <!-- Stats Cards -->
<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #4e73df;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_students']); ?></h3>
            <p>Total Students</p>
        </div>
        <div class="stat-trend <?php echo $stats['student_trend'] ?? 'up'; ?>">
            <i class="fas fa-arrow-<?php echo ($stats['student_trend'] ?? 'up') === 'up' ? 'up' : 'down'; ?>"></i> 
            <?php echo $stats['student_trend_percent'] ?? 0; ?>%
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #1cc88a;">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_teachers']); ?></h3>
            <p>Teachers</p>
        </div>
        <div class="stat-trend <?php echo $stats['teacher_trend'] ?? 'up'; ?>">
            <i class="fas fa-arrow-<?php echo ($stats['teacher_trend'] ?? 'up') === 'up' ? 'up' : 'down'; ?>"></i> 
            <?php echo $stats['teacher_trend_percent'] ?? 0; ?>%
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #36b9cc;">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_subjects']); ?></h3>
            <p>Subjects</p>
        </div>
        <div class="stat-trend <?php echo $stats['subject_trend'] ?? 'up'; ?>">
            <i class="fas fa-arrow-<?php echo ($stats['subject_trend'] ?? 'up') === 'up' ? 'up' : 'down'; ?>"></i> 
            <?php echo $stats['subject_trend_percent'] ?? 0; ?>%
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #f6c23e;">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h3>$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
            <p>Revenue</p>
        </div>
        <div class="stat-trend <?php echo $stats['revenue_trend'] ?? 'up'; ?>">
            <i class="fas fa-arrow-<?php echo ($stats['revenue_trend'] ?? 'up') === 'up' ? 'up' : 'down'; ?>"></i> 
            <?php echo $stats['revenue_trend_percent'] ?? 0; ?>%
        </div>
    </div>
</div>
                
<!-- Charts Row -->
<div class="charts-row">
    <div class="chart-card">
        <div class="card-header">
            <h3>Student Enrollment</h3>
            <div class="card-actions">
                <div class="filter-group">
                    <select id="academicYearFilter" class="filter-select">
                        <option value="">Loading Academic Years...</option>
                    </select>
                    
                    <select id="termFilter" class="filter-select">
                        <option value="">Select Term</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                        <option value="annual">Annual</option>
                    </select>
                    
                    <select id="classFilter" class="filter-select">
                        <option value="">Loading Classes...</option>
                    </select>
                    
                    <button id="applyFilters" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    
                    <button id="resetFilters" class="filter-btn reset">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
        <div class="chart-info" id="enrollmentChartInfo" style="padding: 0 20px; font-size: 14px; color: #6c757d;">
            <!-- Filter info will be displayed here -->
        </div>
        <div class="chart-container">
            <canvas id="enrollmentChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="card-header">
            <h3>Revenue Sources</h3>
            <div class="card-actions">
                <div class="filter-group">
                    <select id="revenueYearFilter" class="filter-select">
                        <option value="">Loading Academic Years...</option>
                    </select>
                    
                    <select id="revenueTermFilter" class="filter-select">
                        <option value="">Select Term</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                        <option value="annual">Annual</option>
                    </select>
                    
                    <button id="applyRevenueFilters" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </div>
        </div>
        <div class="chart-info" id="revenueChartInfo" style="padding: 0 20px; font-size: 14px; color: #6c757d;">
            <!-- Revenue filter info will be displayed here -->
        </div>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>
                
                <!-- Recent Activity and Quick Actions -->
                
<div class="content-row">
    <div class="activity-card">
        <div class="card-header">
            <h3>Recent Activity</h3>
            <a href="activities.php" class="view-all">View All</a>
        </div>
        <div class="activity-list">
            <?php if (!empty($recentActivities)): ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="<?php echo htmlspecialchars($activity['icon']); ?>"></i>
                        </div>
                        <div class="activity-content">
                            <p><?php echo $activity['description']; ?></p>
                            <small><?php echo timeAgo($activity['time']); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="activity-content">
                        <p>No recent activities</p>
                        <small>Activities will appear here</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
               
        <div class="quick-actions-card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="actions-grid">
                            <a href="students.php" class="action-item">
                                <i class="fas fa-user-plus"></i>
                                <span>Add Student</span>
                            </a>
                            
                            <a href="subjects.php" class="action-item">
                                <i class="fas fa-book-medical"></i>
                                <span>Create Course</span>
                            </a>
                            
                            <a href="event.php" class="action-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Schedule Event</span>
                            </a>
                            
                            <a href="messages.php" class="action-item">
                                <i class="fas fa-envelope"></i>
                                <span>Send Notice</span>
                            </a>
                            
                            <a href="invoice.php" class="action-item">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Generate Invoice</span>
                            </a>
                            
                            <a href="report.php" class="action-item">
                                <i class="fas fa-chart-bar"></i>
                                <span>View Reports</span>
                            </a>

                             <a href="activities.php" class="action-item">
                                <i class="fas fa-tasks"></i>    
                                <span>View Activities</span>
                            </a>

                             <a href="activities_log.php" class="action-item">
                                <i class="fas fa-tasks"></i>    
                                <span>View Activities</span>
                            </a>


                        </div>
                    </div>
                </div>
                
                <!-- Recent Students Table -->
                <div class="table-card">
                    <div class="card-header">
                        <h3>Users</h3>
                        <a href="users.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn-icon edit-user" data-id="<?php echo $user['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-icon delete-user" data-id="<?php echo $user['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            </div>
 
            <!-- Footer -->
         <?php
require_once 'config.php'; 

// Fetch school name from school_settings table
$schoolName = 'Your School'; // fallback if query fails
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
 <?php 
 
include 'script.php'

?>

<script>
    // Wait for the DOM to be fully loaded
    document.addEventListener("DOMContentLoaded", function() {

        // Get all dropdown toggle buttons
        document.querySelectorAll(".sidebar-nav .dropdown-toggle").forEach(toggle => {
            toggle.addEventListener("click", function (e) {
                e.preventDefault();
                const parentLi = this.parentElement;

                // Close all other dropdowns
                document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
                    // Check if the current dropdown is not the one being clicked
                    if (item !== parentLi) {
                        item.classList.remove("open");
                    }
                });

                // Toggle the 'open' class on the clicked dropdown's parent list item
                parentLi.classList.toggle("open");
            });
        });

        // Add logic for the dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', function() {
                document.body.classList.toggle('dark-mode', this.checked);
            });
        }
    });
</script>


</body>
</html>