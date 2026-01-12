<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $academicYear = $_GET['academicYear'] ?? '';
    $term = $_GET['term'] ?? '';
    $class = $_GET['class'] ?? '';
    
    // Get marks weights
    $weightQuery = "SELECT mid_weight, class_weight, exam_weight FROM marks_weights LIMIT 1";
    $weightResult = $conn->query($weightQuery);
    $weights = $weightResult->fetch_assoc();
    
    $midWeight = $weights['mid_weight'] / 100;
    $classWeight = $weights['class_weight'] / 100;
    $examWeight = $weights['exam_weight'] / 100;
    
    $query = "SELECT 
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.class_name,
                COUNT(DISTINCT sub.id) as subjects_taken,
                AVG(COALESCE(mm.total_marks, 0) * {$midWeight} + 
                    COALESCE(csm.total_marks, 0) * {$classWeight} + 
                    COALESCE(esm.total_marks, 0) * {$examWeight}) as average_score,
                MAX(COALESCE(mm.total_marks, 0) * {$midWeight} + 
                    COALESCE(csm.total_marks, 0) * {$classWeight} + 
                    COALESCE(esm.total_marks, 0) * {$examWeight}) as highest_score,
                MIN(COALESCE(mm.total_marks, 0) * {$midWeight} + 
                    COALESCE(csm.total_marks, 0) * {$classWeight} + 
                    COALESCE(esm.total_marks, 0) * {$examWeight}) as lowest_score,
                SUM(CASE 
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 80 THEN 1 ELSE 0 
                END) as grade_a_count,
                SUM(CASE 
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 70 
                         AND (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                              COALESCE(csm.total_marks, 0) * {$classWeight} + 
                              COALESCE(esm.total_marks, 0) * {$examWeight}) < 80 THEN 1 ELSE 0 
                END) as grade_b_count,
                SUM(CASE 
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) < 50 THEN 1 ELSE 0 
                END) as failed_subjects,
                t.term_name,
                ay.year_name as academic_year
              FROM students s
              INNER JOIN classes c ON s.class_id = c.id
              CROSS JOIN subjects sub
              LEFT JOIN midterm_marks mm ON s.id = mm.student_id 
                  AND sub.id = mm.subject_id 
                  AND c.id = mm.class_id
              LEFT JOIN class_score_marks csm ON s.id = csm.student_id 
                  AND sub.id = csm.subject_id 
                  AND c.id = csm.class_id
              LEFT JOIN exam_score_marks esm ON s.id = esm.student_id 
                  AND sub.id = esm.subject_id 
                  AND c.id = esm.class_id
              LEFT JOIN terms t ON COALESCE(mm.term_id, csm.term_id, esm.term_id) = t.id
              LEFT JOIN academic_years ay ON COALESCE(mm.academic_year_id, csm.academic_year_id, esm.academic_year_id) = ay.id
              WHERE (mm.id IS NOT NULL OR csm.id IS NOT NULL OR esm.id IS NOT NULL)";
    
    $params = [];
    $types = "";
    
    if (!empty($academicYear)) {
        $query .= " AND (mm.academic_year_id = ? OR csm.academic_year_id = ? OR esm.academic_year_id = ?)";
        $params[] = $academicYear;
        $params[] = $academicYear;
        $params[] = $academicYear;
        $types .= "iii";
    }
    
    if (!empty($term)) {
        $query .= " AND (mm.term_id = ? OR csm.term_id = ? OR esm.term_id = ?)";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types .= "iii";
    }
    
    if (!empty($class)) {
        $query .= " AND s.class_id = ?";
        $params[] = $class;
        $types .= "i";
    }
    
    $query .= " GROUP BY s.id, s.student_id, student_name, c.class_name, t.term_name, ay.year_name
                ORDER BY average_score DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance = [];
    $rank = 1;
    
    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank++;
        $row['performance_status'] = $row['average_score'] >= 70 ? 'Excellent' : 
                                     ($row['average_score'] >= 60 ? 'Good' : 
                                     ($row['average_score'] >= 50 ? 'Average' : 'Needs Improvement'));
        $performance[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $performance,
        'count' => count($performance)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch student performance',
        'message' => $e->getMessage()
    ]);
}
?> 