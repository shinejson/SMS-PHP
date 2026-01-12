<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Add input validation
$student_id = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);
if (!$student_id || $student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

$graduation_year = $_POST['graduation_year'] ?? '';

try {
    // Get student information
    $student_query = "
        SELECT 
            s.id,
            s.first_name,
            s.last_name,
            s.student_id,
            s.dob,
            s.parent_contact,
            s.email,
            s.address,
            s.class_id,
            c.class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.id = ? AND s.status = 'Active'
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($student_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for student query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_data = $student_result->fetch_assoc();
    $stmt->close();
    
    if (!$student_data) {
        throw new Exception("Student not found or is inactive.");
    }

    $student_name = trim(($student_data['first_name'] ?? '') . ' ' . ($student_data['last_name'] ?? ''));

    // Get school settings
    $settings_query = "SELECT * FROM school_settings WHERE id = 1 LIMIT 1";
    $settings_result = $conn->query($settings_query);
    $school_settings = $settings_result->fetch_assoc();
    
    // Get academic records
    $academic_records = getStudentResultsFromDB($student_id);

    if (empty($academic_records)) {
        error_log("No results data found for student ID: $student_id in database tables.");
        $response_data = [
            "success" => false,
            "message" => "No academic records found for this student in the database.",
            "student_name" => $student_name,
            "student_address" => $student_data['address'] ?? 'N/A',
            "student_phone" => $student_data['parent_contact'] ?? 'N/A',
            "student_email" => $student_data['email'] ?? 'N/A',
            "student_dob" => date('d/m/Y', strtotime($student_data['dob'])) ?? 'N/A',
            "academic_records" => [],
            "cumulative_gpa" => 0
        ];
        echo json_encode($response_data);
        exit;
    }
    
    // Calculate cumulative GPA
    $total_gpa = 0;
    $total_credits = 0;
    foreach ($academic_records as $year_term) {
        foreach ($year_term as $term_data) {
            foreach ($term_data['subjects'] as $subject) {
                if (isset($subject['gpa_points']) && isset($subject['credits'])) {
                    $total_gpa += $subject['gpa_points'] * $subject['credits'];
                    $total_credits += $subject['credits'];
                }
            }
        }
    }

    $cumulative_gpa = $total_credits > 0 ? round($total_gpa / $total_credits, 2) : 0.0;

    $response_data = [
        "success" => true,
        "student_name" => $student_name,
        "student_address" => $student_data['address'] ?? 'N/A',
        "student_phone" => $student_data['parent_contact'] ?? 'N/A',
        "student_email" => $student_data['email'] ?? 'N/A',
        "student_dob" => date('d/m/Y', strtotime($student_data['dob'])) ?? 'N/A',
        "parent_name" => 'N/A',
        "graduation_date" => $graduation_year ? "{$graduation_year} Grace" : 'N/A',
        "current_class" => $student_data['class_name'] ?? 'N/A',
        "academic_records" => $academic_records,
        "cumulative_gpa" => $cumulative_gpa,
        "gpa_info" => [
            "cumulative_gpa" => $cumulative_gpa,
            "total_credits" => $total_credits
        ]
    ];
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Error in get_student_transcript.php: " . $e->getMessage());
}

$conn->close();

function getStudentResultsFromDB($student_id) {
    global $conn;
    $academic_records = [];

    try {
        error_log("Fetching results for student ID: $student_id");

        // ** FETCH SINGLE, GLOBAL MARK WEIGHT **
        $weights_query = "
            SELECT mid_weight, class_weight, exam_weight 
            FROM marks_weights 
            LIMIT 1
        ";
        $weights_result = $conn->query($weights_query);
        $global_weights_row = $weights_result->fetch_assoc();
        $global_weights = [
            'mid_weight' => (float)($global_weights_row['mid_weight'] ?? 20),
            'class_weight' => (float)($global_weights_row['class_weight'] ?? 20),
            'exam_weight' => (float)($global_weights_row['exam_weight'] ?? 60)
        ];
        
        error_log("Using Global Mark Weights: Mid={$global_weights['mid_weight']}%, Class={$global_weights['class_weight']}%, Exam={$global_weights['exam_weight']}%");

        // Get student's actual class history with marks
        $student_classes_query = "
            SELECT DISTINCT 
                m.academic_year_id,
                m.term_id, 
                m.class_id,
                ay.year_name,
                t.term_name,
                c.class_name
            FROM (
                SELECT academic_year_id, term_id, class_id FROM midterm_marks WHERE student_id = ?
                UNION
                SELECT academic_year_id, term_id, class_id FROM class_score_marks WHERE student_id = ?
                UNION
                SELECT academic_year_id, term_id, class_id FROM exam_score_marks WHERE student_id = ?
            ) AS m
            JOIN academic_years ay ON m.academic_year_id = ay.id
            JOIN terms t ON m.term_id = t.id
            JOIN classes c ON m.class_id = c.id
            ORDER BY ay.year_name DESC, t.term_name
        ";

        $stmt = $conn->prepare($student_classes_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare student classes query: " . $conn->error);
        }
        
        $stmt->bind_param("iii", $student_id, $student_id, $student_id);
        $stmt->execute();
        $classes_result = $stmt->get_result();
        $student_classes = [];
        
        while ($row = $classes_result->fetch_assoc()) {
            $student_classes[] = $row;
        }
        $stmt->close();

        if (empty($student_classes)) {
            error_log("No academic records found for student ID: $student_id in any marks table");
            return [];
        }

        error_log("Found " . count($student_classes) . " academic periods with marks");

        // Get all subjects
        $subjects_query = "SELECT id, subject_name FROM subjects ORDER BY subject_name";
        $subjects_result = $conn->query($subjects_query);
        $subjects = [];
        while ($row = $subjects_result->fetch_assoc()) {
            $subjects[$row['id']] = $row['subject_name'];
        }

        // Process each class/year/term combination
        foreach ($student_classes as $class_info) {
            $class_id = $class_info['class_id'];
            $academic_year_id = $class_info['academic_year_id'];
            $term_id = $class_info['term_id'];
            $year_term_key = $class_info['year_name'] . ' - ' . $class_info['term_name'];
            $class_key = $class_info['class_name'];

            // Initialize the academic record structure
            if (!isset($academic_records[$year_term_key])) {
                $academic_records[$year_term_key] = [];
            }
            
            if (!isset($academic_records[$year_term_key][$class_key])) {
                $academic_records[$year_term_key][$class_key] = [
                    'class_name' => $class_info['class_name'],
                    'subjects' => [],
                    'term_total' => 0,
                    'term_average' => 0,
                    'subject_count' => 0
                ];
            }

            // Get ALL subjects that have marks for this student
            $student_subjects_query = "
                SELECT DISTINCT subject_id 
                FROM (
                    SELECT subject_id FROM midterm_marks WHERE student_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                    UNION 
                    SELECT subject_id FROM class_score_marks WHERE student_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                    UNION 
                    SELECT subject_id FROM exam_score_marks WHERE student_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                ) AS student_subjects
            ";
            
            $subjects_stmt = $conn->prepare($student_subjects_query);
            $student_subjects = [];
            
            if ($subjects_stmt) {
                $subjects_stmt->bind_param("iiiiiiiiiiii", 
                    $student_id, $academic_year_id, $term_id, $class_id,
                    $student_id, $academic_year_id, $term_id, $class_id,
                    $student_id, $academic_year_id, $term_id, $class_id
                );
                $subjects_stmt->execute();
                $subjects_result = $subjects_stmt->get_result();
                
                while ($subject_row = $subjects_result->fetch_assoc()) {
                    $student_subjects[] = $subject_row['subject_id'];
                }
                $subjects_stmt->close();
            } else {
                error_log("Failed to prepare student subjects query");
                $student_subjects = array_keys($subjects);
            }

            // Process each subject that has marks
            foreach ($student_subjects as $subject_id) {
                $subject_name = $subjects[$subject_id] ?? 'Unknown Subject';
                
                // Get midterm marks
                $midterm_total = 0;
                $midterm_query = "
                    SELECT total_marks 
                    FROM midterm_marks 
                    WHERE student_id = ? AND subject_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                ";
                
                $midterm_stmt = $conn->prepare($midterm_query);
                if ($midterm_stmt) {
                    $midterm_stmt->bind_param("iiiii", $student_id, $subject_id, $academic_year_id, $term_id, $class_id);
                    $midterm_stmt->execute();
                    $midterm_result = $midterm_stmt->get_result();
                    if ($midterm_row = $midterm_result->fetch_assoc()) {
                        $midterm_total = (float)$midterm_row['total_marks'];
                    }
                    $midterm_stmt->close();
                }

                // Get class score marks
                $class_score_total = 0;
                $class_score_query = "
                    SELECT total_marks 
                    FROM class_score_marks 
                    WHERE student_id = ? AND subject_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                ";
                
                $class_score_stmt = $conn->prepare($class_score_query);
                if ($class_score_stmt) {
                    $class_score_stmt->bind_param("iiiii", $student_id, $subject_id, $academic_year_id, $term_id, $class_id);
                    $class_score_stmt->execute();
                    $class_score_result = $class_score_stmt->get_result();
                    if ($class_score_row = $class_score_result->fetch_assoc()) {
                        $class_score_total = (float)$class_score_row['total_marks'];
                    }
                    $class_score_stmt->close();
                }

                // Get exam score marks
                $exam_score_total = 0;
                $exam_query = "
                    SELECT total_marks 
                    FROM exam_score_marks 
                    WHERE student_id = ? AND subject_id = ? AND academic_year_id = ? AND term_id = ? AND class_id = ?
                ";
                
                $exam_stmt = $conn->prepare($exam_query);
                if ($exam_stmt) {
                    $exam_stmt->bind_param("iiiii", $student_id, $subject_id, $academic_year_id, $term_id, $class_id);
                    $exam_stmt->execute();
                    $exam_result = $exam_stmt->get_result();
                    if ($exam_row = $exam_result->fetch_assoc()) {
                        $exam_score_total = (float)$exam_row['total_marks'];
                    }
                    $exam_stmt->close();
                }

                // Skip if no marks found for this subject
                if ($midterm_total == 0 && $class_score_total == 0 && $exam_score_total == 0) {
                    continue;
                }

                // Calculate weighted scores using dynamic weights
                $weighted_midterm = ($midterm_total * $global_weights['mid_weight']) / 100;
                $weighted_class = ($class_score_total * $global_weights['class_weight']) / 100;
                $weighted_exam = ($exam_score_total * $global_weights['exam_weight']) / 100;

                // Calculate class score (midterm + class score combined)
                $class_score_percentage = $weighted_midterm + $weighted_class;
                $exam_score_percentage = $weighted_exam;
                $total_score = $class_score_percentage + $exam_score_percentage;

                // Determine grade and remark
                $grade = calculateGrade($total_score);
                $remark = calculateRemark($total_score);
                $gpa_points = calculateGpaPoints($total_score);

                $subject_data = [
                    'subject_name' => $subject_name,
                    'class_score' => round($class_score_percentage, 2),
                    'exam_score' => round($exam_score_percentage, 2),
                    'total_score' => round($total_score, 2),
                    'grade' => $grade,
                    'remark' => $remark,
                    'gpa_points' => $gpa_points,
                    'credits' => 1.0
                ];

                $academic_records[$year_term_key][$class_key]['subjects'][] = $subject_data;
                $academic_records[$year_term_key][$class_key]['term_total'] += $total_score;
                $academic_records[$year_term_key][$class_key]['subject_count']++;
                
                error_log("Added subject: $subject_name - Total: $total_score, Grade: $grade");
            }
        }

        // Calculate term averages and remove empty records
        $filtered_records = [];
        foreach ($academic_records as $year_term_key => $terms_data) {
            foreach ($terms_data as $class_key => $term_data) {
                if ($term_data['subject_count'] > 0) {
                    $term_data['term_average'] = round($term_data['term_total'] / $term_data['subject_count'], 2);
                    $filtered_records[$year_term_key][$class_key] = $term_data;
                }
            }
        }

        error_log("Successfully processed " . count($filtered_records) . " academic records with real data");
        return $filtered_records;

    } catch (Exception $e) {
        error_log("Error in getStudentResultsFromDB: " . $e->getMessage());
        return [];
    }
}

// Helper function to calculate grade based on total score
function calculateGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'F';
}

// Helper function to calculate remark based on total score
function calculateRemark($score) {
    if ($score >= 80) return 'Excellent';
    if ($score >= 70) return 'Very Good';
    if ($score >= 60) return 'Good';
    if ($score >= 50) return 'Fair';
    return 'Needs Improvement';
}

// Helper function to calculate GPA points based on total score
function calculateGpaPoints($score) {
    if ($score >= 80) return 4.0;
    if ($score >= 70) return 3.0;
    if ($score >= 60) return 2.0;
    if ($score >= 50) return 1.0;
    return 0.0;
}

// Debug function
function debugStudentRecords($student_id) {
    global $conn;
    
    error_log("=== DEBUG: Checking student records for ID: $student_id ===");
    
    // Check if student exists
    $check_student = $conn->query("SELECT id, first_name, last_name FROM students WHERE id = $student_id");
    if ($check_student && $check_student->num_rows > 0) {
        $student = $check_student->fetch_assoc();
        error_log("Student found: {$student['first_name']} {$student['last_name']}");
    } else {
        error_log("ERROR: Student not found in database!");
        return;
    }
    
    // Check marks in each table
    $tables = ['midterm_marks', 'class_score_marks', 'exam_score_marks'];
    
    foreach ($tables as $table) {
        $check_query = "SELECT COUNT(*) as count FROM $table WHERE student_id = $student_id";
        $result = $conn->query($check_query);
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            error_log("Table $table: $count records found");
            
            if ($count > 0) {
                $sample_query = "SELECT * FROM $table WHERE student_id = $student_id LIMIT 2";
                $sample_result = $conn->query($sample_query);
                while ($row = $sample_result->fetch_assoc()) {
                    error_log("Sample from $table: " . json_encode($row));
                }
            }
        } else {
            error_log("ERROR: Could not query table $table");
        }
    }
}
?>