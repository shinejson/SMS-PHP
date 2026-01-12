<?php
// Get the profile image from topnav if it exists
if (!isset($profile_image)) {
    $user_id = $_SESSION['user_id'] ?? null;
    $profile_image = './img/founder.jpg';
    
    if ($user_id) {
        require_once 'config.php';
        $sql = "SELECT profile_image FROM users WHERE id = ?";
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
}

?>
<aside class="sidebar">
    <?php
// Fetch school settings for the sidebar
require_once 'config.php';
$settings_sql = "SELECT school_short_name, logo FROM school_settings ORDER BY id DESC LIMIT 1";
$settings_result = $conn->query($settings_sql);
$school_settings = $settings_result->fetch_assoc();

$school_short_name = $school_settings['school_short_name'] ?? 'SET SNAME';
$school_logo = $school_settings['logo'] ?? './img/logo.png';


?>

<div class="sidebar-header">
    <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_short_name); ?> Logo" width="40" onerror="this.src='./img/logo.png'">
    <h2><?php echo htmlspecialchars($school_short_name); ?></h2>
</div>

    <nav class="sidebar-nav">
        <ul>
            <!-- Dashboard link based on role -->
            <li class="active">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="index.php">
                <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                    <a href="teacher_dashboard.php">
                <?php elseif ($_SESSION['role'] === 'staff'): ?>
                    <a href="staff_dashboard.php">
                <?php else: ?>
                    <a href="dashboard.php">
                <?php endif; ?>
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
              <!-- Marks/Grades Dropdown -->
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher'): ?>
            <li class="dropdown">
                <a href="javascript:void(0);" class="dropdown-toggle">
                    <i class="fas fa-users"></i>
                    <span>Students Records</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
              <ul class="dropdown-menu">
                    <li>
                        <a href="students.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>Students</span>
                        </a>
                    </li>
                    <li>
                        <a href="attendance.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li>
                        <a href="assignments.php">
                            <i class="fas fa-tasks"></i>
                            <span>Assignments</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher'): ?>
            <li>
                <a href="subjects.php">
                    <i class="fas fa-book-open"></i>
                    <span>Subjects</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
            <li>
                <a href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li>
                <a href="users.php">
                    <i class="fas fa-user-shield"></i>
                    <span>User Management</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'staff'): ?>
            <li>
                <a href="classes.php">
                    <i class="fas fa-book"></i>
                    <span>Classes</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Marks/Grades Dropdown -->
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher'): ?>
            <li class="dropdown">
                <a href="javascript:void(0);" class="dropdown-toggle">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Marks/Grades</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
               <ul class="dropdown-menu">
                <li><a href="marks.php"><i class="fas fa-edit"></i> <span>Raw Marks</span></a></li>
                <li><a href="master-Score.php"><i class="fas fa-file-invoice"></i> <span>Master Sheet</span></a></li>
            </ul>
            </li>
            <?php endif; ?>

            <!-- Transactions Dropdown -->
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
            <li class="dropdown">
                <a href="javascript:void(0);" class="dropdown-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Transactions</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
         <ul class="dropdown-menu">
                <li><a href="payments.php"><i class="fas fa-receipt"></i> <span>Payments</span></a></li>
                <li><a href="view_bills.php"><i class="fas fa-file-invoice-dollar"></i> <span>Billings</span></a></li>
                <li><a href="payment-ladger.php"><i class="fas fa-book"></i> <span>Ledger</span></a></li>
                <li><a href="accounts.php"><i class="fas fa-university"></i> <span>Account</span></a></li>
            </ul>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
            <li>
                <a href="event.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
            </li>
            <?php endif; ?>

            <li>
                <a href="messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge">5</span>
                </a>
            </li>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
            <li>
                <a href="report.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li>
                <a href="school_settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

<div class="sidebar-footer">
    <div class="user-profile">
        <?php
        // Use the same profile image from topnav or default
        $sidebar_profile_image = $profile_image ?? './img/founder.jpg';
        $sidebar_full_name = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User';
        $sidebar_role = $_SESSION['role'] ?? 'User';
        ?>
        <img src="<?= $sidebar_profile_image ?>" alt="User Avatar" onerror="this.src='./img/founder.jpg'">
    </div>
    <div class="dark-mode-toggle">
        <i class="fas fa-moon"></i>
        <label class="switch">
            <input type="checkbox" id="darkModeToggle">
            <span class="slider round"></span>
        </label>
    </div>
    <a href="logout.php" class="logout-btn" id="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
    </a>
</div>
</aside>
<script>
    // Session timeout management
class SessionManager {
    constructor() {
        this.timeoutMinutes = 30; // 30 minutes
        this.timeoutMs = this.timeoutMinutes * 60 * 1000;
        this.warningMinutes = 5; // Show warning 5 minutes before timeout
        this.warningMs = this.warningMinutes * 60 * 1000;
        this.logoutUrl = '/gebsco/logout.php';
        this.checkInterval = 60000; // Check every minute
        
        this.init();
    }

    init() {
        this.resetActivityTimer();
        this.setupActivityListeners();
        this.startTimer();
        
        console.log('🕒 Session manager initialized - Timeout:', this.timeoutMinutes + ' minutes');
    }

    resetActivityTimer() {
        this.lastActivity = Date.now();
        localStorage.setItem('lastActivity', this.lastActivity);
    }

    setupActivityListeners() {
        // Track user activity
        const activities = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        activities.forEach(event => {
            document.addEventListener(event, () => {
                this.resetActivityTimer();
                this.hideWarning(); // Hide warning if user becomes active again
            }, { passive: true });
        });

        // Also track visibility changes (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.resetActivityTimer();
                this.checkSession();
            }
        });
    }

    startTimer() {
        setInterval(() => {
            this.checkSession();
        }, this.checkInterval);
    }

    checkSession() {
        const now = Date.now();
        const lastActivity = parseInt(localStorage.getItem('lastActivity')) || now;
        const idleTime = now - lastActivity;

        // Show warning 5 minutes before timeout
        if (idleTime > (this.timeoutMs - this.warningMs) && idleTime < this.timeoutMs) {
            const minutesLeft = Math.ceil((this.timeoutMs - idleTime) / 60000);
            this.showWarning(minutesLeft);
        }

        // Logout when timeout reached
        if (idleTime >= this.timeoutMs) {
            this.logout();
        }
    }

    showWarning(minutesLeft) {
        // Don't show multiple warnings
        if (document.getElementById('session-warning')) return;

        const warning = document.createElement('div');
        warning.id = 'session-warning';
        warning.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #f39c12;
                color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                max-width: 300px;
                font-family: Arial, sans-serif;
            ">
                <h4 style="margin: 0 0 10px 0;">Session Timeout Warning</h4>
                <p style="margin: 0 0 15px 0;">Your session will expire in ${minutesLeft} minute(s).</p>
                <div style="display: flex; gap: 10px;">
                    <button onclick="sessionManager.extendSession()" style="
                        padding: 8px 16px;
                        background: #27ae60;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Stay Logged In</button>
                    <button onclick="sessionManager.logout()" style="
                        padding: 8px 16px;
                        background: #e74c3c;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Logout Now</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(warning);

        // Auto-remove after 10 seconds if no interaction
        setTimeout(() => {
            this.hideWarning();
        }, 10000);
    }

    hideWarning() {
        const warning = document.getElementById('session-warning');
        if (warning) {
            warning.remove();
        }
    }

    extendSession() {
        this.resetActivityTimer();
        this.hideWarning();
        
        // Optionally ping server to extend PHP session
        this.pingServer();
        
        // Show confirmation
        this.showMessage('Session extended!');
    }

    pingServer() {
        // Send a request to keep PHP session alive
        fetch('/gebsco/api/ping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('🔄 Session extended on server');
            }
        })
        .catch(error => {
            console.error('Ping failed:', error);
        });
    }

    logout() {
        this.hideWarning();
        
        // Show logout message
        this.showMessage('Logging out due to inactivity...', 'warning');
        
        // Redirect to logout after a brief delay
        setTimeout(() => {
            window.location.href = this.logoutUrl + '?timeout=1';
        }, 1000);
    }

    showMessage(message, type = 'info') {
        // Remove existing message
        const existingMsg = document.getElementById('session-message');
        if (existingMsg) existingMsg.remove();

        const bgColor = type === 'warning' ? '#e74c3c' : '#2ecc71';
        
        const messageEl = document.createElement('div');
        messageEl.id = 'session-message';
        messageEl.textContent = message;
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: ${bgColor};
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            z-index: 10001;
            font-family: Arial, sans-serif;
        `;
        
        document.body.appendChild(messageEl);
        
        setTimeout(() => {
            if (messageEl.parentNode) {
                messageEl.remove();
            }
        }, 3000);
    }
}

// Initialize session manager
const sessionManager = new SessionManager();
</script>

