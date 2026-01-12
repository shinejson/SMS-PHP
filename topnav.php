<?php
// Get logged-in user's profile image
$user_id = $_SESSION['user_id'] ?? null;
$profile_image = './img/founder.jpg'; // Default image
$user_data = [];

if ($user_id) {
    require_once 'config.php';
    $sql = "SELECT profile_image, full_name, email, username, role, signature FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        if (!empty($user_data['profile_image'])) {
            $profile_image = 'uploads/users/' . $user_data['profile_image'];
        }
    }
    $stmt->close();
}

// Get notification count (events count for current academic year and term)
$notification_count = 0;
if ($user_id) {
    // First, get the current academic year ID from academic_years table
    $current_academic_year_id = null;
    $sql = "SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $year_data = $result->fetch_assoc();
        $current_academic_year_id = $year_data['id'];
    }
    $stmt->close();
    
    // Get current term ID from terms table
    $current_term_id = null;
    $current_month = date('n');
    
    // Determine term order based on current month (adjust as needed for your academic calendar)
    if ($current_month >= 1 && $current_month <= 4) {
        $term_order = 2; // Term 2
    } elseif ($current_month >= 5 && $current_month <= 8) {
        $term_order = 3; // Term 3
    } else {
        $term_order = 1; // Term 1
    }
    
    $sql = "SELECT id FROM terms WHERE term_order = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $term_order);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $term_data = $result->fetch_assoc();
        $current_term_id = $term_data['id'];
    }
    $stmt->close();
    
    // Get events count using the correct IDs
    if ($current_academic_year_id && $current_term_id) {
        $sql = "SELECT COUNT(*) as event_count FROM events 
                WHERE academic_year_id = ? AND term_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $current_academic_year_id, $current_term_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $count_data = $result->fetch_assoc();
            $notification_count = $count_data['event_count'];
        }
        $stmt->close();
    }
}

// Check if user is admin
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// ---------------------------------------------------------------------
//  Flash-message helper – put this in a shared file (e.g. topnav.php)
// ---------------------------------------------------------------------
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
<header class="top-nav">
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <form method="GET" action="search.php" class="search-form">
            <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </form>
    </div>
    
    <div class="nav-right">
        <div class="notifications" id="notificationsBtn">
            <i class="fas fa-bell"></i>
            <?php if ($notification_count > 0): ?>
                <span class="badge"><?= $notification_count ?></span>
            <?php endif; ?>
            <!-- Notifications Dropdown -->
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notifications-header">
                    <h4>Notifications</h4>
                    <span class="notifications-count"><?= $notification_count ?> events</span>
                </div>
                <div class="notifications-list">
                    <?php
                    // Get recent events for notifications
                    if ($user_id && isset($current_academic_year_id) && isset($current_term_id)) {
                        $sql = "SELECT event_title, event_date FROM events 
                                WHERE academic_year_id = ? AND term_id = ? 
                                ORDER BY event_date DESC 
                                LIMIT 5";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $current_academic_year_id, $current_term_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            while ($event = $result->fetch_assoc()) {
                                echo '<div class="notification-item">';
                                echo '<div class="notification-icon"><i class="fas fa-calendar-alt"></i></div>';
                                echo '<div class="notification-content">';
                                echo '<p class="notification-title">' . htmlspecialchars($event['event_title']) . '</p>';
                                echo '<span class="notification-time">' . date('M d, Y', strtotime($event['event_date'])) . '</span>';
                                echo '</div></div>';
                            }
                        } else {
                            echo '<div class="notification-item">';
                            echo '<div class="notification-content">';
                            echo '<p class="notification-title">No events found</p>';
                            echo '</div></div>';
                        }
                        $stmt->close();
                    } else {
                        echo '<div class="notification-item">';
                        echo '<div class="notification-content">';
                        echo '<p class="notification-title">No current academic year/term set</p>';
                        echo '</div></div>';
                    }
                    ?>
                </div>
                <div class="notifications-footer">
                    <a href="event.php">View All Events</a>
                </div>
            </div>
        </div>
        
        <div class="user-menu" id="userMenuBtn">
            <img src="<?= $profile_image ?>" alt="User Avatar" onerror="this.src='./img/founder.jpg'">
            <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
            <i class="fas fa-chevron-down"></i>
            
            <!-- User Dropdown Menu -->
            <div class="user-dropdown" id="userDropdown">
                <div class="user-info">
                    <img src="<?= $profile_image ?>" alt="User Avatar" onerror="this.src='./img/founder.jpg'">
                    <div>
                        <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
                        <span><?= ucfirst($_SESSION['role'] ?? 'User') ?></span>
                    </div>
                </div>
                <a href="#" class="dropdown-item profile-link">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <?php if ($is_admin): ?>
                <a href="school_settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> School Settings
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- User Profile Modal -->
<div id="profileModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal">&times;</span>
        <h2>User Profile</h2>
        <div class="profile-content">
            <div class="profile-header">
                <div class="profile-image">
                    <img src="<?= $profile_image ?>" alt="Profile Image" onerror="this.src='./img/founder.jpg'">
                </div>
                <div class="profile-info">
                    <h3><?= htmlspecialchars($user_data['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></h3>
                    <p class="profile-role"><?= ucfirst($user_data['role'] ?? $_SESSION['role'] ?? 'User') ?></p>
                    <p class="profile-email"><?= htmlspecialchars($user_data['email'] ?? 'No email provided') ?></p>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-item">
                    <label>Username:</label>
                    <span><?= htmlspecialchars($user_data['username'] ?? $_SESSION['username'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <label>Full Name:</label>
                    <span><?= htmlspecialchars($user_data['full_name'] ?? $_SESSION['full_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span><?= htmlspecialchars($user_data['email'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <label>Role:</label>
                    <span><?= ucfirst($user_data['role'] ?? $_SESSION['role'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($user_data['signature'])): ?>
                <div class="detail-item">
                    <label>Signature:</label>
                    <span><?= htmlspecialchars($user_data['signature']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-actions">
                <button type="button" class="btn-secondary close-profile">Close</button>
                <a href="profile.php" class="btn-primary">Edit Profile</a>
            </div>
        </div>
    </div>
</div>



<style>

/* Notifications Dropdown */
.notifications {
    position: relative;
    cursor: pointer;
    padding: 10px;
    border-radius: 50%;
    transition: background 0.2s;
}

.notifications:hover {
    background: #f8f9fa;
}

.notifications-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: none;
    z-index: 1000;
    margin-top: 5px;
}


.notifications-dropdown.show {
    display: block;
}

.notifications-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notifications-header h4 {
    margin: 0;
    flex: 1;
}

.notifications-count {
    background: #4e73df;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
}

.notifications-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f8f9fa;
    display: flex;
    align-items: center;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-icon {
    margin-right: 12px;
    color: #4e73df;
}

.notification-content {
    flex: 1;
}

.notification-title {
    margin: 0 0 4px 0;
    font-weight: 500;
    font-size: 14px;
}

.notification-time {
    font-size: 12px;
    color: #6c757d;
}

.notifications-footer {
    padding: 12px 15px;
    text-align: center;
    border-top: 1px solid #eee;
}

.notifications-footer a {
    color: #4e73df;
    text-decoration: none;
    font-weight: 500;
}

.notifications-footer a:hover {
    text-decoration: underline;
}

/* User Dropdown */
.user-menu {
    position: relative;
    cursor: pointer;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 250px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: none;
    z-index: 1000;
    margin-top: 5px;
}

.user-dropdown.show {
    display: block;
}

.user-info {
    padding: 15px;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.user-info img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 12px;
    object-fit: cover;
}

.user-info div {
    flex: 1;
}

.user-info strong {
    display: block;
    margin-bottom: 2px;
    font-size: 14px;
}

.user-info span {
    font-size: 12px;
    color: #6c757d;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #333;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8f9fa;
    color: #333;
}

.dropdown-item i {
    margin-right: 10px;
    width: 16px;
    text-align: center;
}

.dropdown-divider {
    height: 1px;
    background: #eee;
    margin: 5px 0;
}

.dropdown-item.logout {
    color: #e74a3b;
}

.dropdown-item.logout:hover {
    background: #f8d7da;
    color: #e74a3b;
}

/* Badge Styles */
.badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #e74a3b;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    animation: slideIn 0.3s;
}

.close-modal {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close-modal:hover {
    color: #000;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Profile Modal Specific Styles */
.profile-content {
    margin-top: 20px;
}

.profile-header {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.profile-image img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #4e73df;
}

.profile-info {
    margin-left: 20px;
}

.profile-info h3 {
    margin: 0 0 5px 0;
    color: #333;
}

.profile-role {
    margin: 0 0 5px 0;
    color: #6c757d;
    font-weight: 500;
}

.profile-email {
    margin: 0;
    color: #4e73df;
    font-size: 14px;
}

.profile-details {
    margin-bottom: 30px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item label {
    font-weight: 600;
    color: #495057;
    min-width: 100px;
}

.detail-item span {
    color: #6c757d;
    text-align: right;
}

.profile-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-primary, .btn-secondary {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
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

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Responsive Design */
@media (max-width: 768px) {
    .search-bar {
        min-width: 200px;
    }
    
    .user-menu span {
        display: none;
    }
    
    .notifications-dropdown,
    .user-dropdown {
        width: 300px;
        right: -50px;
    }
    
    .modal-content {
        margin: 10% auto;
        width: 90%;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-info {
        margin-left: 0;
        margin-top: 15px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .top-nav {
        background: #1a202c;
        color: white;
    }
    
    .search-bar {
        background: #2d3748;
    }
    
    .search-bar input {
        color: white;
    }
    
    .search-bar input::placeholder {
        color: #a0aec0;
    }
    
    .user-menu:hover,
    .notifications:hover {
        background: #2d3748;
    }
    
    .notifications-dropdown,
    .user-dropdown {
        background: #2d3748;
        color: white;
    }
    
    .notification-item:hover,
    .dropdown-item:hover {
        background: #4a5568;
        color: white;
    }
    
    .notifications-header,
    .user-info {
        border-bottom-color: #4a5568;
    }
    
    .dropdown-divider {
        background: #4a5568;
    }
    
    .modal-content {
        background-color: #2d3748;
        color: white;
    }
    
    .profile-info h3 {
        color: white;
    }
    
    .detail-item label {
        color: #e2e8f0;
    }
    
    .detail-item span {
        color: #a0aec0;
    }
    
    .profile-header {
        border-bottom-color: #4a5568;
    }
    
    .detail-item {
        border-bottom-color: #4a5568;
    }
    
    .close-modal:hover {
        color: #e2e8f0;
    }
}
</style>