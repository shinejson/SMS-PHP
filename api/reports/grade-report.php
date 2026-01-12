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
                sub.subject_name,
                COALESCE(mm.total_marks, 0) as midterm_marks,
                COALESCE(csm.total_marks, 0) as class_marks,
                COALESCE(esm.total_marks, 0) as exam_marks,
                (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                 COALESCE(csm.total_marks, 0) * {$classWeight} + 
                 COALESCE(esm.total_marks, 0) * {$examWeight}) as total_score,
                CASE 
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 80 THEN 'A'
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 70 THEN 'B'
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 60 THEN 'C'
                    WHEN (COALESCE(mm.total_marks, 0) * {$midWeight} + 
                          COALESCE(csm.total_marks, 0) * {$classWeight} + 
                          COALESCE(esm.total_marks, 0) * {$examWeight}) >= 50 THEN 'D'
                    ELSE 'F'
                END as grade,
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
              WHERE 1=1";
    
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
    
    $query .= " ORDER BY student_name, sub.subject_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $grades,
        'count' => count($grades),
        'weights' => $weights
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch grade report',
        'message' => $e->getMessage()
    ]);
}
?>