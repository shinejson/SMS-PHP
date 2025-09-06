<aside class="sidebar">
    <div class="sidebar-header">
        <img src="./img/logo.png" alt="GEBSCO Logo" width="40">
        <h2>GEBSCO</h2>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li class="active">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="students.php">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
            </li>
              <li>
                <a href="subjects.php">
                    <i class="fas fa-users"></i>
                    <span>Subjects</span>
                </a>
            </li>
            <li>
                <a href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class="fas fa-user-shield"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li>
                <a href="classes.php">
                    <i class="fas fa-book"></i>
                    <span>Classes</span>
                </a>
            </li>
   <!-- Marks/Grades Dropdown -->
<li class="dropdown">
    <a href="javascript:void(0);" class="dropdown-toggle">
        <i class="fas fa-graduation-cap"></i>
        <span>Marks/Grades</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
    </a>
    <ul class="dropdown-menu">
        <li><a href="marks.php">Raw Marks</a></li>
        <li><a href="master-Score.php">Master Sheet</a></li>
    </ul>
</li>

<!-- Transactions Dropdown -->
<li class="dropdown">
    <a href="javascript:void(0);" class="dropdown-toggle">
        <i class="fas fa-money-bill-wave"></i>
        <span>Transactions</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
    </a>
    <ul class="dropdown-menu">
        <li><a href="payments.php">Payments</a></li>
        <li><a href="view_bills.php">Billings</a></li>
    </ul>
</li>

            <li>
                <a href="events.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge">5</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="school_settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <img src="./img/founder.jpg" alt="Admin Avatar">
            <div class="user-info">
                <strong><span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span></strong>
                <small>Super Admin</small>
            </div>
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