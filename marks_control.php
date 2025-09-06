<?php
// Temporary debugging - remove after fixing
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';

// Set content type for JSON response
header('Content-Type: application/json');
// Check if we're getting the expected request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_students_by_class') {
    // Log the request for debugging
    error_log("GET students request: " . print_r($_GET, true));
}
// Define helper functions
function loadWeights($conn) {
    $sql = "SELECT mid_weight, class_weight, exam_weight FROM marks_weights LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return [
        'mid_weight' => 10,
        'class_weight' => 20,
        'exam_weight' => 70
    ];
}

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

function getGradeFromScore($score, $conn) {
    $sql = "SELECT grade FROM remarks WHERE ? BETWEEN min_mark AND max_mark LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("d", $score);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['grade'];
    }
    
    return 'F';
}

function getRemarkFromScore($score, $conn) {
    $sql = "SELECT remark FROM remarks WHERE ? BETWEEN min_mark AND max_mark LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("d", $score);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['remark'];
    }
    
    return 'Needs Improvement';
}

function validateMarkInputs($data) {
    $errors = [];
    
    // Required fields validation
    $required = ['student_id', 'class_id', 'term_id', 'academic_year_id', 'mark_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '{$field}' is required";
        }
    }
    
    // Numeric validations
    if (!empty($data['student_id']) && (!is_numeric($data['student_id']) || (int)$data['student_id'] <= 0)) {
        $errors[] = "Invalid student ID";
    }
    
    if (!empty($data['class_id']) && (!is_numeric($data['class_id']) || (int)$data['class_id'] <= 0)) {
        $errors[] = "Invalid class ID";
    }
    
    if (!empty($data['term_id']) && (!is_numeric($data['term_id']) || (int)$data['term_id'] <= 0)) {
        $errors[] = "Invalid term ID";
    }
    
    if (!empty($data['academic_year_id']) && (!is_numeric($data['academic_year_id']) || (int)$data['academic_year_id'] <= 0)) {
        $errors[] = "Invalid academic year ID";
    }
    
    // Mark type validation
    $validMarkTypes = ['midterm', 'class_score', 'exam_score'];
    if (!empty($data['mark_type']) && !in_array($data['mark_type'], $validMarkTypes)) {
        $errors[] = "Invalid mark type";
    }
    
    // Marks validation
    if (isset($data['total_marks'])) {
        foreach ($data['total_marks'] as $mark) {
            if (!empty($mark) && (!is_numeric($mark) || $mark < 0 || $mark > 100)) {
                $errors[] = "Marks must be between 0 and 100";
                break;
            }
        }
    }
    
    return $errors;
}

// Handle get_marks_details action - FIXED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_marks_details') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $studentId = (int)$_POST['student_id'];
    $subjectId = (int)$_POST['subject_id'];
    $termId = (int)$_POST['term_id'];
    $yearId = (int)$_POST['academic_year_id'];
    
    // Get mark details from database
    $details = getMarkDetails($conn, $studentId, $subjectId, $termId, $yearId);
    
    if ($details) {
        echo json_encode([
            'status' => 'success',
            'details' => $details
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No marks found for the specified criteria'
        ]);
    }
    exit();
}

// Function to get mark details
function getMarkDetails($conn, $studentId, $subjectId, $termId, $yearId) {
    $sql = "SELECT 
                s.first_name, s.last_name,
                c.class_name,
                sub.subject_name,
                t.term_name,
                ay.year_name,
                COALESCE(mm.total_marks, 0) as mid_marks,
                COALESCE(csm.total_marks, 0) as class_marks,
                COALESCE(esm.total_marks, 0) as exam_marks,
                COALESCE(mm.mark_breakdown, '[]') as mid_breakdown,
                COALESCE(csm.mark_breakdown, '[]') as class_breakdown,
                COALESCE(esm.mark_breakdown, '[]') as exam_breakdown
            FROM students s
            JOIN classes c ON s.class_id = c.id
            JOIN subjects sub ON sub.id = ?
            JOIN terms t ON t.id = ?
            JOIN academic_years ay ON ay.id = ?
            LEFT JOIN midterm_marks mm ON mm.student_id = s.id AND mm.subject_id = sub.id AND mm.term_id = t.id AND mm.academic_year_id = ay.id
            LEFT JOIN class_score_marks csm ON csm.student_id = s.id AND csm.subject_id = sub.id AND csm.term_id = t.id AND csm.academic_year_id = ay.id
            LEFT JOIN exam_score_marks esm ON esm.student_id = s.id AND esm.subject_id = sub.id AND esm.term_id = t.id AND esm.academic_year_id = ay.id
            WHERE s.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $subjectId, $termId, $yearId, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Process mark breakdowns
        $midBreakdown = json_decode($row['mid_breakdown'], true) ?: [];
        $classBreakdown = json_decode($row['class_breakdown'], true) ?: [];
        $examBreakdown = json_decode($row['exam_breakdown'], true) ?: [];
        
        // Combine all mark breakdowns
        $markBreakdown = array_merge($midBreakdown, $classBreakdown, $examBreakdown);
        
        return [
            'student_name' => $row['first_name'] . ' ' . $row['last_name'],
            'class_name' => $row['class_name'],
            'subject_name' => $row['subject_name'],
            'term_name' => $row['term_name'],
            'year_name' => $row['year_name'],
            'mid_marks' => $row['mid_marks'],
            'class_marks' => $row['class_marks'],
            'exam_marks' => $row['exam_marks'],
            'mark_breakdown' => $markBreakdown,
            'total_marks' => $row['mid_marks'] + $row['class_marks'] + $row['exam_marks']
        ];
    }
    
    return false;
}

// Handle get_mark_details action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_mark_details') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $markId = (int)$_POST['mark_id'];
    $markType = trim($_POST['mark_type']);
    
    // Validate inputs
    if ($markId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid mark ID']);
        exit();
    }
    
    // Use whitelist approach for table selection
    $tableMap = [
        'midterm' => 'midterm_marks',
        'class_score' => 'class_score_marks',
        'exam_score' => 'exam_score_marks'
    ];
    
    if (!isset($tableMap[$markType])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid mark type']);
        exit();
    }
    
    $tableName = $tableMap[$markType];
    
    try {
        $sql = "
            SELECT 
                m.*,
                s.first_name,
                s.last_name,
                s.student_id as student_code,
                c.class_name,
                sub.subject_name,
                t.term_name AS term,
                ay.year_name,
                ay.is_current
            FROM `{$tableName}` m
            LEFT JOIN students s ON s.id = m.student_id
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN subjects sub ON sub.id = m.subject_id
            LEFT JOIN terms t ON t.id = m.term_id
            LEFT JOIN academic_years ay ON ay.id = m.academic_year_id
            WHERE m.id = ?
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare error");
        }
        
        $stmt->bind_param("i", $markId);
        
        if (!$stmt->execute()) {
            throw new Exception("Database execute error");
        }
        
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $markDetails = $result->fetch_assoc();
            
            // Extract individual marks
            $markBreakdown = [];
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($markDetails["mark$i"])) {
                    $markBreakdown[] = $markDetails["mark$i"];
                }
            }
            
            // Add additional details
            $markDetails['mark_type'] = $markType;
            $markDetails['table_name'] = $tableName;
            $markDetails['mark_breakdown'] = $markBreakdown;
            
            echo json_encode(['status' => 'success', 'details' => $markDetails]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Mark not found']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching mark details: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
    
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_mark') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = 'Invalid CSRF token';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Validate required fields
    if (!isset($_POST['mark_id']) || !isset($_POST['mark_type']) || !isset($_POST['total_marks'])) {
        $_SESSION['message'] = 'Missing required fields';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $markId = (int)$_POST['mark_id'];
    $markType = trim($_POST['mark_type']);
    $totalMarks = (float)$_POST['total_marks'];
    
    // Validate input
    if ($markId <= 0) {
        $_SESSION['message'] = 'Invalid mark ID';
        $_SESSION['message_type'] = 'danger';  // Fixed: Added missing quote
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    if ($totalMarks < 0 || $totalMarks > 100) {
        $_SESSION['message'] = 'Marks must be between 0 and 100';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Determine table name with validation
    $allowedMarkTypes = ['midterm', 'class_score', 'exam_score'];
    if (!in_array($markType, $allowedMarkTypes)) {
        $_SESSION['message'] = 'Invalid mark type';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    switch ($markType) {
        case 'midterm':
            $tableName = 'midterm_marks';
            break;
        case 'class_score':
            $tableName = 'class_score_marks';
            break;
        case 'exam_score':
            $tableName = 'exam_score_marks';
            break;
        default:
            $_SESSION['message'] = 'Invalid mark type';
            $_SESSION['message_type'] = 'danger';
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
    }
    
    try {
        // Start transaction
        if (!$conn->begin_transaction()) {
            throw new Exception("Failed to start transaction");
        }
        
        // Verify the record exists
        $check_stmt = $conn->prepare("SELECT * FROM `$tableName` WHERE id = ? LIMIT 1");
        if (!$check_stmt) {
            throw new Exception("Failed to prepare check query: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $markId);
        if (!$check_stmt->execute()) {
            throw new Exception("Failed to execute check query: " . $check_stmt->error);
        }
        
        $check_result = $check_stmt->get_result();  // Added: Get the result
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            throw new Exception("Mark record not found");
        }
        $check_stmt->close();
        
        // Get table columns
        $columns_result = $conn->query("SHOW COLUMNS FROM `$tableName`");
        if (!$columns_result) {
            throw new Exception("Failed to get table columns: " . $conn->error);
        }
        
        $table_columns = [];
        while ($column = $columns_result->fetch_assoc()) {
            $table_columns[] = $column['Field'];
        }
        $columns_result->free();  // Free the result
        
        // Build update query
        $update_fields = ['total_marks = ?'];
        $update_params = [$totalMarks];
        $update_types = "d";
        
        // Add individual marks
        for ($i = 1; $i <= 10; $i++) {
            $markField = "mark_$i";
            $columnName = "mark$i";
            
            if (in_array($columnName, $table_columns) && isset($_POST[$markField])) {
                $markValue = $_POST[$markField];
                
                if ($markValue === '' || $markValue === null) {
                    $update_fields[] = "$columnName = NULL";
                } else {
                    $markValue = (float)$markValue;
                    if ($markValue < 0 || $markValue > 100) {
                        throw new Exception("Individual mark $i must be between 0 and 100");
                    }
                    $update_fields[] = "$columnName = ?";
                    $update_params[] = $markValue;
                    $update_types .= "d";
                }
            }
        }
        
        // Add mark ID for WHERE clause
        $update_params[] = $markId;
        $update_types .= "i";
        
        $sql = "UPDATE `$tableName` SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        
        if (!$stmt->bind_param($update_types, ...$update_params)) {
            throw new Exception("Failed to bind parameters: " . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute update: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Failed to commit transaction");
        }
        
        // Set success message and redirect
        if ($affected_rows > 0) {
            $_SESSION['message'] = "Mark updated successfully!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "No changes detected. Marks remain the same.";
            $_SESSION['message_type'] = 'info';
        }
        
        // Redirect back to marks.php (or the main page)
        header("Location: marks.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn) {
            $conn->rollback();
        }
        
        // Set error message and redirect
        $_SESSION['message'] = 'Failed to update mark: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
        
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Handle add_marks action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_marks') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
            exit();
        }

        // Get form data
        $student_id = intval($_POST['student_id']);
        $class_id = intval($_POST['class_id']);
        $term_id = intval($_POST['term_id']);
        $academic_year_id = intval($_POST['academic_year_id']);
        $mark_type = $_POST['mark_type'];
        $subject_ids = $_POST['subject_id'] ?? [];
        $total_marks = $_POST['total_marks'] ?? [];
        
        // Validate inputs
        $validationErrors = validateMarkInputs([
            'student_id' => $student_id,
            'class_id' => $class_id,
            'term_id' => $term_id,
            'academic_year_id' => $academic_year_id,
            'mark_type' => $mark_type,
            'total_marks' => $total_marks
        ]);
        
        if (!empty($validationErrors)) {
            echo json_encode(['status' => 'error', 'message' => implode(', ', $validationErrors)]);
            exit();
        }

        // Determine the table based on mark type
        $table_name = '';
        switch ($mark_type) {
            case 'midterm':
                $table_name = 'midterm_marks';
                break;
            case 'class_score':
                $table_name = 'class_score_marks';
                break;
            case 'exam_score':
                $table_name = 'exam_score_marks';
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid mark type selected.']);
                exit();
        }
        
        // Check if subjects and total_marks are arrays and have the same number of elements
        if (!is_array($subject_ids) || !is_array($total_marks) || count($subject_ids) !== count($total_marks)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data format.']);
            exit();
        }

        // Filter out empty subject selections
        $valid_entries = [];
        foreach ($subject_ids as $key => $subject_id) {
            if (!empty($subject_id) && !empty($total_marks[$key])) {
                $entry = [
                    'subject_id' => intval($subject_id),
                    'total_marks' => floatval($total_marks[$key])
                ];
                
                // Add individual marks for this subject
                for ($i = 1; $i <= 10; $i++) {
                    $mark_key = "mark$i";
                    if (isset($_POST[$mark_key]) && is_array($_POST[$mark_key]) && isset($_POST[$mark_key][$key]) && !empty($_POST[$mark_key][$key])) {
                        $entry["mark$i"] = floatval($_POST[$mark_key][$key]);
                    }
                }
                
                $valid_entries[] = $entry;
            }
        }

        if (empty($valid_entries)) {
            echo json_encode(['status' => 'error', 'message' => 'No valid subject-mark combinations found.']);
            exit();
        }

        // Start transaction
        $conn->begin_transaction();

        $success_count = 0;
        
        // Check table structure
        $columns_query = "SHOW COLUMNS FROM `$table_name`";
        $columns_result = $conn->query($columns_query);
        $table_columns = [];
        
        if ($columns_result) {
            while ($column = $columns_result->fetch_assoc()) {
                $table_columns[] = $column['Field'];
            }
        }

        foreach ($valid_entries as $entry) {
            // Check for duplicate entry
            $check_sql = "SELECT id FROM `$table_name` WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
            $check_params = [$student_id, $entry['subject_id'], $term_id, $academic_year_id];
            
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iiii", ...$check_params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                continue; // Skip duplicate entries
            }
            $check_stmt->close();

            // Build insert SQL
            $insert_fields = ['student_id', 'subject_id', 'total_marks', 'term_id', 'academic_year_id'];
            $insert_values = ['?', '?', '?', '?', '?'];
            $insert_params = [$student_id, $entry['subject_id'], $entry['total_marks'], $term_id, $academic_year_id];
            $insert_types = "iidii";

            if (in_array('class_id', $table_columns)) {
                $insert_fields[] = 'class_id';
                $insert_values[] = '?';
                $insert_params[] = $class_id;
                $insert_types .= "i";
            }
                        
            // Add individual marks if they exist in the table and in the data
            for ($i = 1; $i <= 10; $i++) {
                if (in_array("mark$i", $table_columns) && isset($entry["mark$i"])) {
                    $insert_fields[] = "mark$i";
                    $insert_values[] = '?';
                    $insert_params[] = $entry["mark$i"];
                    $insert_types .= "d";
                }
            }
            
            $sql = "INSERT INTO `$table_name` (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed");
            }
            
            $stmt->bind_param($insert_types, ...$insert_params);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                throw new Exception("Execute failed");
            }
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        if ($success_count > 0) {
            $table_display_name = ucwords(str_replace('_', ' ', str_replace('_marks', '', $table_name)));
            echo json_encode(['status' => 'success', 'message' => "$success_count marks added successfully to $table_display_name table."]);
        } else {
            echo json_encode(['status' => 'warning', 'message' => "No new marks were added. Records may already exist."]);
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error in marks_control.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while saving marks']);
    } finally {
        $conn->close();
    }
    exit();
}

// Handle AJAX request for filtering students by class
// Handle AJAX request for filtering students by class
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_students_by_class') {
    
    // Set proper JSON header
    header('Content-Type: application/json');
    
    $class_id = intval($_GET['class_id'] ?? 0);
    $academic_year_id = intval($_GET['academic_year_id'] ?? 0);

    if ($class_id > 0) {
        $students = [];

        $students_sql = "SELECT s.id AS student_id, s.first_name, s.last_name 
                         FROM students s 
                         WHERE s.class_id = ?
                         ORDER BY s.first_name, s.last_name";

        $stmt = $conn->prepare($students_sql);
        if ($stmt) {
            $stmt->bind_param("i", $class_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $students[] = $row;
                }
                
                echo json_encode(['status' => 'success', 'students' => $students]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to execute query']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare query']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid class ID.']);
    }
    
    exit();
}

// Handle delete_marks action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_marks') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $student_id = (int)$_POST['student_id'];
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    $year_id = (int)$_POST['academic_year_id'];
    $mark_type = $_POST['mark_type'];
    
    // Validate inputs
    if ($student_id <= 0 || $subject_id <= 0 || $term_id <= 0 || $year_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
        exit();
    }
    
    // Determine table based on mark type
    $table_name = '';
    switch ($mark_type) {
        case 'midterm':
            $table_name = 'midterm_marks';
            break;
        case 'class_score':
            $table_name = 'class_score_marks';
            break;
        case 'exam_score':
            $table_name = 'exam_score_marks';
            break;
        case 'all':
            // Handle deletion from all tables
            $this->deleteFromAllMarkTables($conn, $student_id, $subject_id, $term_id, $year_id);
            echo json_encode(['status' => 'success', 'message' => 'All marks deleted successfully']);
            exit();
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid mark type']);
            exit();
    }
    
    try {
        // Delete the mark
        $sql = "DELETE FROM `$table_name` WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database prepare error");
        }
        
        $stmt->bind_param("iiii", $student_id, $subject_id, $term_id, $year_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Mark deleted successfully']);
            } else {
                echo json_encode(['status' => 'warning', 'message' => 'No marks found to delete']);
            }
        } else {
            throw new Exception("Database execute error");
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error deleting mark: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete mark']);
    }
    
    exit();
}

// Helper function to delete from all mark tables
function deleteFromAllMarkTables($conn, $student_id, $subject_id, $term_id, $year_id) {
    $tables = ['midterm_marks', 'class_score_marks', 'exam_score_marks'];
    
    foreach ($tables as $table_name) {
        $sql = "DELETE FROM `$table_name` WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("iiii", $student_id, $subject_id, $term_id, $year_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

?>