<?php
// report_sheet.php
require_once 'config.php';
require_once 'session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Debug: Log all received parameters
error_log("Received parameters: " . print_r($_GET, true));

// Get and validate parameters
$studentId = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$termId = isset($_GET['term_id']) && is_numeric($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$academicYearId = isset($_GET['academic_year_id']) && is_numeric($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : 0;

// Check if all required parameters are present and valid
if ($studentId == 0 || $termId == 0 || $academicYearId == 0) {
    echo "<div style='padding: 20px; text-align: center; font-family: Arial, sans-serif;'>";
    echo "<h2>Missing Required Parameters</h2>";
    echo "<p>Please provide valid student_id, term_id, and academic_year_id parameters.</p>";
    echo "<p><strong>Current parameters:</strong></p>";
    echo "<ul style='list-style: none; padding: 0;'>";
    echo "<li>student_id: " . (isset($_GET['student_id']) ? htmlspecialchars($_GET['student_id']) : 'Not provided') . "</li>";
    echo "<li>term_id: " . (isset($_GET['term_id']) ? htmlspecialchars($_GET['term_id']) : 'Not provided') . "</li>";
    echo "<li>academic_year_id: " . (isset($_GET['academic_year_id']) ? htmlspecialchars($_GET['academic_year_id']) : 'Not provided') . "</li>";
    echo "</ul>";
    
    // Additional debug information
    echo "<p><strong>Full URL Parameters:</strong></p>";
    echo "<pre>" . print_r($_GET, true) . "</pre>";
    
    // Check if parameters are being passed via POST instead
    if (!empty($_POST)) {
        echo "<p><strong>POST Parameters:</strong></p>";
        echo "<pre>" . print_r($_POST, true) . "</pre>";
    }
    
    echo "<p><strong>Example:</strong> report_sheet.php?student_id=1&term_id=1&academic_year_id=1</p>";
    echo "<a href='master-Score.php' style='color: #667eea; text-decoration: none;'>← Back to Master Sheet</a>";
    echo "</div>";
    exit();
}

// Get school settings
$schoolSql = "SELECT * FROM school_settings LIMIT 1";
$schoolResult = $conn->query($schoolSql);
$school = $schoolResult->fetch_assoc();

// Get student information including class_id for ranking within class
$studentSql = "
    SELECT s.id, s.first_name, s.last_name, s.dob, s.gender, 
           c.class_name, s.student_id, s.class_id
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    WHERE s.id = ?
";
$stmt = $conn->prepare($studentSql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$studentResult = $stmt->get_result();

if ($studentResult->num_rows === 0) {
    die("Student not found.");
}

$student = $studentResult->fetch_assoc();

// Get total number of students in the same class (No. on Roll)
$classCountSql = "SELECT COUNT(*) as total_students FROM students WHERE class_id = ?";
$stmt = $conn->prepare($classCountSql);
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$classCountResult = $stmt->get_result();
$classCount = $classCountResult->fetch_assoc();
$totalStudentsInClass = $classCount['total_students'];

// Get term and academic year information
$termSql = "SELECT term_name FROM terms WHERE id = ?";
$stmt = $conn->prepare($termSql);
$stmt->bind_param("i", $termId);
$stmt->execute();
$termResult = $stmt->get_result();
$term = $termResult->fetch_assoc();

$yearSql = "SELECT year_name FROM academic_years WHERE id = ?";
$stmt = $conn->prepare($yearSql);
$stmt->bind_param("i", $academicYearId);
$stmt->execute();
$yearResult = $stmt->get_result();
$academicYear = $yearResult->fetch_assoc();

// Get weights from marks_weights table
$weightsSql = "SELECT mid_weight, class_weight, exam_weight FROM marks_weights LIMIT 1";
$weightsResult = $conn->query($weightsSql);
$weights = $weightsResult->fetch_assoc();

if ($weights) {
    $midtermWeight = (int)$weights['mid_weight'];
    $classWeight = (int)$weights['class_weight'];
    $examWeight = (int)$weights['exam_weight'];
    $combinedClassWeight = $midtermWeight + $classWeight; // Combined weight for midterm + class score
} else {
    $midtermWeight = 15;
    $classWeight = 15;
    $examWeight = 70;
    $combinedClassWeight = 30; // Default combined weight
}

// Get all subjects for this student in the specified term and academic year
$subjectsSql = "
    SELECT DISTINCT s.id as subject_id, s.subject_name
    FROM subjects s
    WHERE s.id IN (
        SELECT DISTINCT subject_id FROM midterm_marks 
        WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
        UNION
        SELECT DISTINCT subject_id FROM class_score_marks 
        WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
        UNION
        SELECT DISTINCT subject_id FROM exam_score_marks 
        WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
    )
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($subjectsSql);
$stmt->bind_param("iiiiiiiii", $studentId, $termId, $academicYearId, 
                  $studentId, $termId, $academicYearId,
                  $studentId, $termId, $academicYearId);
$stmt->execute();
$subjectsResult = $stmt->get_result();

$reportData = [];
$totalScore = 0;
$subjectCount = 0;

// Get all students in the same class for ranking (for each subject and overall)
$classStudentsSql = "
    SELECT DISTINCT student_id
    FROM (
        SELECT DISTINCT student_id FROM midterm_marks 
        WHERE term_id = ? AND academic_year_id = ? 
        AND student_id IN (SELECT id FROM students WHERE class_id = ?)
        UNION
        SELECT DISTINCT student_id FROM class_score_marks 
        WHERE term_id = ? AND academic_year_id = ?
        AND student_id IN (SELECT id FROM students WHERE class_id = ?)
        UNION
        SELECT DISTINCT student_id FROM exam_score_marks 
        WHERE term_id = ? AND academic_year_id = ?
        AND student_id IN (SELECT id FROM students WHERE class_id = ?)
    ) AS class_students
";

$stmt = $conn->prepare($classStudentsSql);
$stmt->bind_param("iiiiiiiii", $termId, $academicYearId, $student['class_id'],
                  $termId, $academicYearId, $student['class_id'],
                  $termId, $academicYearId, $student['class_id']);
$stmt->execute();
$classStudentsResult = $stmt->get_result();

$classStudentsData = [];
while ($classStudentRow = $classStudentsResult->fetch_assoc()) {
    $classStudentsData[] = $classStudentRow['student_id'];
}

// Process each subject for the current student
while ($subject = $subjectsResult->fetch_assoc()) {
    $subjectId = $subject['subject_id'];
    
    // Get midterm marks
    $midtermSql = "SELECT total_marks FROM midterm_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
    $stmt = $conn->prepare($midtermSql);
    $stmt->bind_param("iiii", $studentId, $subjectId, $termId, $academicYearId);
    $stmt->execute();
    $midtermResult = $stmt->get_result();
    $midtermMark = $midtermResult->num_rows > 0 ? (float)$midtermResult->fetch_assoc()['total_marks'] : 0;
    
    // Get class score marks
    $classSql = "SELECT total_marks FROM class_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
    $stmt = $conn->prepare($classSql);
    $stmt->bind_param("iiii", $studentId, $subjectId, $termId, $academicYearId);
    $stmt->execute();
    $classResult = $stmt->get_result();
    $classMark = $classResult->num_rows > 0 ? (float)$classResult->fetch_assoc()['total_marks'] : 0;
    
    // Get exam marks
    $examSql = "SELECT total_marks FROM exam_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
    $stmt = $conn->prepare($examSql);
    $stmt->bind_param("iiii", $studentId, $subjectId, $termId, $academicYearId);
    $stmt->execute();
    $examResult = $stmt->get_result();
    $examMark = $examResult->num_rows > 0 ? (float)$examResult->fetch_assoc()['total_marks'] : 0;
    
    // Calculate weighted scores properly
    $midtermWeighted = ($midtermMark * $midtermWeight) / 100;
    $classWeighted = ($classMark * $classWeight) / 100;
    $examWeighted = ($examMark * $examWeight) / 100;
    
    // Combined class score (weighted midterm + weighted class score)
    $combinedClassScore = $midtermWeighted + $classWeighted;
    
    // Final grade calculation
    $finalGrade = $combinedClassScore + $examWeighted;
    
    // Get all students in the same class for this subject to calculate rank
    $subjectRankData = [];
    foreach ($classStudentsData as $otherStudentId) {
        // Get other student's marks for this subject
        $midtermSql = "SELECT total_marks FROM midterm_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($midtermSql);
        $stmt->bind_param("iiii", $otherStudentId, $subjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherMidtermResult = $stmt->get_result();
        $otherMidtermMark = $otherMidtermResult->num_rows > 0 ? (float)$otherMidtermResult->fetch_assoc()['total_marks'] : 0;
        
        $classSql = "SELECT total_marks FROM class_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($classSql);
        $stmt->bind_param("iiii", $otherStudentId, $subjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherClassResult = $stmt->get_result();
        $otherClassMark = $otherClassResult->num_rows > 0 ? (float)$otherClassResult->fetch_assoc()['total_marks'] : 0;
        
        $examSql = "SELECT total_marks FROM exam_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($examSql);
        $stmt->bind_param("iiii", $otherStudentId, $subjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherExamResult = $stmt->get_result();
        $otherExamMark = $otherExamResult->num_rows > 0 ? (float)$otherExamResult->fetch_assoc()['total_marks'] : 0;
        
        // Calculate other student's final grade
        $otherMidtermWeighted = ($otherMidtermMark * $midtermWeight) / 100;
        $otherClassWeighted = ($otherClassMark * $classWeight) / 100;
        $otherExamWeighted = ($otherExamMark * $examWeight) / 100;
        $otherFinalGrade = $otherMidtermWeighted + $otherClassWeighted + $otherExamWeighted;
        
        $subjectRankData[$otherStudentId] = $otherFinalGrade;
    }
    
    // Calculate rank for this subject
    $subjectRank = 1;
    foreach ($subjectRankData as $otherScore) {
        if ($otherScore > $finalGrade) {
            $subjectRank++;
        }
    }
    
    // Get grade and remark from remarks table
    $remarkSql = "SELECT grade, remark FROM remarks WHERE min_mark <= ? AND max_mark >= ? ORDER BY min_mark DESC LIMIT 1";
    $stmt = $conn->prepare($remarkSql);
    $stmt->bind_param("dd", $finalGrade, $finalGrade);
    $stmt->execute();
    $remarkResult = $stmt->get_result();
    $remarkData = $remarkResult->fetch_assoc();
    
    $letterGrade = $remarkData ? $remarkData['grade'] : 'F';
    $remark = $remarkData ? $remarkData['remark'] : 'FAIL';
    
    $reportData[] = [
        'subject_name' => $subject['subject_name'],
        'combined_class_score' => round($combinedClassScore, 1),
        'exam_score' => round($examWeighted, 1), // Use weighted exam score instead of raw marks
        'total_score' => round($finalGrade, 1),
        'position' => $subjectRank,
        'grade' => $letterGrade,
        'remark' => $remark
    ];
    
    $totalScore += $finalGrade;
    $subjectCount++;
}

$overallAverage = $subjectCount > 0 ? round($totalScore / $subjectCount, 1) : 0;

// Calculate overall rank among students in the same class
$classStudentAverages = [];
foreach ($classStudentsData as $otherStudentId) {
    // Get all subjects for this student
    $subjectsSql = "
        SELECT DISTINCT s.id as subject_id
        FROM subjects s
        WHERE s.id IN (
            SELECT DISTINCT subject_id FROM midterm_marks 
            WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
            UNION
            SELECT DISTINCT subject_id FROM class_score_marks 
            WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
            UNION
            SELECT DISTINCT subject_id FROM exam_score_marks 
            WHERE student_id = ? AND term_id = ? AND academic_year_id = ?
        )
    ";

    $stmt = $conn->prepare($subjectsSql);
    $stmt->bind_param("iiiiiiiii", $otherStudentId, $termId, $academicYearId, 
                      $otherStudentId, $termId, $academicYearId,
                      $otherStudentId, $termId, $academicYearId);
    $stmt->execute();
    $otherSubjectsResult = $stmt->get_result();
    
    $otherTotalScore = 0;
    $otherSubjectCount = 0;
    
    while ($otherSubject = $otherSubjectsResult->fetch_assoc()) {
        $otherSubjectId = $otherSubject['subject_id'];
        
        // Get marks for this student and subject
        $midtermSql = "SELECT total_marks FROM midterm_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($midtermSql);
        $stmt->bind_param("iiii", $otherStudentId, $otherSubjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherMidtermResult = $stmt->get_result();
        $otherMidtermMark = $otherMidtermResult->num_rows > 0 ? (float)$otherMidtermResult->fetch_assoc()['total_marks'] : 0;
        
        $classSql = "SELECT total_marks FROM class_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($classSql);
        $stmt->bind_param("iiii", $otherStudentId, $otherSubjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherClassResult = $stmt->get_result();
        $otherClassMark = $otherClassResult->num_rows > 0 ? (float)$otherClassResult->fetch_assoc()['total_marks'] : 0;
        
        $examSql = "SELECT total_marks FROM exam_score_marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($examSql);
        $stmt->bind_param("iiii", $otherStudentId, $otherSubjectId, $termId, $academicYearId);
        $stmt->execute();
        $otherExamResult = $stmt->get_result();
        $otherExamMark = $otherExamResult->num_rows > 0 ? (float)$otherExamResult->fetch_assoc()['total_marks'] : 0;
        
        // Calculate weighted final grade
        $otherMidtermWeighted = ($otherMidtermMark * $midtermWeight) / 100;
        $otherClassWeighted = ($otherClassMark * $classWeight) / 100;
        $otherExamWeighted = ($otherExamMark * $examWeight) / 100;
        $otherFinalGrade = $otherMidtermWeighted + $otherClassWeighted + $otherExamWeighted;
        
        $otherTotalScore += $otherFinalGrade;
        $otherSubjectCount++;
    }
    
    $otherOverallAverage = $otherSubjectCount > 0 ? ($otherTotalScore / $otherSubjectCount) : 0;
    $classStudentAverages[$otherStudentId] = $otherOverallAverage;
}

// Calculate overall rank within the class
$overallRank = 1;
foreach ($classStudentAverages as $otherAverage) {
    if ($otherAverage > $overallAverage) {
        $overallRank++;
    }
}

// Get overall position from remarks table
$overallRemarkSql = "SELECT remark FROM remarks WHERE min_mark <= ? AND max_mark >= ? ORDER BY min_mark DESC LIMIT 1";
$stmt = $conn->prepare($overallRemarkSql);
$stmt->bind_param("dd", $overallAverage, $overallAverage);
$stmt->execute();
$overallRemarkResult = $stmt->get_result();
$overallRemarkData = $overallRemarkResult->fetch_assoc();
$overallPosition = $overallRemarkData ? $overallRemarkData['remark'] : 'FAIL';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPIL'S REPORT SHEET - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <!-- Add html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page {
            size: A4;
            margin: 0.5in;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        
        .report-sheet {
            max-width: 8.5in;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #000;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 15px;
        }
        
        .school-logo {
            width: 60px;
            height: 60px;
            border: 1px solid #000;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            overflow: hidden;
        }
        
        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .school-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        
        .school-location {
            font-size: 12px;
            margin-bottom: 3px;
        }
        
        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 10px;
        }
        
        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        
        .detail-line {
            margin-bottom: 8px;
            border-bottom: 1px dotted #000;
            padding-bottom: 2px;
        }
        
        .next-term-info {
            text-align: center;
            margin-bottom: 15px;
            font-size: 12px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        
        .grading-scale {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            font-size: 10px;
            text-align: center;
        }
        
        .grade-box {
            border: 1px solid #000;
            padding: 5px;
            font-weight: bold;
        }
        
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .marks-table th,
        .marks-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }
        
        .marks-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 10px;
        }
        
        .subject-cell {
            text-align: left !important;
            font-weight: bold;
            padding-left: 8px;
        }
        
        .marks-table .subject-header {
            text-align: left;
            font-weight: bold;
        }
        
        .weight-header {
            font-size: 9px;
            line-height: 1.2;
        }
        
        .footer-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
            font-size: 11px;
        }
        
        .footer-item {
            margin-bottom: 12px;
            border-bottom: 1px dotted #000;
            padding-bottom: 8px;
        }
        
        .signature-section {
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #000;
            padding-top: 15px;
        }
        
        .no-cross {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .actions {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            border-top: 2px solid #000;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            margin: 0 10px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            border: none;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-pdf {
            background: #dc3545;
        }
        
        .btn-pdf:hover {
            background: #c82333;
        }
        
        .overall-pos {
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            margin-top: 10px;
            padding: 5px;
            border: 1px solid #000;
        }
    </style>
</head>
<body>
    <div class="report-sheet" id="report-content">
        <div class="header">
            <div class="school-logo">
                <?php if ($school && !empty($school['logo']) && file_exists($school['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($school['logo']); ?>" alt="School Logo">
                <?php else: ?>
                    <?php echo $school ? strtoupper(substr($school['school_name'], 0, 2)) : 'GT'; ?>
                <?php endif; ?>
            </div>
            <div class="school-name"><?php echo htmlspecialchars(strtoupper($school['school_name'] ?? 'GLOBAL EVANGELICAL BASIC SCHOOL')); ?></div>
            <?php if ($school && !empty($school['school_name_2'])): ?>
                <div class="school-name"><?php echo htmlspecialchars(strtoupper($school['school_name_2'])); ?></div>
            <?php endif; ?>
            <div class="school-location"><?php echo htmlspecialchars($school['address'] ?? 'P.O. BOX KW 182, KETA'); ?></div>
            <div class="report-title">PUPIL'S REPORT SHEET</div>
        </div>
        
        <div class="student-details">
            <div>
                <div class="detail-line">
                    <strong>NAME:</strong> <?php echo htmlspecialchars(strtoupper($student['first_name'] . ' ' . $student['last_name'])); ?>
                </div>
                <div class="detail-line">
                    <strong>FORM / CLASS:</strong> <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?>
                </div>
            </div>
            <div>
                <div class="detail-line">
                    <strong>No. on Roll:</strong> <?php echo htmlspecialchars($totalStudentsInClass); ?>
                </div>
                <div class="detail-line">
                    <strong>YEAR:</strong> <?php echo htmlspecialchars($academicYear['year_name'] ?? 'N/A'); ?> 
                    <strong>VAC. DATE:</strong> ......................
                </div>
            </div>
        </div>
        
        <div class="next-term-info">
            <strong>NEXT TERM BEGINS:</strong> ............................ 
            <strong>OVERALL POS:</strong> <?php echo $overallRank; ?>/<?php echo count($classStudentAverages); ?> (<?php echo $overallPosition; ?>)
        </div>
        
        <?php
        // Get grading scale from remarks table for display
        $gradesSql = "SELECT grade, remark, min_mark, max_mark FROM remarks ORDER BY min_mark DESC";
        $gradesResult = $conn->query($gradesSql);
        $grades = [];
        while ($grade = $gradesResult->fetch_assoc()) {
            $grades[] = $grade;
        }
        ?>
        
        <div class="grading-scale">
            <?php foreach ($grades as $grade): ?>
                <div class="grade-box">
                    <?php echo htmlspecialchars($grade['grade'] . ' ' . $grade['remark'] . ' (' . $grade['min_mark'] . '% - ' . $grade['max_mark'] . '%)'); ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <table class="marks-table">
            <thead>
                <tr>
                    <th rowspan="2" class="subject-header">SUBJECTS</th>
                    <th class="weight-header">CLASS<br>SCORE<br><?php echo $combinedClassWeight; ?>%</th>
                    <th class="weight-header">EXAMS<br>SCORE<br><?php echo $examWeight; ?>%</th>
                    <th class="weight-header">TOTAL<br>SCORE<br>100%</th>
                    <th rowspan="2">POS</th>
                    <th rowspan="2">GRADES</th>
                    <th rowspan="2" class="subject-header">REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $subject): ?>
                <tr>
                    <td class="subject-cell"><?php echo htmlspecialchars(strtoupper($subject['subject_name'])); ?></td>
                    <td><?php echo number_format($subject['combined_class_score'], 1); ?></td>
                    <td><?php echo number_format($subject['exam_score'], 1); ?></td>
                    <td><?php echo number_format($subject['total_score'], 1); ?></td>
                    <td><?php echo $subject['position']; ?></td>
                    <td><strong><?php echo $subject['grade']; ?></strong></td>
                    <td><?php echo htmlspecialchars($subject['remark']); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($reportData)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No marks found for this student</td></tr>
                <?php endif; ?>
                
                <!-- Add empty rows to match the original format -->
                <?php for ($i = count($reportData); $i < 12; $i++): ?>
                <tr>
                    <td class="subject-cell"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <div class="overall-pos">
            OVERALL AVERAGE: <?php echo number_format($overallAverage, 1); ?>% | 
            TERM: <?php echo htmlspecialchars($term['term_name'] ?? 'N/A'); ?> |
            OVERALL RANK: <?php echo $overallRank; ?>/<?php echo count($classStudentAverages); ?>
        </div>
        
        <div class="footer-section">
            <div>
                <div class="footer-item">
                    <strong>ATTENDANCE:</strong> .........................
                </div>
                <div class="footer-item">
                    <strong>CONDUCT:</strong> .........................................................................................
                </div>
                <div class="footer-item">
                    <strong>ATTITUDE:</strong> .........................................................................................
                </div>
            </div>
            <div>
                <div class="footer-item">
                    <strong>PROMOTED TO:</strong> .........................
                </div>
                <div class="footer-item">
                    <strong>CLASS TEACHER'S REMARK:</strong> .........................................................................................
                </div>
                <div style="margin-top: 20px;">
                    .........................................................................................
                </div>
            </div>
        </div>
        
        <div class="signature-section">
            <strong>HEAD TEACHER'S SIGNATURE:</strong> .........................................................................................
        </div>
        
        <div class="no-cross">
            <strong><?php echo htmlspecialchars($school['motto'] ?? 'NO CROSS NO CROWN'); ?></strong>
        </div>
    </div>

    <div class="actions no-print">
        <button onclick="window.print()" class="btn">Print Report</button>
        <button onclick="generatePDF()" class="btn btn-pdf">Save as PDF</button>
        <a href="master-Score.php" class="btn">Back to Master Sheet</a>
        <button onclick="window.close()" class="btn">Close</button>
    </div>

    <script>
        // Function to generate PDF
        function generatePDF() {
            const element = document.getElementById('report-content');
            const options = {
                margin: 10,
                filename: 'report_sheet_<?php echo $student["first_name"] . "_" . $student["last_name"]; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(options).from(element).save();
        }

        // Function to open report sheet with proper parameters
        function openReportSheet(studentId, termId, academicYearId) {
            // Validate parameters
            if (!studentId || studentId === 'undefined' || isNaN(studentId)) {
                alert("Invalid Student ID! Please select a valid student.");
                return;
            }
            
            // If term or academic year are undefined, try to get defaults
            if (!termId || termId === 'undefined' || isNaN(termId) ||
                !academicYearId || academicYearId === 'undefined' || isNaN(academicYearId)) {
                
                // Show loading message
                alert("Loading default term and academic year...");
                
                // Fetch default values
                fetch(`get_default_values.php?student_id=${studentId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const url = `report_sheet.php?student_id=${studentId}&term_id=${data.term_id}&academic_year_id=${data.academic_year_id}`;
                            window.location.href = url;
                        } else {
                            alert("Error: Could not determine default term and academic year.");
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching default values:', error);
                        alert("An error occurred while loading default values.");
                    });
            } else {
                // All parameters are valid, proceed normally
                const url = `report_sheet.php?student_id=${encodeURIComponent(studentId)}&term_id=${encodeURIComponent(termId)}&academic_year_id=${encodeURIComponent(academicYearId)}`;
                window.location.href = url;
            }
        }
    </script>
</body>
</html>