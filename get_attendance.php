<?php

header('Content-Type: application/json');
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

// Check access permissions
checkAccess(['admin', 'teacher', 'staff']);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Error handler to catch PHP warnings and output JSON
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $message in $file on line $line"
    ]);
    exit;
});

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

try {
    $action = $_GET['action'] ?? null;

    if ($action === 'get_students') {
        $class_id = intval($_GET['class_id'] ?? 0);
        $academic_year = trim($_GET['academic_year'] ?? '');
        $specific_student_id = intval($_GET['student_id'] ?? 0);

        // IMPORTANT: Require at least class_id OR specific_student_id
        if (!$class_id && !$specific_student_id) {
            throw new Exception('Either class_id or student_id is required.');
        }

        // Build dynamic query with proper filtering
        $sql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.class_id, s.academic_year_id 
                FROM students s 
                WHERE s.status = 'active'";
        $params = [];
        $types = "";

        // If class_id is provided, filter by it
        if ($class_id) {
            $sql .= " AND s.class_id = ?";
            $params[] = $class_id;
            $types .= "i";
        }
        
        // CRITICAL: Filter by academic year if provided (ensures only students in selected year)
        if (!empty($academic_year)) {
            $sql .= " AND s.academic_year_id IN (SELECT id FROM academic_years WHERE year_name = ?)";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        // If specific student is requested
        if ($specific_student_id) {
            $sql .= " AND s.id = ?";
            $params[] = $specific_student_id;
            $types .= "i";
        }

        $sql .= " ORDER BY s.first_name, s.last_name";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }

        // Bind parameters only if there are any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        $response['success'] = true;
        $response['students'] = $students;
        $response['filters'] = [
            'class_id' => $class_id,
            'academic_year' => $academic_year,
            'student_id' => $specific_student_id,
            'total_students' => count($students)
        ];

} elseif ($action === 'get_classes') {
    $academic_year = $_GET['academic_year'] ?? null; // still receive it, but not used for filtering

    $sql = "SELECT c.id, c.class_name, c.class_teacher_id, c.description 
            FROM classes c 
            ORDER BY c.class_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'academic_year_searched' => $academic_year,
        'total_classes' => count($classes)
    ]);
    exit;
}
 elseif ($action === 'get_attendance') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $attendanceDate = $_GET['attendance_date'] ?? null;
    $academicYearId = (int)($_GET['academic_year_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);

    if (!$classId || !$attendanceDate) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: class_id or attendance_date'
        ]);
        exit;
    }

    $sql = "SELECT a.*, 
                   s.first_name, s.last_name, s.student_id AS student_code,
                   c.class_name, ay.year_name AS academic_year_name, t.term_name
            FROM attendance a
            INNER JOIN students s ON s.id = a.student_id
            INNER JOIN classes c ON c.id = a.class_id
            INNER JOIN academic_years ay ON ay.id = a.academic_year_id
            INNER JOIN terms t ON t.id = a.term_id
            WHERE a.class_id = ? 
              AND a.attendance_date = ?";

    $params = [$classId, $attendanceDate];
    $types = "is";

    // 🧭 Optional filter by academic year
    if ($academicYearId > 0) {
        $sql .= " AND a.academic_year_id = ?";
        $params[] = $academicYearId;
        $types .= "i";
    }

    // 🧭 Optional filter by term
    if ($termId > 0) {
        $sql .= " AND a.term_id = ?";
        $params[] = $termId;
        $types .= "i";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'SQL Prepare Error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'records' => $records,
        'class_id' => $classId,
        'academic_year_id' => $academicYearId,
        'term_id' => $termId,
        'total' => count($records)
    ]);
    exit;
}
 // Replace the 'get_attendance_by_id' action with this:

 elseif ($action === 'get_attendance_by_id') {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('Attendance ID required.');
    }
    
    // Updated query to get both student IDs clearly
    $sql = "SELECT 
                a.id,
                a.student_id as student_db_id,
                a.class_id,
                a.attendance_date,
                a.status,
                a.time_in,
                a.time_out,
                a.remarks,
                a.academic_year_id,
                a.term_id,
                a.marked_by,
                a.created_at,
                s.first_name, 
                s.last_name, 
                s.student_id as student_code,
                c.class_name 
            FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            LEFT JOIN classes c ON a.class_id = c.id 
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        $response['success'] = true;
        $response['record'] = $record;
    } else {
        $response['success'] = false;
        $response['message'] = 'Attendance record not found.';
    }
    $stmt->close();
} else {
        $response['message'] = 'Invalid action.';
    }

} catch (Exception $ex) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $ex->getMessage();
    http_response_code(400);
}

echo json_encode($response);
exit;
?>