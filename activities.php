<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_message'] = "Please login to access this page";
    header('Location: login.php');
    exit();
}

// Set username for display
$username = $_SESSION['username'] ?? 'User';

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Activity types for filter dropdown
$activity_types = [
    'enrollment' => 'Student Enrollment',
    'payment' => 'Payments',
    'event' => 'Events',
    'course' => 'Course Management'
];

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
        return date('M d, Y H:i', $time);
    }
}

// Function to get badge color based on activity type
function getActivityBadgeClass($type) {
    $classes = [
        'enrollment' => 'badge-primary',
        'payment' => 'badge-success',
        'event' => 'badge-info',
        'course' => 'badge-warning'
    ];
    return $classes[$type] ?? 'badge-light';
}

// Get all activities from multiple tables
$allActivities = [];

// 1. Student enrollments
if (empty($type_filter) || $type_filter === 'enrollment') {
    $sql_enrollments = "SELECT student_id, first_name, last_name, created_at 
                       FROM students 
                       WHERE status = 'active' 
                       ORDER BY created_at DESC";
    $result = $conn->query($sql_enrollments);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
            $allActivities[] = [
                'icon' => 'fas fa-user-plus',
                'description' => '<strong>' . $full_name . '</strong> enrolled in the system',
                'time' => $row['created_at'],
                'type' => 'enrollment',
                'raw_date' => $row['created_at']
            ];
        }
    }
}

// 2. Payments
if (empty($type_filter) || $type_filter === 'payment') {
    $sql_payments = "SELECT p.amount, s.first_name, s.last_name, p.payment_date 
                    FROM payments p 
                    INNER JOIN students s ON p.student_id = s.student_id 
                    WHERE p.status = 'completed' 
                    ORDER BY p.payment_date DESC";
    $result = $conn->query($sql_payments);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
            $amount = number_format($row['amount'], 2);
            $allActivities[] = [
                'icon' => 'fas fa-money-bill-wave',
                'description' => 'Payment of $' . $amount . ' received from <strong>' . $full_name . '</strong>',
                'time' => $row['payment_date'],
                'type' => 'payment',
                'raw_date' => $row['payment_date']
            ];
        }
    }
}

// 3. Events
if (empty($type_filter) || $type_filter === 'event') {
    $sql_events = "SELECT event_title, event_date, created_at FROM events 
                  ORDER BY event_date DESC";
    $result = $conn->query($sql_events);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $event_date = date('M d, Y', strtotime($row['event_date']));
            $is_upcoming = strtotime($row['event_date']) >= strtotime('today');
            $allActivities[] = [
                'icon' => 'fas fa-calendar-check',
                'description' => ($is_upcoming ? 'Upcoming event: ' : 'Event: ') . '<strong>' . htmlspecialchars($row['event_title']) . '</strong> (' . $event_date . ')',
                'time' => $row['created_at'],
                'type' => 'event',
                'raw_date' => $row['created_at']
            ];
        }
    }
}

// 4. Course/Subject additions
if (empty($type_filter) || $type_filter === 'course') {
    $sql_subjects = "SELECT subject_name, created_at FROM subjects 
                    ORDER BY created_at DESC";
    $result = $conn->query($sql_subjects);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $allActivities[] = [
                'icon' => 'fas fa-book',
                'description' => 'New course added: <strong>' . htmlspecialchars($row['subject_name']) . '</strong>',
                'time' => $row['created_at'],
                'type' => 'course',
                'raw_date' => $row['created_at']
            ];
        }
    }
}

// Apply date filters
if (!empty($date_from) || !empty($date_to)) {
    $allActivities = array_filter($allActivities, function($activity) use ($date_from, $date_to) {
        $activity_date = date('Y-m-d', strtotime($activity['raw_date']));
        
        $pass_from = empty($date_from) || $activity_date >= $date_from;
        $pass_to = empty($date_to) || $activity_date <= $date_to;
        
        return $pass_from && $pass_to;
    });
}

// Sort by time (newest first)
usort($allActivities, function($a, $b) {
    return strtotime($b['raw_date']) - strtotime($a['raw_date']);
});

// Get total count for pagination
$total_activities = count($allActivities);
$total_pages = ceil($total_activities / $limit);

// Apply pagination
$activities = array_slice($allActivities, $offset, $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities - School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .top-nav {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #2e59d9;
            margin-bottom: 5px;
        }

        .breadcrumb {
            color: #6c757d;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #4e73df;
            text-decoration: none;
        }

        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #4e73df;
            color: white;
        }

        .btn-primary:hover {
            background: #2e59d9;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #ddd;
            color: #6c757d;
        }

        .btn-outline:hover {
            background: #f8f9fa;
        }

        /* Activities List */
        .activities-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            color: #2e59d9;
            margin: 0;
        }

        .activities-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: flex-start;
            transition: background 0.3s;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .activity-icon i {
            color: #495057;
            font-size: 16px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            margin-bottom: 5px;
            color: #495057;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 12px;
            color: #6c757d;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-primary { background: #4e73df; color: white; }
        .badge-success { background: #1cc88a; color: white; }
        .badge-info { background: #36b9cc; color: white; }
        .badge-warning { background: #f6c23e; color: white; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px;
            border-top: 1px solid #eee;
        }

        .pagination a {
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #4e73df;
            transition: all 0.3s;
        }

        .pagination a:hover, .pagination a.active {
            background: #4e73df;
            color: white;
            border-color: #4e73df;
        }

        .pagination a.disabled {
            color: #6c757d;
            pointer-events: none;
            background: #f8f9fa;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .activity-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <?php include 'topnav.php'; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Activity Log</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Activities</span>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-card">
                <form method="GET" action="activities.php" class="filter-form">
                    <div class="form-group">
                        <label for="type">Activity Type</label>
                        <select name="type" id="type" class="form-control">
                            <option value="">All Types</option>
                            <?php foreach ($activity_types as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $type_filter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="activities.php" class="btn btn-outline">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Activities List -->
            <div class="activities-card">
                <div class="card-header">
                    <h3>All Activities (<?php echo $total_activities; ?>)</h3>
                </div>
                
                <div class="activities-list">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="<?php echo htmlspecialchars($activity['icon']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p><?php echo $activity['description']; ?></p>
                                    <div class="activity-meta">
                                        <span class="badge <?php echo getActivityBadgeClass($activity['type']); ?>">
                                            <?php echo $activity_types[$activity['type']] ?? ucfirst($activity['type']); ?>
                                        </span>
                                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($activity['time']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($activity['time'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No activities found</h3>
                            <p>There are no activities matching your current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    <?php else: ?>
                        <a class="disabled">First</a>
                        <a class="disabled">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last</a>
                    <?php else: ?>
                        <a class="disabled">Next</a>
                        <a class="disabled">Last</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
<script src="js/dashboard.js"></script>
     <script src="js/darkmode.js"></script>
    <script>
        // Set date_to max to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('date_to')) {
                document.getElementById('date_to').max = today;
            }
            if (document.getElementById('date_from')) {
                document.getElementById('date_from').max = today;
            }
        });
    </script>
</body>
</html>
    
   