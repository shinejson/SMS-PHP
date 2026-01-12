
<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Load weights from database with fallback defaults
 */
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

/**
 * Validate weight percentages
 */
function validateWeights($mid, $class, $exam) {
    $errors = [];
    
    if (!is_numeric($mid) || $mid < 0 || $mid > 100) {
        $errors[] = "Midterm weight must be between 0-100";
    }
    if (!is_numeric($class) || $class < 0 || $class > 100) {
        $errors[] = "Class weight must be between 0-100";
    }
    if (!is_numeric($exam) || $exam < 0 || $exam > 100) {
        $errors[] = "Exam weight must be between 0-100";
    }
    
    $total = $mid + $class + $exam;
    if ($total !== 100) {
        $errors[] = "Total weight must equal 100%. Currently: {$total}%";
    }
    
    return $errors;
}

/**
 * Calculate weighted score
 */
function calculateWeightedScore($midterm, $classScore, $exam, $weights) {
    $midWeightPercent = $weights['mid_weight'] / 100;
    $classWeightPercent = $weights['class_weight'] / 100;
    $examWeightPercent = $weights['exam_weight'] / 100;
    
    return round(
        ($midterm * $midWeightPercent) +
        ($classScore * $classWeightPercent) +
        ($exam * $examWeightPercent),
        2
    );
}

/**
 * Load grading scale from database with caching
 */
function loadGradingScale($conn) {
    static $gradingScale = null;
    
    if ($gradingScale === null) {
        $sql = "SELECT id, grade, min_mark, max_mark, remark 
                FROM remarks 
                ORDER BY min_mark DESC";
        $result = $conn->query($sql);
        
        $gradingScale = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $gradingScale[] = [
                    'id' => $row['id'],
                    'grade' => $row['grade'],
                    'min_mark' => (float)$row['min_mark'],
                    'max_mark' => (float)$row['max_mark'],
                    'remark' => $row['remark']
                ];
            }
        }
        
        // Fallback if no grading scale found in database
        if (empty($gradingScale)) {
            $gradingScale = [
                ['grade' => 'A', 'min_mark' => 80, 'max_mark' => 100, 'remark' => 'Excellent'],
                ['grade' => 'B', 'min_mark' => 70, 'max_mark' => 79.99, 'remark' => 'Very Good'],
                ['grade' => 'C', 'min_mark' => 60, 'max_mark' => 69.99, 'remark' => 'Good'],
                ['grade' => 'D', 'min_mark' => 50, 'max_mark' => 59.99, 'remark' => 'Satisfactory'],
                ['grade' => 'F', 'min_mark' => 0, 'max_mark' => 49.99, 'remark' => 'Needs Improvement']
            ];
        }
    }
    
    return $gradingScale;
}

/**
 * Get grade from score using database grading scale
 */
function getGradeFromScore($score, $conn) {
    $gradingScale = loadGradingScale($conn);
    
    foreach ($gradingScale as $grade) {
        if ($score >= $grade['min_mark'] && $score <= $grade['max_mark']) {
            return $grade['grade'];
        }
    }
    
    // Fallback
    return 'F';
}

/**
 * Get remark from score using database grading scale
 */
function getRemarkFromScore($score, $conn) {
    $gradingScale = loadGradingScale($conn);
    
    foreach ($gradingScale as $grade) {
        if ($score >= $grade['min_mark'] && $score <= $grade['max_mark']) {
            return $grade['remark'];
        }
    }
    
    // Fallback
    return 'Needs Improvement';
}

/**
 * Save or update weights
 */
function saveWeights($conn, $midWeight, $classWeight, $examWeight) {
    try {
        // Validate weights
        $errors = validateWeights($midWeight, $classWeight, $examWeight);
        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            return false;
        }
        
        // Check if weights record exists
        $checkSql = "SELECT id FROM marks_weights LIMIT 1";
        $checkResult = $conn->query($checkSql);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Update existing record
            $row = $checkResult->fetch_assoc();
            $stmt = $conn->prepare("UPDATE marks_weights SET mid_weight = ?, class_weight = ?, exam_weight = ? WHERE id = ?");
            $stmt->bind_param("iiii", $midWeight, $classWeight, $examWeight, $row['id']);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO marks_weights (mid_weight, class_weight, exam_weight) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $midWeight, $classWeight, $examWeight);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $_SESSION['message'] = "Weights saved successfully!";
            return true;
        } else {
            $_SESSION['error'] = "Failed to save weights";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error saving weights: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while saving weights";
        return false;
    }
}

// Load current weights
$weights = loadWeights($conn);
$midWeight = (int)$weights['mid_weight'];
$classWeight = (int)$weights['class_weight'];
$examWeight = (int)$weights['exam_weight'];

// Handle weight form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weights'])) {
    $newMidWeight = (int)$_POST['mid_weight'];
    $newClassWeight = (int)$_POST['class_weight'];
    $newExamWeight = (int)$_POST['exam_weight'];
    
    if (saveWeights($conn, $newMidWeight, $newClassWeight, $newExamWeight)) {
        // Update current weights if save successful
        $midWeight = $newMidWeight;
        $classWeight = $newClassWeight;
        $examWeight = $newExamWeight;
    }
    
    header("Location: marks.php");
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

$academicYears = [];
$academicYearsSql = "SELECT id, year_name FROM academic_years ORDER BY year_name DESC";
$academicYearsResult = $conn->query($academicYearsSql);
if ($academicYearsResult && $academicYearsResult->num_rows > 0) {
    while ($row = $academicYearsResult->fetch_assoc()) {
        $academicYears[] = $row;
    }
}
// Load and process marks data with improved query
$marks = [];
$marksSql = "
    SELECT 
        s.id AS student_id,
        s.first_name, 
        s.last_name,
        s.student_id as student_code,
        s.class_id,
        c.class_name,
        subj.id as subject_id,
        subj.subject_name,
        t.id as term_id,
        t.term_name AS term,
        ay.id as academic_year_id,
        ay.year_name,
        COALESCE(m.total_marks, 0) AS mid_marks,
        COALESCE(cs.total_marks, 0) AS class_marks,
        COALESCE(e.total_marks, 0) AS exam_marks
    FROM students s
    CROSS JOIN subjects subj
    CROSS JOIN terms t
    CROSS JOIN academic_years ay  -- Add academic years table
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN midterm_marks m ON m.student_id = s.id AND m.subject_id = subj.id AND m.term_id = t.id AND m.academic_year_id = ay.id
    LEFT JOIN class_score_marks cs ON cs.student_id = s.id AND cs.subject_id = subj.id AND cs.term_id = t.id AND cs.academic_year_id = ay.id
    LEFT JOIN exam_score_marks e ON e.student_id = s.id AND e.subject_id = subj.id AND e.term_id = t.id AND e.academic_year_id = ay.id
    WHERE (m.id IS NOT NULL OR cs.id IS NOT NULL OR e.id IS NOT NULL)
    ORDER BY ay.year_name DESC, s.first_name, s.last_name, subj.subject_name, t.id
";

$marksResult = $conn->query($marksSql);
if ($marksResult && $marksResult->num_rows > 0) {
    // Group marks by class, term, and subject to calculate ranks
    $groupedMarks = [];
    
    while ($row = $marksResult->fetch_assoc()) {
        // Calculate weighted total in PHP instead of complex SQL
        $totalScore = calculateWeightedScore(
            $row['mid_marks'],
            $row['class_marks'],
            $row['exam_marks'],
            $weights
        );
        
        $row['total_marks'] = $totalScore;
        $row['grade'] = getGradeFromScore($totalScore, $conn);
        $row['remark'] = getRemarkFromScore($totalScore, $conn);
        
        // Group by class, term, and subject for ranking
        $groupKey = $row['class_id'] . '_' . $row['term_id'] . '_' . $row['subject_id'];
        $groupedMarks[$groupKey][] = $row;
        
        $marks[] = $row;
    }
    
    // Calculate ranks within each group
    foreach ($groupedMarks as $groupKey => $group) {
        // Sort by total marks descending
        usort($group, function($a, $b) {
            return $b['total_marks'] <=> $a['total_marks'];
        });
        
        // Assign ranks
        $rank = 1;
        $prevScore = null;
        $sameRankCount = 0;
        
        foreach ($group as $index => $studentMark) {
            $currentScore = $studentMark['total_marks'];
            
            if ($prevScore !== null && $currentScore == $prevScore) {
                $sameRankCount++;
            } else {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            }
            
            // Find the corresponding mark in the main marks array and add rank
            foreach ($marks as &$mark) {
                if ($mark['student_id'] == $studentMark['student_id'] && 
                    $mark['subject_id'] == $studentMark['subject_id'] && 
                    $mark['term_id'] == $studentMark['term_id']) {
                    $mark['rank'] = $rank;
                    break;
                }
            }
            
            $prevScore = $currentScore;
        }
    }
    
    unset($mark); // Break the reference
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/marks.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
    <link rel="stylesheet" href="css/dropdown.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Marks/Grades Management</h1>
                <div class="breadcrumb">
                    <a href="index.php">Home</a> / <span>Marks</span>
                </div>
            </div>
                        <!-- message Configuration Section -->
<div class="message-container">
    <?php if (isset($_SESSION['message'])): ?>
        <?php 
        $messageType = $_SESSION['message_type'] ?? 'info';
        $alertClass = '';
        
        switch ($messageType) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'info':
                $alertClass = 'alert-info';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
        }
        ?>
        <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php 
        unset($_SESSION['message']); 
        unset($_SESSION['message_type']); 
        ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
</div>

            
            <!-- Weight Configuration Section -->
            <div class="card">
                <div class="card-header">
                    <h3>Configure Mark Weights</h3>
                    <p>Set the percentage weight for each mark component. Total must equal 100%.</p>
                </div>
                <div class="card-body">
                    <form method="POST" class="weight-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="weight-group">
                            <label for="mid_weight">Midterm Weight (%)</label>
                            <input type="number" name="mid_weight" id="mid_weight" 
                                   value="<?php echo $midWeight; ?>" min="0" max="100" required>
                        </div>
                        
                        <div class="weight-group">
                            <label for="class_weight">Class Score Weight (%)</label>
                            <input type="number" name="class_weight" id="class_weight" 
                                   value="<?php echo $classWeight; ?>" min="0" max="100" required>
                        </div>
                        
                        <div class="weight-group">
                            <label for="exam_weight">Exam Weight (%)</label>
                            <input type="number" name="exam_weight" id="exam_weight" 
                                   value="<?php echo $examWeight; ?>" min="0" max="100" required>
                        </div>

                        <div id="weight-total" class="weight-total">
                            Total: <?php echo ($midWeight + $classWeight + $examWeight); ?>%
                        </div>

                        <div class="weight-actions">
                            <button type="submit" name="save_weights" id="saveBtn" class="btn-save">
                                Save Weights
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Marks Management Section -->
            <div class="card">
                <div class="card-header">
                    <h3>Student Marks Summary</h3>
                    <div class="marks-legend">
                        <span>Midterm: <?php echo $midWeight; ?>%</span>
                        <span>Class: <?php echo $classWeight; ?>%</span>
                        <span>Exam: <?php echo $examWeight; ?>%</span>
                    </div>

                    <div class="card-header">
                        <button id="addMarkBtn" class="btn-primary"> <i class="fas fa-plus"></i>Add Marks</button>
                        <button id="viewScoresBtn" class="btn-secondary"> <i class="fas fa-eye"></i>View Raw Scores</button>
                  </div>
                    <!-- Class Filter Dropdown -->
   <div class="filter-controls">
    <div class="filter-group">
        <label for="classFilter">Filter by Class:</label>
        <select id="classFilter">
            <option value="">All Classes</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo htmlspecialchars($class['id']); ?>">
                    <?php echo htmlspecialchars($class['class_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="subjectFilter">Filter by Subject:</label>
        <select id="subjectFilter">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $subject): ?>
                <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>">
                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="studentFilter">Filter by Student:</label>
        <select id="studentFilter">
            <option value="">All Students</option>
            <?php foreach ($students as $student): ?>
                <option value="<?php echo htmlspecialchars($student['student_id']); ?>">
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="yearFilter">Academic Year:</label>
        <select id="yearFilter" class="filter-select">
            <option value="">All Academic Years</option>
            <?php 
            $yearQuery = "SELECT * FROM academic_years ORDER BY year_name DESC";
            $yearResult = mysqli_query($conn, $yearQuery);
            while ($year = mysqli_fetch_assoc($yearResult)) {
                $isCurrent = $year['is_current'] ? 'data-is-current="1"' : '';
                echo "<option value='{$year['academic_year_id']}' $isCurrent>{$year['year_name']}</option>";
            }
            ?>
        </select>
    </div>
    
    <button id="clearFilters" class="btn-clear-filters">Clear Filters</button>
</div>
                    <div class="filter-group">
                    <button id="clearFilters" class="btn-clear-filters">Clear Filters</button>
                    </div>
                </div>
                
                <div class="card-body">
                    <table id="marksTable" class="display">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Term</th>
                                <th>Academic Year</th>
                                <th>Total Score</th>
                                <th>Grade</th>
                                <th>Rank</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marks as $mark): ?>
                            <tr data-class-id="<?php echo htmlspecialchars($mark['class_id']); ?>">
                                <td><?php echo htmlspecialchars($mark['first_name'] . ' ' . $mark['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($mark['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($mark['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($mark['year_name']); ?></td>
                                <td><?php echo htmlspecialchars($mark['term']); ?></td>
                                <td><strong><?php echo number_format($mark['total_marks'], 2); ?></strong></td>
                                <td>
                                    <span class="grade-badge grade-<?php echo strtolower($mark['grade']); ?>">
                                        <?php echo $mark['grade']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rank-badge">
                                        <?php echo isset($mark['rank']) ? $mark['rank'] : 'N/A'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($mark['remark']); ?></td>
                                <td>
                            <button class="btn-icon view-scores view-details"  
                                    data-student-id="<?php echo $mark['student_id']; ?>"
                                    data-student-name="<?php echo htmlspecialchars($mark['first_name'] . ' ' . $mark['last_name']); ?>"
                                    data-class-name="<?php echo htmlspecialchars($mark['class_name']); ?>"
                                    data-subject-id="<?php echo $mark['subject_id']; ?>"
                                    data-subject-name="<?php echo htmlspecialchars($mark['subject_name']); ?>"
                                    data-term-id="<?php echo $mark['term_id']; ?>"
                                    data-term-name="<?php echo htmlspecialchars($mark['term']); ?>"
                                    data-year-name="<?php echo htmlspecialchars($mark['year_name']); ?>" 
                                    data-mid-marks="<?php echo $mark['mid_marks']; ?>"
                                    data-class-marks="<?php echo $mark['class_marks']; ?>"
                                    data-exam-marks="<?php echo $mark['exam_marks']; ?>"
                                    data-total-marks="<?php echo $mark['total_marks']; ?>"
                                    data-grade="<?php echo $mark['grade']; ?>"
                                    data-rank="<?php echo isset($mark['rank']) ? $mark['rank'] : 'N/A'; ?>"
                                    data-remark="<?php echo htmlspecialchars($mark['remark']); ?>"
                                    title="View Scores">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                                   
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Marks Modal -->
<div id="addMarkModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close">&times;</span>
        <h2 style="margin-bottom: 1.5rem;">Add Student Marks</h2>
        <form id="marksForm" method="POST" action="marks_control.php">
            <input type="hidden" name="form_action" value="add_marks">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="academic_year_id">Academic Year</label>
                    <select id="academic_year_id" name="academic_year_id" required style="padding: 8px 10px; font-size: 0.9rem;">
                        <option value="">Select Academic Year</option>
                        <?php 
                        $academicYearsSql = "SELECT id, year_name FROM academic_years ORDER BY year_name DESC";
                        $academicYearsResult = $conn->query($academicYearsSql);
                        if ($academicYearsResult && $academicYearsResult->num_rows > 0) {
                            while ($year = $academicYearsResult->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($year['id']) . '">' . 
                                     htmlspecialchars($year['year_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="term_id">Term</label>
                    <select id="term_id" name="term_id" required style="padding: 8px 10px; font-size: 0.9rem;">
                        <option value="">Select Term</option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?php echo htmlspecialchars($term['id']); ?>">
                                <?php echo htmlspecialchars($term['term_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id" required style="padding: 8px 10px; font-size: 0.9rem;">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required disabled style="padding: 8px 10px; font-size: 0.9rem;">
                        <option value="">Select class first</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="mark_type">Mark Type</label>
                <select id="mark_type" name="mark_type" required style="padding: 8px 10px; font-size: 0.9rem;">
                    <option value="">Select Mark Type</option>
                    <option value="midterm">Midterm</option>
                    <option value="class_score">Class Score</option>
                    <option value="exam_score">Exams Score</option>
                </select>
            </div>

            <div id="subject-fields-container" style="display:none; margin-bottom: 15px;">
                <!-- Dynamic subject fields will be added here -->
            </div>
            
            <div style="margin-bottom: 20px; text-align: center;">
                <button type="button" class="btn-secondary" id="addSubjectBtn" style="display:none; padding: 8px 15px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> Add More Subject
                </button>
            </div>

            <div class="form-actions" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--gray-300);">
                <button type="button" class="btn-cancel" style="padding: 8px 16px; font-size: 0.9rem;">Cancel</button>
                <button type="submit" class="btn-submit" style="padding: 8px 20px; font-size: 0.9rem;">Save Marks</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Mark Modal -->
<!-- Score Details Modal -->
<div id="scoreModal" class="modal">
    <div class="modal-content score-modal">
        <span class="close">&times;</span>
        <h2>Score Details</h2>
        <div id="scoreDetailsContent">
            <div class="student-info">
                <h3 id="scoreStudentName"></h3>
                <p><strong>Class:</strong> <span id="scoreClassName"></span></p>
                <p><strong>Subject:</strong> <span id="scoreSubjectName"></span></p>
                <p><strong>Academic Year:</strong> <span id="scoreYearName"></span></p> <!-- Add this line -->
                <p><strong>Term:</strong> <span id="scoreTermName"></span></p>
            </div>
            
            <!-- Rest of your score modal content remains the same -->
            <div class="score-details">
                <table>
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Score</th>
                            <th>Weight</th>
                            <th>Weighted Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Midterm</td>
                            <td id="midtermScore"></td>
                            <td><?php echo $midWeight; ?>%</td>
                            <td id="midtermWeighted"></td>
                        </tr>
                        <tr>
                            <td>Class Score</td>
                            <td id="classScore"></td>
                            <td><?php echo $classWeight; ?>%</td>
                            <td id="classWeighted"></td>
                        </tr>
                        <tr>
                            <td>Exam</td>
                            <td id="examScore"></td>
                            <td><?php echo $examWeight; ?>%</td>
                            <td id="examWeighted"></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="total-score">
                    Total Score: <span id="totalScoreDisplay"></span>
                </div>
                
                <div class="grade-info">
                    <p><strong>Grade:</strong> <span id="scoreGrade"></span></p>
                    <p><strong>Rank:</strong> <span id="scoreRank"></span></p>
                    <p><strong>Remarks:</strong> <span id="scoreRemark"></span></p>
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

    <!-- Delete Confirmation Modal -->
<!-- Delete Mark Modal -->
<div id="deleteMarkModal" class="modal">
  <div class="modal-content">
    <form id="deleteMarkForm" action="marks_control.php" method="POST">
      <input type="hidden" name="action" value="delete_mark">
      <input type="hidden" id="delete_student_id" name="student_id">
      <input type="hidden" id="delete_subject_id" name="subject_id">
      <input type="hidden" id="delete_term_id" name="term_id">
      <input type="hidden" id="delete_year_id" name="academic_year_id">
      <input type="hidden" id="delete_mark_type" name="mark_type">

      <p id="deleteConfirmationText">
        Are you sure you want to delete this mark?
      </p>
      
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary btn-cancel">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- View Mark Modal -->

    <!-- View Marks Details Modal -->
    <div id="viewMarkModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Marks Details</h2>
            <div id="marksDetailsContent">
                <!-- Details will be loaded here via AJAX -->
                <div class="loading-spinner">Loading marks details...</div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel">Close</button>
            </div>
        </div>
    </div>
    
    <?php include 'view-rawScore.php'?>
    <!-- Scripts -->


        <script>
        const subjectsData = <?php echo json_encode($subjects); ?>;
        const studentsData = <?php echo json_encode($students); ?>;
        const midWeight = <?php echo $midWeight; ?>;
        const classWeight = <?php echo $classWeight; ?>;
        const examWeight = <?php echo $examWeight; ?>;
    </script>
    <!-- Add this script tag before including marks.js -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="js/marks.js"></script>
    <script src="js/dashboard.js"></script>
     <script src="js/darkmode.js"></script>
        <!-- JavaScript Data -->
</body>
</html>
