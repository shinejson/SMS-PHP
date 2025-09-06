<?php
require_once 'config.php';
require_once 'session.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_message'] = "Please login to access this page";
    header('Location: login.php');
    exit();
}

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
                    <h1>Dashboard Overview</h1>
                    <div class="breadcrumb">
                        <a href="#">Home</a> / <span>Dashboard</span>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #4e73df;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>1,254</h3>
                            <p>Total Students</p>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 12%
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #1cc88a;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <h3>48</h3>
                            <p>Teachers</p>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 5%
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #36b9cc;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3>32</h3>
                            <p>Courses</p>
                        </div>
                        <div class="stat-trend down">
                            <i class="fas fa-arrow-down"></i> 2%
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f6c23e;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>$24,500</h3>
                            <p>Revenue</p>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 18%
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="charts-row">
                    <div class="chart-card">
                        <div class="card-header">
                            <h3>Student Enrollment</h3>
                            <div class="card-actions">
                                <select>
                                    <option>2023</option>
                                    <option>2022</option>
                                    <option>2021</option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="enrollmentChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="card-header">
                            <h3>Revenue Sources</h3>
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
                            <a href="#" class="view-all">View All</a>
                        </div>
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong>5 new students</strong> enrolled today</p>
                                    <small>2 hours ago</small>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="activity-content">
                                    <p>New course <strong>Advanced Robotics</strong> added</p>
                                    <small>5 hours ago</small>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="activity-content">
                                    <p>Upcoming event: <strong>Science Fair</strong> on June 15</p>
                                    <small>Yesterday</small>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong>3 pending applications</strong> need review</p>
                                    <small>Yesterday</small>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong>Monthly fee collection</strong> is 85% complete</p>
                                    <small>2 days ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-actions-card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="actions-grid">
                            <a href="#" class="action-item">
                                <i class="fas fa-user-plus"></i>
                                <span>Add Student</span>
                            </a>
                            
                            <a href="#" class="action-item">
                                <i class="fas fa-book-medical"></i>
                                <span>Create Course</span>
                            </a>
                            
                            <a href="#" class="action-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Schedule Event</span>
                            </a>
                            
                            <a href="#" class="action-item">
                                <i class="fas fa-envelope"></i>
                                <span>Send Notice</span>
                            </a>
                            
                            <a href="#" class="action-item">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Generate Invoice</span>
                            </a>
                            
                            <a href="#" class="action-item">
                                <i class="fas fa-chart-bar"></i>
                                <span>View Reports</span>
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
            
            <!-- Footer -->
            <footer class="dashboard-footer">
                <p>&copy; 2023 GEBSCO School Management System. All rights reserved.</p>
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