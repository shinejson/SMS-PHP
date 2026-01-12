<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has permission (admin or staff)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    header("Location: unauthorized.php");
    exit();
}

$page_title = "Reports";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/report.css">
    <?php include 'favicon.php'; ?>
</head>
<body>
    <!-- Main Sidebar -->
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Top Navigation -->
        <?php include 'topnav.php'; ?>

        <div class="report-layout">
            <!-- Report Sidebar -->
            <aside class="report-sidebar">
                <div class="report-sidebar-header">
                    <i class="fas fa-file-alt"></i>
                    <h3>Report Categories</h3>
                </div>

                <nav class="report-nav">
                    <ul>
                        <!-- Student Reports -->
                        <li class="report-category active">
                            <a href="javascript:void(0);" class="report-toggle" data-category="student">
                                <i class="fas fa-user-graduate"></i>
                                <span>Student Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu active">
                                <li><a href="#" data-report="student-list"><i class="fas fa-list"></i> Student List</a></li>
                                <li><a href="#" data-report="attendance-summary"><i class="fas fa-calendar-check"></i> Attendance Summary</a></li>
                                <li><a href="#" data-report="student-performance"><i class="fas fa-chart-line"></i> Performance Report</a></li>
                                <li><a href="#" data-report="enrollment-report"><i class="fas fa-user-plus"></i> Enrollment Report</a></li>
                            </ul>
                        </li>

                        <!-- Financial Reports -->
                        <li class="report-category">
                            <a href="javascript:void(0);" class="report-toggle" data-category="finance">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Financial Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu">
                                <li><a href="#" data-report="payment-summary"><i class="fas fa-receipt"></i> Payment Summary</a></li>
                                <li><a href="#" data-report="outstanding-fees"><i class="fas fa-exclamation-triangle"></i> Outstanding Fees</a></li>
                                <li><a href="#" data-report="revenue-analysis"><i class="fas fa-chart-pie"></i> Revenue Analysis</a></li>
                                <li><a href="#" data-report="expense-report"><i class="fas fa-wallet"></i> Expense Report</a></li>
                            </ul>
                        </li>

                        <!-- Academic Reports -->
                        <li class="report-category">
                            <a href="javascript:void(0);" class="report-toggle" data-category="academic">
                                <i class="fas fa-graduation-cap"></i>
                                <span>Academic Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu">
                                <li><a href="#" data-report="grade-report"><i class="fas fa-star"></i> Grade Report</a></li>
                                <li><a href="#" data-report="subject-performance"><i class="fas fa-book"></i> Subject Performance</a></li>
                                <li><a href="#" data-report="class-average"><i class="fas fa-calculator"></i> Class Averages</a></li>
                                <li><a href="#" data-report="exam-analysis"><i class="fas fa-file-invoice"></i> Exam Analysis</a></li>
                            </ul>
                        </li>

                        <!-- Teacher Reports -->
                        <li class="report-category">
                            <a href="javascript:void(0);" class="report-toggle" data-category="teacher">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Teacher Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu">
                                <li><a href="#" data-report="teacher-list"><i class="fas fa-users"></i> Teacher List</a></li>
                                <li><a href="#" data-report="teacher-workload"><i class="fas fa-briefcase"></i> Workload Report</a></li>
                                <li><a href="#" data-report="teacher-attendance"><i class="fas fa-clock"></i> Attendance Report</a></li>
                            </ul>
                        </li>

                        <!-- Class Reports -->
                        <li class="report-category">
                            <a href="javascript:void(0);" class="report-toggle" data-category="class">
                                <i class="fas fa-book-open"></i>
                                <span>Class Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu">
                                <li><a href="#" data-report="class-list"><i class="fas fa-list-ul"></i> Class List</a></li>
                                <li><a href="#" data-report="class-strength"><i class="fas fa-users"></i> Class Strength</a></li>
                                <li><a href="#" data-report="subject-allocation"><i class="fas fa-clipboard-list"></i> Subject Allocation</a></li>
                            </ul>
                        </li>

                        <!-- Custom Reports -->
                        <li class="report-category">
                            <a href="javascript:void(0);" class="report-toggle" data-category="custom">
                                <i class="fas fa-cogs"></i>
                                <span>Custom Reports</span>
                                <i class="fas fa-chevron-down report-arrow"></i>
                            </a>
                            <ul class="report-submenu">
                                <li><a href="#" data-report="custom-builder"><i class="fas fa-hammer"></i> Report Builder</a></li>
                                <li><a href="#" data-report="saved-reports"><i class="fas fa-save"></i> Saved Reports</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Report Content Area -->
            <main class="report-content">
                <div class="report-header">
                    <h1 id="reportTitle">Student List Report</h1>
                    <div class="report-actions">
                        <button class="btn btn-secondary" id="printReport">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-success" id="exportExcel">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-danger" id="exportPDF">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="report-filters card">
                    <h3><i class="fas fa-filter"></i> Report Filters</h3>
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="academicYear">Academic Year</label>
                            <select id="academicYear" class="form-control">
                                <option value="">Select Academic Year</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="term">Term</label>
                            <select id="term" class="form-control">
                                <option value="">Select Term</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="class">Class</label>
                            <select id="class" class="form-control">
                                <option value="">Select Class</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="dateFrom">Date From</label>
                            <input type="date" id="dateFrom" class="form-control">
                        </div>

                        <div class="filter-group">
                            <label for="dateTo">Date To</label>
                            <input type="date" id="dateTo" class="form-control">
                        </div>

                        <div class="filter-group">
                            <button class="btn btn-primary" id="generateReport">
                                <i class="fas fa-play"></i> Generate Report
                            </button>
                            <button class="btn btn-secondary" id="resetFilters">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Display Area -->
                <div class="report-display card">
                    <div id="reportContent">
                        <!-- Default view -->
                        <div class="empty-state">
                            <i class="fas fa-file-alt fa-5x"></i>
                            <h3>Select Report Parameters</h3>
                            <p>Choose your filters and click "Generate Report" to view results</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Load html2pdf library from CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script src="js/dashboard.js"></script>
    <script src="js/report.js"></script>
</body>
</html>