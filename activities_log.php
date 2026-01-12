<?php
// activities_log.php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';
require_once 'functions/activity_logger.php';

// NEW: Skip logging for this page to prevent auto-increase on refresh (update activity_logger.php to check this flag)
$_SESSION['skip_log'] = true;

// Restrict access to admin only
checkAccess(['admin']);

// Get filter parameters with validation
$filter_type = $_GET['type'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_user = $_GET['user_id'] ?? '';
$filter_ip = $_GET['ip_address'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// Validate date format
if (!empty($filter_date) && !DateTime::createFromFormat('Y-m-d', $filter_date)) {
    $filter_date = '';
}

// Build base query with filters
$base_sql = "FROM activities a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1";
$params = [];
$types = "";

// Apply filters
$where_conditions = [];

if (!empty($filter_type)) {
    $where_conditions[] = "a.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(a.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($filter_user)) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $filter_user;
    $types .= "i";
}

if (!empty($filter_ip)) {
    $where_conditions[] = "a.ip_address LIKE ?";
    $params[] = "%$filter_ip%";
    $types .= "s";
}

if (!empty($search_query)) {
    $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ? OR u.full_name LIKE ? OR a.ip_address LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Build the WHERE clause
if (!empty($where_conditions)) {
    $base_sql .= " AND " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total " . $base_sql;
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_activities = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// Get unique IP addresses for filter
$ip_addresses = $conn->query("SELECT DISTINCT ip_address FROM activities WHERE ip_address IS NOT NULL AND ip_address != 'Unknown' ORDER BY ip_address")->fetch_all(MYSQLI_ASSOC);

// Pagination
$per_page = 50;
$current_page = max(1, $_GET['page'] ?? 1);
$total_pages = ceil($total_activities / $per_page);
$offset = ($current_page - 1) * $per_page;

// Build main query
$main_sql = "SELECT 
            a.*, 
            u.full_name, 
            u.role,
            u.username,
            DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s') as formatted_time,
            DATE_FORMAT(a.created_at, '%M %d, %Y %l:%i %p') as display_time
        " . $base_sql . " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";

// Add pagination parameters
$main_params = $params;
$main_types = $types;
$main_params[] = $per_page;
$main_params[] = $offset;
$main_types .= "ii";

// Execute main query
$stmt = $conn->prepare($main_sql);
if (!empty($main_params)) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get filter options
$users = $conn->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity Log - <?= htmlspecialchars($school_name ?? 'GEBSCO') ?></title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/forms.css">
    <style>
        .activity-log-container {
            padding: 20px;
        }
        
        .filter-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,.05); /* UPDATED: Softer shadow for modern look */
            margin-bottom: 1.5rem;
            padding: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* UPDATED: Slightly wider for better fit */
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        /* NEW/UPDATED: Enhanced form-group for better design */
        .form-group {
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        
        .form-group .form-control {
            padding: 10px 12px 10px 40px; /* UPDATED: Space for icon */
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .form-group .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); /* NEW: Focus glow */
            transform: translateY(-1px); /* NEW: Subtle lift */
        }
        
        /* NEW: Input icons */
        .form-group i.input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
            z-index: 1;
        }
        
        /* UPDATED: Make search wider */
        .form-group:has(#search) {
            flex-grow: 1; /* Allow it to expand */
        }
        
        .form-group:has(#search) .form-control {
            min-width: 250px; /* Ensure minimum width */
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .activity-badge {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-login { background: #d4edda; color: #155724; }
        .badge-create { background: #d1ecf1; color: #0c5460; }
        .badge-update { background: #fff3cd; color: #856404; }
        .badge-delete { background: #f8d7da; color: #721c24; }
        .badge-system { background: #e2e3e5; color: #383d41; }
        .badge-error { background: #f8d7da; color: #721c24; }
        
        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        .ip-local {
            color: #6c757d;
        }
        
        .ip-external {
            color: #495057;
            font-weight: 500;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .page-info {
            margin: 0 15px;
            color: #666;
        }
        
        .activity-details {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .activity-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        /* UPDATED: Mobile improvements */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .filter-actions {
                flex-direction: column; /* NEW: Stack buttons on mobile */
                width: 100%;
            }
            
            .filter-actions .btn {
                width: 100%;
            }
            
            .form-group .form-control {
                padding-left: 45px; /* Adjust for icons on smaller screens */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-history"></i> System Activity Log</h1>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="exportLog('csv')">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                        <button class="btn btn-secondary" onclick="clearOldLogs()">
                            <i class="fas fa-trash"></i> Clear Old Logs
                        </button>
                    </div>
                </div>

                <!-- Statistics Overview - UPDATED: Exclude 'view' type if present -->
                <div class="stats-overview">
                    <?php
                    $stats_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(type = 'login') as logins,
                        SUM(type = 'create') as creates,
                        SUM(type = 'update') as updates,
                        SUM(type = 'delete') as deletes,
                        COUNT(DISTINCT ip_address) as unique_ips
                    FROM activities WHERE type != 'view'"; // NEW: Exclude routine views
                    $stats_result = $conn->query($stats_sql);
                    $stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'logins' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0, 'unique_ips' => 0];
                    ?>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['total']) ?></div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['logins']) ?></div>
                        <div class="stat-label">User Logins</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['unique_ips']) ?></div>
                        <div class="stat-label">Unique IPs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['creates'] + $stats['updates'] + $stats['deletes']) ?></div>
                        <div class="stat-label">Data Changes</div>
                    </div>
                </div>

                <!-- Filters - UPDATED: Added icons and enhanced styling -->
                <div class="filter-card">
                    <form method="GET" class="filter-form">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="type">Activity Type</label>
                                <i class="fas fa-tasks input-icon"></i> <!-- NEW: Icon -->
                                <select id="type" name="type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="login" <?= $filter_type == 'login' ? 'selected' : '' ?>>Login</option>
                                    <option value="create" <?= $filter_type == 'create' ? 'selected' : '' ?>>Create</option>
                                    <option value="update" <?= $filter_type == 'update' ? 'selected' : '' ?>>Update</option>
                                    <option value="delete" <?= $filter_type == 'delete' ? 'selected' : '' ?>>Delete</option>
                                    <option value="system" <?= $filter_type == 'system' ? 'selected' : '' ?>>System</option>
                                    <option value="error" <?= $filter_type == 'error' ? 'selected' : '' ?>>Error</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="user_id">User</label>
                                <i class="fas fa-user input-icon"></i> <!-- NEW: Icon -->
                                <select id="user_id" name="user_id" class="form-control">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="ip_address">IP Address</label>
                                <i class="fas fa-globe input-icon"></i> <!-- NEW: Icon -->
                                <select id="ip_address" name="ip_address" class="form-control">
                                    <option value="">All IPs</option>
                                    <?php foreach ($ip_addresses as $ip): ?>
                                        <option value="<?= htmlspecialchars($ip['ip_address']) ?>" <?= $filter_ip == $ip['ip_address'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ip['ip_address']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Date</label>
                                <i class="fas fa-calendar input-icon"></i> <!-- NEW: Icon -->
                                <input type="date" id="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="search">Search</label>
                                <i class="fas fa-search input-icon"></i> <!-- NEW: Icon -->
                                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                                       placeholder="Search in title, description, IP..." class="form-control">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="activities_log.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Activity Table -->
                <div class="table-container">
                    <div class="table-actions">
                        <div class="table-info">
                            Showing <?= number_format(count($activities)) ?> of <?= number_format($total_activities) ?> activities
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activities)): ?>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <small><?= htmlspecialchars($activity['display_time'] ?? $activity['formatted_time'] ?? 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($activity['user_id']): ?>
                                                    <div class="user-info">
                                                        <div class="user-avatar">
                                                            <?= strtoupper(substr($activity['full_name'] ?? 'SY', 0, 2)) ?>
                                                        </div>
                                                        <div>
                                                            <div><?= htmlspecialchars($activity['full_name'] ?? 'System') ?></div>
                                                            <small class="text-muted"><?= ucfirst($activity['role'] ?? 'system') ?></small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <em>System</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="<?= htmlspecialchars($activity['icon'] ?? 'fas fa-info-circle') ?> activity-icon text-<?= 
                                                    ($activity['type'] ?? 'system') == 'login' ? 'success' :
                                                    (($activity['type'] ?? 'system') == 'create' ? 'primary' :
                                                    (($activity['type'] ?? 'system') == 'update' ? 'info' :
                                                    (($activity['type'] ?? 'system') == 'delete' ? 'danger' :
                                                    (($activity['type'] ?? 'system') == 'error' ? 'danger' : 'secondary'))))
                                                ?>"></i>
                                                <span class="activity-badge badge-<?= $activity['type'] ?? 'system' ?>">
                                                    <?= ucfirst($activity['type'] ?? 'system') ?>
                                                </span>
                                            </td>
                                            <td><strong><?= htmlspecialchars($activity['title'] ?? 'No Title') ?></strong></td>
                                            <td class="activity-details">
                                                <?= htmlspecialchars($activity['description'] ?? '-') ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($activity['ip_address']) && $activity['ip_address'] !== 'Unknown'): ?>
                                                    <span class="ip-address <?= 
                                                        ($activity['ip_address'] === '127.0.0.1' || $activity['ip_address'] === '::1') ? 'ip-local' : 'ip-external'
                                                    ?>">
                                                        <?= htmlspecialchars($activity['ip_address']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <small class="text-muted">Unknown</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="empty-state">
                                                <i class="fas fa-history fa-3x text-muted"></i>
                                                <h3>No activities found</h3>
                                                <p>No system activities match your current filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </span>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="btn btn-sm">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="js/dashboard.js"></script>
    <script>
    function exportLog(format) {
        const params = new URLSearchParams(window.location.search);
        window.open('export_activity_log.php?' + params.toString() + '&format=' + format, '_blank');
    }
    
    function clearOldLogs() {
        if (confirm('Are you sure you want to clear logs older than 90 days? This action cannot be undone.')) {
            const btn = document.querySelector('button[onclick="clearOldLogs()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
            btn.disabled = true;
            
            fetch('clear_old_logs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_old_logs'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Error: ' + error);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    }
    
    // REMOVED: Auto-refresh script to prevent unintended log increases
    </script>
</body>
</html>