<?php

// master_marks.php
require_once 'config.php';
require_once 'session.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Load classes data
$classes = [];
$classesSql = "SELECT id, class_name FROM classes ORDER BY class_name";
$classesResult = $conn->query($classesSql);
if ($classesResult && $classesResult->num_rows > 0) {
    while ($row = $classesResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Load subjects data
$subjects = [];
$subjectsSql = "SELECT id as subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjectsResult = $conn->query($subjectsSql);
if ($subjectsResult && $subjectsResult->num_rows > 0) {
    while ($row = $subjectsResult->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Load students data
$students = [];
$studentsSql = "SELECT s.id as student_id, s.first_name, s.last_name, s.class_id, c.class_name 
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id 
                ORDER BY c.class_name, s.first_name, s.last_name";
$studentsResult = $conn->query($studentsSql);
if ($studentsResult && $studentsResult->num_rows > 0) {
    while ($row = $studentsResult->fetch_assoc()) {
        $students[] = $row;
    }
}

// Load terms data
$terms = [];
$termsSql = "SELECT id, term_name FROM terms ORDER BY id";
$termsResult = $conn->query($termsSql);
if ($termsResult && $termsResult->num_rows > 0) {
    while ($row = $termsResult->fetch_assoc()) {
        $terms[] = $row;
    }
}

// Load academic years data
$academicYears = [];
$academicYearsSql = "SELECT id, year_name FROM academic_years ORDER BY year_name DESC";
$academicYearsResult = $conn->query($academicYearsSql);
if ($academicYearsResult && $academicYearsResult->num_rows > 0) {
    while ($row = $academicYearsResult->fetch_assoc()) {
        $academicYears[] = $row;
    }
}

// Load weights
function loadWeights($conn) {
    $sql = "SELECT mid_weight, class_weight, exam_weight FROM marks_weights LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // Default weights if none exist
    return [
        'mid_weight' => 10,
        'class_weight' => 20,
        'exam_weight' => 70
    ];
}

$weights = loadWeights($conn);
$midWeight = (int)$weights['mid_weight'];
$classWeight = (int)$weights['class_weight'];
$examWeight = (int)$weights['exam_weight'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Marks Sheet - GEBSCO</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/marks.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/dropdown.css">
   <link rel="stylesheet" href="css/master-score.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="master-marks-page">
            <a href="marks.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Marks Management
            </a>
            
            <div class="page-header">
                <h1>Master Marks Sheet</h1>
                <p class="text-muted">View and manage all student marks in one place. Click on student names to view their report sheet.</p>
                
                <div class="weights-info">
                    <div class="weight-item">
                        <strong>Midterm Weight:</strong> <?php echo $midWeight; ?>%
                    </div>
                    <div class="weight-item">
                        <strong>Class Score Weight:</strong> <?php echo $classWeight; ?>%
                    </div>
                    <div class="weight-item">
                        <strong>Exam Weight:</strong> <?php echo $examWeight; ?>%
                    </div>
                </div>
            </div>
            
            <div class="filter-section">
                <div class="filter-group">
                    <label for="classFilterMaster">Filter by Class</label>
                    <select id="classFilterMaster">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_name']); ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="termFilterMaster">Filter by Term</label>
                    <select id="termFilterMaster">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?php echo htmlspecialchars($term['term_name']); ?>">
                                <?php echo htmlspecialchars($term['term_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="yearFilterMaster">Filter by Academic Year</label>
                    <select id="yearFilterMaster">
                        <option value="">All Academic Years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year['year_name']); ?>">
                                <?php echo htmlspecialchars($year['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="studentFilterMaster">Filter by Student</label>
                    <select id="studentFilterMaster">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button class="btn-clear-filters" id="clearFiltersBtnMaster">
                    <i class="fas fa-sync-alt"></i> Clear Filters
                </button>
            </div>
            
            <div class="table-container">
                <div class="table-responsive">
                    <!-- In the table header, add the Rank column -->
            <table id="masterMarksTable" class="table table-striped table-bordered w-100">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Term</th>
                        <th>Academic Year</th>
                        <th>Midterm Total</th>
                        <th>Midterm Weighted</th>
                        <th>Class Score Total</th>
                        <th>Class Score Weighted</th>
                        <th>Exam Score Total</th>
                        <th>Exam Score Weighted</th>
                        <th>Final Grade</th>
                        <th>Rank</th> <!-- Add this column -->
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be populated by JavaScript -->
                </tbody>
            </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
        <script>
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
    <script>
        // Master Marks Table functionality
        let masterMarksTable = null;
        
        $(document).ready(function() {
            initMasterMarksTable();
        });

        function initMasterMarksTable() {
            // Show loading state
            $('#masterMarksTable tbody').html('<tr><td colspan="14" class="text-center">Loading data...</td></tr>');
            
            // Fetch data from server
            fetch('get_master_marks.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Check if we got an error response
                    if (data.error) {
                        $('#masterMarksTable tbody').html('<tr><td colspan="14" class="text-center">Error: ' + data.error + '</td></tr>');
                        return;
                    }
                    
                    if (data.length === 0) {
                        $('#masterMarksTable tbody').html('<tr><td colspan="14" class="text-center">No data available</td></tr>');
                        return;
                    }
                    
                    console.log('Loaded ' + data.length + ' records'); // Debug log
                    
                    // Initialize DataTable
                    masterMarksTable = $('#masterMarksTable').DataTable({
                        data: data,
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: [
                            'copy', 'excel', 'pdf', 'print'
                        ],
                        pageLength: 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
// In the DataTable columns definition, add the rank column:
columns: [
    { 
        data: 'student_name',
        render: function(data, type, row) {
            if (type === 'display') {
                // Use the actual IDs from the row data
                return `<a href="report_sheet.php?student_id=${row.student_id}&term_id=${row.term_id}&academic_year_id=${row.academic_year_id}" 
                           class="student-link" target="_blank">
                           ${data} <i class="fas fa-external-link-alt"></i>
                        </a>`;
            }
            return data;
        }
    },
    { data: 'class_name' },
    { data: 'subject_name' },
    { data: 'term' },
    { data: 'academic_year' },
    { data: 'midterm_total', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'midterm_weighted', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'class_score_total', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'class_score_weighted', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'exam_score_total', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'exam_score_weighted', render: $.fn.dataTable.render.number(',', '.', 2) },
    { data: 'final_grade', render: $.fn.dataTable.render.number(',', '.', 2) },
    { 
        data: 'rank',
        render: function(data, type, row) {
            if (type === 'display') {
                // Add badge styling for rank
                let badgeClass = 'badge-secondary';
                if (data === 1) badgeClass = 'badge-gold';
                else if (data === 2) badgeClass = 'badge-silver';
                else if (data === 3) badgeClass = 'badge-bronze';
                
                return `<span class="badge ${badgeClass}">${data}</span>`;
            }
            return data;
        }
    },
    { 
        data: 'grade',
        render: function(data, type, row) {
            if (type === 'display') {
                let badgeClass = 'badge-secondary';
                switch(data) {
                    case 'A': badgeClass = 'badge-success'; break;
                    case 'B': badgeClass = 'badge-primary'; break;
                    case 'C': badgeClass = 'badge-info'; break;
                    case 'D': badgeClass = 'badge-warning'; break;
                    case 'F': badgeClass = 'badge-danger'; break;
                }
                return `<span class="badge ${badgeClass}">${data}</span>`;
            }
            return data;
        }
    },
    { data: 'remark' }
],
                        initComplete: function() {
                            // Add custom filtering
                            setupMasterTableFilters();
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading master marks data:', error);
                    $('#masterMarksTable tbody').html('<tr><td colspan="14" class="text-center">Error loading data. Please check the console for details.</td></tr>');
                });
        }
        
            function setupMasterTableFilters() {
                // Class filter (now column 1)
                $('#classFilterMaster').on('change', function() {
                    masterMarksTable.column(1).search(this.value).draw();
                });
                
                // Term filter (now column 3)
                $('#termFilterMaster').on('change', function() {
                    masterMarksTable.column(3).search(this.value).draw();
                });
                
                // Academic Year filter (now column 4)
                $('#yearFilterMaster').on('change', function() {
                    masterMarksTable.column(4).search(this.value).draw();
                });
                
                // Student filter (column 0)
                $('#studentFilterMaster').on('change', function() {
                    masterMarksTable.column(0).search(this.value).draw();
                });
                
                // Clear filters
                $('#clearFiltersBtnMaster').on('click', function() {
                    $('#classFilterMaster, #termFilterMaster, #yearFilterMaster, #studentFilterMaster').val('');
                    masterMarksTable.columns().search('').draw();
                });
            }

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