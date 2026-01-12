<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

$search_query = $_GET['q'] ?? '';
$results = [];
$search_type = '';

if (!empty($search_query)) {
    $search_term = '%' . $conn->real_escape_string($search_query) . '%';
    
    // Search in users table
    $user_sql = "SELECT id, username, full_name, email, role, 'user' as type 
                 FROM users 
                 WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?
                 LIMIT 10";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $user_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Search in events table (if you have one)
    $event_sql = "SELECT id, event_title, description, event_date, 'event' as type 
                  FROM events 
                  WHERE event_title LIKE ? OR description LIKE ?
                  LIMIT 10";
    $stmt = $conn->prepare($event_sql);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $event_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $results = array_merge($user_results, $event_results);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - GEBSCO</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Search Results Styles */
.search-results {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.search-result-item {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.search-result-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.search-result-item i {
    font-size: 24px;
    margin-right: 15px;
    color: #4e73df;
}

.result-content {
    flex: 1;
}

.result-content h4 {
    margin: 0 0 8px 0;
    color: #333;
}

.result-content p {
    margin: 0 0 8px 0;
    color: #666;
}

.result-content span {
    font-size: 14px;
    color: #888;
}

.result-content a {
    color: #4e73df;
    text-decoration: none;
    font-weight: 500;
}

.result-content a:hover {
    text-decoration: underline;
}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
  
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Search Results</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Search</span>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Search for "<?= htmlspecialchars($search_query) ?>"</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($search_query)): ?>
                        <p>Please enter a search term.</p>
                    <?php elseif (empty($results)): ?>
                        <p>No results found for "<?= htmlspecialchars($search_query) ?>".</p>
                    <?php else: ?>
                        <div class="search-results">
                            <?php foreach ($results as $result): ?>
                                <div class="search-result-item">
                                    <?php if ($result['type'] === 'user'): ?>
                                        <i class="fas fa-user"></i>
                                        <div class="result-content">
                                            <h4><?= htmlspecialchars($result['full_name']) ?></h4>
                                            <p>Username: <?= htmlspecialchars($result['username']) ?> | 
                                               Email: <?= htmlspecialchars($result['email']) ?> | 
                                               Role: <?= ucfirst($result['role']) ?></p>
                                            <a href="users.php?edit=<?= $result['id'] ?>">View User</a>
                                        </div>
                                    <?php elseif ($result['type'] === 'event'): ?>
                                        <i class="fas fa-calendar-alt"></i>
                                        <div class="result-content">
                                            <h4><?= htmlspecialchars($result['title']) ?></h4>
                                            <p><?= htmlspecialchars($result['description']) ?></p>
                                            <span>Date: <?= date('M d, Y', strtotime($result['event_date'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
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
    <script src="js/dashboard.js"></script>
</body>
</html>