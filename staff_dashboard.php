<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';
// Check if user is logged in and is staff
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access to staff only
if ($_SESSION['role'] !== 'staff') {
    header('Location: access_denied.php');
    exit();
}
checkAccess(['staff']);

// Get staff-specific statistics
$staff_id = $_SESSION['user_id'];
$stats = [];

// Function to safely get statistics
function getStatistic($conn, $query, $params = [], $default = 0) {
    try {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
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

// Get total active students
$stats['total_students'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM students WHERE status = 'active'", 
    [], 0
);

// Get total active teachers
$stats['total_teachers'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM teachers WHERE status = 'active'", 
    [], 0
);

// Get pending payments
$stats['pending_payments'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid'", 
    [], 0
);

// Get overdue invoices
$stats['overdue_invoices'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM invoices WHERE status = 'overdue'", 
    [], 0
);

// Get today's payments
$stats['today_payments'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM payments WHERE DATE(payment_date) = CURDATE()", 
    [], 0
);

// Get recent enrollments (last 7 days)
$stats['recent_enrollments'] = getStatistic($conn, 
    "SELECT COUNT(*) as total FROM students WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", 
    [], 0
);

// Get recent activities for staff
$recentActivities = [];

// Get recent student enrollments
$sql_enrollments = "SELECT first_name, last_name, created_at 
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
            'description' => 'New student enrolled: <strong>' . $full_name . '</strong>',
            'time' => $row['created_at']
        ];
    }
}

// Get recent payments
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
            'description' => 'Payment received: <strong>₵' . number_format($row['amount'], 2) . '</strong> from ' . $full_name,
            'time' => $row['payment_date']
        ];
    }
}

// Get upcoming events
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

// Get recent invoices
$sql_invoices = "SELECT invoice_number, amount, created_at FROM invoices 
                ORDER BY created_at DESC 
                LIMIT 1";
$result = $conn->query($sql_invoices);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $recentActivities[] = [
        'icon' => 'fas fa-file-invoice',
        'description' => 'New invoice created: <strong>#' . $row['invoice_number'] . '</strong>',
        'time' => $row['created_at']
    ];
}

// Sort by time and get latest 5
usort($recentActivities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$recentActivities = array_slice($recentActivities, 0, 5);

// Get pending tasks for staff
$pendingTasks = [];

// Get unpaid invoices
$sql_unpaid = "SELECT invoice_number, student_id, amount, due_date 
              FROM invoices 
              WHERE status = 'unpaid' 
              ORDER BY due_date ASC 
              LIMIT 5";
$result = $conn->query($sql_unpaid);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pendingTasks[] = [
            'type' => 'invoice',
            'title' => 'Unpaid Invoice: #' . $row['invoice_number'],
            'description' => $row['student_id'] . ' - ₵' . number_format($row['amount'], 2),
            'due_date' => $row['due_date'],
            'priority' => strtotime($row['due_date']) < strtotime('+3 days') ? 'high' : 'medium'
        ];
    }
}

// Get overdue invoices
$sql_overdue = "SELECT invoice_number, student_id, amount, due_date 
               FROM invoices 
               WHERE status = 'overdue' 
               ORDER BY due_date ASC 
               LIMIT 3";
$result = $conn->query($sql_overdue);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pendingTasks[] = [
            'type' => 'overdue',
            'title' => 'OVERDUE Invoice: #' . $row['invoice_number'],
            'description' => $row['student_id'] . ' - ₵' . number_format($row['amount'], 2),
            'due_date' => $row['due_date'],
            'priority' => 'critical'
        ];
    }
}

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

<head>
    <meta name="mobile-web-app-capable" content="yes">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>GEBSCO Dashboard</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
</head>
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
                    <h1><i class="fas fa-user-tie"></i> Staff Dashboard</h1>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Staff') ?>! (Staff Member)</p>
                </div>
                
                <!-- Staff-specific Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #4e73df;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_students']); ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #1cc88a;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_teachers']); ?></h3>
                            <p>Active Teachers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f6c23e;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['pending_payments']); ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #e74a3b;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['overdue_invoices']); ?></h3>
                            <p>Overdue Invoices</p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Stats Row -->
                <div class="stats-grid" style="margin-top: 20px;">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #36b9cc;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['today_payments']); ?></h3>
                            <p>Today's Payments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #6f42c1;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['recent_enrollments']); ?></h3>
                            <p>New Enrollments (7 days)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Staff Content Row -->
                <div class="content-row">
                    <!-- Recent Activity -->
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
                                        <small>School activities will appear here</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Tasks -->
                    <div class="quick-actions-card">
                        <div class="card-header">
                            <h3>Pending Tasks</h3>
                        </div>
                        <div class="tasks-list">
                            <?php if (!empty($pendingTasks)): ?>
                                <?php foreach ($pendingTasks as $task): ?>
                                    <div class="task-item <?php echo $task['priority']; ?>">
                                        <div class="task-icon">
                                            <i class="fas fa-<?php echo $task['type'] === 'overdue' ? 'exclamation-triangle' : 'file-invoice'; ?>"></i>
                                        </div>
                                        <div class="task-content">
                                            <h4><?php echo $task['title']; ?></h4>
                                            <p><?php echo $task['description']; ?></p>
                                            <small>Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></small>
                                        </div>
                                        
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="task-item">
                                    <div class="task-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="task-content">
                                        <h4>All Caught Up!</h4>
                                        <p>No pending tasks at the moment.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="content-row">
                    <!-- Staff Quick Actions -->
                    <div class="quick-actions-card full-width">
                        <div class="card-header">
                            <h3>Staff Quick Actions</h3>
                        </div>
                        <div class="actions-grid">
                            <a href="students.php" class="action-item">
                                <i class="fas fa-user-plus"></i>
                                <span>Manage Students</span>
                            </a>
                            
                            <a href="teachers.php" class="action-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Manage Teachers</span>
                            </a>
                            
                            <a href="invoice.php" class="action-item">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Create Invoice</span>
                            </a>
                            
                            <a href="payments.php" class="action-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Record Payment</span>
                            </a>
                            
                            <a href="view_bills.php" class="action-item">
                                <i class="fas fa-receipt"></i>
                                <span>View Bills</span>
                            </a>
                            
                            <a href="event.php" class="action-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Schedule Event</span>
                            </a>
                            
                            <a href="payment-ladger.php" class="action-item">
                                <i class="fas fa-book"></i>
                                <span>Payment Ledger</span>
                            </a>
                            
                            <a href="report.php" class="action-item">
                                <i class="fas fa-chart-bar"></i>
                                <span>Generate Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
                
           <!-- Recent Students Table -->
<div class="table-card">
    <div class="card-header">
        <h3>Recent Student Enrollments</h3>
        <a href="students.php" class="view-all">View All Students</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Enrollment Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Try multiple query options to find the correct one
                $sql_queries = [
                    // Option 1: Using class_assignments table
                     "SELECT s.student_id, s.first_name, s.last_name, s.created_at, s.status, 
                       c.class_name 
                       FROM students s 
                       LEFT JOIN classes c ON s.class_id = c.id 
                       WHERE s.status = 'active' 
                       ORDER BY s.created_at DESC 
                       LIMIT 6",
                    
                    // Option 2: Direct class_id in students table
                    "SELECT s.student_id, s.first_name, s.last_name, s.created_at, s.status, 
                    c.class_name 
                    FROM students s 
                    LEFT JOIN classes c ON s.class_id = c.id 
                    WHERE s.status = 'active' 
                    ORDER BY s.created_at DESC 
                    LIMIT 6",
                    
                    // Option 3: Without class information
                    "SELECT student_id, first_name, last_name, created_at, status 
                    FROM students 
                    WHERE status = 'active' 
                    ORDER BY created_at DESC 
                    LIMIT 6"
                ];
                
                $students_found = false;
                
                foreach ($sql_queries as $sql_recent_students) {
                    $result = $conn->query($sql_recent_students);
                    
                    if ($result && $result->num_rows > 0) {
                        $students_found = true;
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['student_id']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                            
                            // Handle class name - check if column exists
                            if (isset($row['class_name'])) {
                                echo '<td>' . htmlspecialchars($row['class_name'] ?? 'Not Assigned') . '</td>';
                            } else {
                                echo '<td>Class info not available</td>';
                            }
                            
                            echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
                            echo '<td><span class="status-badge active">' . ucfirst($row['status']) . '</span></td>';
                            echo '<td>';
                            echo '<a href="view_student.php?id=' . $row['student_id'] . '" class="btn-icon" title="View">';
                            echo '<i class="fas fa-eye"></i>';
                            echo '</a>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        break; // Stop after first successful query
                    }
                }
                
                if (!$students_found) {
                    echo '<tr><td colspan="6" class="text-center">No recent student enrollments found</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
            </div>
            
            <!-- Footer -->
            <?php
            require_once 'config.php'; 
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
      
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/dashboard.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="js/data-tables.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dropdown.js"></script>
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

    
    });
</script>
</body>
</html>