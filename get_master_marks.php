<?php
require_once 'config.php';
require_once 'session.php';

// Suppress PHP errors from being output as HTML
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Load weights
    $weightsSql = "SELECT mid_weight, class_weight, exam_weight FROM marks_weights LIMIT 1";
    $weightsResult = $conn->query($weightsSql);
    if ($weightsResult && $weightsResult->num_rows > 0) {
        $weights = $weightsResult->fetch_assoc();
        $midWeight   = (int)$weights['mid_weight'];
        $classWeight = (int)$weights['class_weight'];
        $examWeight  = (int)$weights['exam_weight'];
    } else {
        $midWeight = 10;
        $classWeight = 20;
        $examWeight = 70;
    }

    // Get master marks query - FIXED
// Replace the entire SQL query section with this updated version:

// Get master marks query - FIXED with rank calculation
$sql = "
    SELECT 
        s.id AS student_id,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        c.class_name,
        subj.subject_name,
        t.term_name AS term,
        t.id AS term_id,
        ay.year_name AS academic_year,
        ay.id AS academic_year_id,
        COALESCE(m.total_marks, 0) AS midterm_total,
        ROUND(COALESCE(m.total_marks, 0) * ? / 100, 2) AS midterm_weighted,
        COALESCE(cs.total_marks, 0) AS class_score_total,
        ROUND(COALESCE(cs.total_marks, 0) * ? / 100, 2) AS class_score_weighted,
        COALESCE(e.total_marks, 0) AS exam_score_total,
        ROUND(COALESCE(e.total_marks, 0) * ? / 100, 2) AS exam_score_weighted,
        ROUND(
            (COALESCE(m.total_marks, 0) * ? / 100) +
            (COALESCE(cs.total_marks, 0) * ? / 100) +
            (COALESCE(e.total_marks, 0) * ? / 100),
            2
        ) AS final_grade
    FROM students s
    JOIN classes c ON s.class_id = c.id
    -- Get all combinations that actually have marks
    JOIN (
        SELECT student_id, subject_id, term_id, academic_year_id
        FROM midterm_marks
        UNION
        SELECT student_id, subject_id, term_id, academic_year_id
        FROM class_score_marks
        UNION
        SELECT student_id, subject_id, term_id, academic_year_id
        FROM exam_score_marks
    ) mark_combinations ON s.id = mark_combinations.student_id
    JOIN subjects subj ON mark_combinations.subject_id = subj.id
    JOIN terms t ON mark_combinations.term_id = t.id
    JOIN academic_years ay ON mark_combinations.academic_year_id = ay.id
    -- Now get the actual marks
    LEFT JOIN midterm_marks m 
        ON m.student_id = s.id 
        AND m.subject_id = subj.id 
        AND m.term_id = t.id 
        AND m.academic_year_id = ay.id
    LEFT JOIN class_score_marks cs 
        ON cs.student_id = s.id 
        AND cs.subject_id = subj.id 
        AND cs.term_id = t.id 
        AND cs.academic_year_id = ay.id
    LEFT JOIN exam_score_marks e 
        ON e.student_id = s.id 
        AND e.subject_id = subj.id 
        AND e.term_id = t.id 
        AND e.academic_year_id = ay.id
    ORDER BY c.class_name, t.term_name, subj.subject_name, final_grade DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iiiiii",
    $midWeight, $classWeight, $examWeight,
    $midWeight, $classWeight, $examWeight
);

if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

$masterMarksData = [];

// Load remarks table into memory (better than querying per row)
$remarksMap = [];
$remarksRes = $conn->query("SELECT grade, remark, min_mark, max_mark FROM remarks");
if ($remarksRes && $remarksRes->num_rows > 0) {
    while ($r = $remarksRes->fetch_assoc()) {
        $remarksMap[] = $r;
    }
}

// Group data by class, term, and subject to calculate ranks
$groupedData = [];

while ($row = $result->fetch_assoc()) {
    $finalGrade = (float)$row['final_grade'];
    $row['grade'] = '';
    $row['remark'] = '';

    // Assign grade/remark from remarks table
    foreach ($remarksMap as $r) {
        if ($finalGrade >= (float)$r['min_mark'] && $finalGrade <= (float)$r['max_mark']) {
            $row['grade'] = $r['grade'];
            $row['remark'] = $r['remark'];
            break;
        }
    }

    // Group by class, term, and subject for ranking
    $groupKey = $row['class_name'] . '_' . $row['term'] . '_' . $row['subject_name'];
    $groupedData[$groupKey][] = $row;
}

// Calculate ranks within each group
foreach ($groupedData as $groupKey => $group) {
    // Sort by final_grade descending
    usort($group, function($a, $b) {
        return $b['final_grade'] <=> $a['final_grade'];
    });
    
    // Assign ranks
    $rank = 1;
    $prevScore = null;
    $sameRankCount = 0;
    
    foreach ($group as $index => $studentMark) {
        $currentScore = $studentMark['final_grade'];
        
        if ($prevScore !== null && $currentScore == $prevScore) {
            $sameRankCount++;
        } else {
            $rank += $sameRankCount;
            $sameRankCount = 1;
        }
        
        // Add rank to the student data
        $group[$index]['rank'] = $rank;
        $prevScore = $currentScore;
    }
    
    // Add the ranked group back to the main data
    foreach ($group as $rankedRow) {
        $masterMarksData[] = $rankedRow;
    }
}

    echo json_encode($masterMarksData);

} catch (Exception $e) {
    // Log error properly and return clean JSON error
    error_log("Error in get_master_marks.php: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}
exit();
?>