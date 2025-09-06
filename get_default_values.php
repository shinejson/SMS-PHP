<?php
require_once 'config.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users

function getDefaultTermId($conn) {
    try {
        // First try to get current term
        $sql = "SELECT id FROM terms WHERE is_current = 1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['id'];
        }
        
        // Fallback: get the most recent term by creation date or ID
        $sql = "SELECT id FROM terms ORDER BY created_at DESC, id DESC LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['id'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting default term ID: " . $e->getMessage());
        return null;
    }
}

function getDefaultAcademicYearId($conn) {
    try {
        // First try to get current academic year
        $sql = "SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['id'];
        }
        
        // Fallback: get the most recent academic year by year name
        $sql = "SELECT id FROM academic_years ORDER BY year_name DESC LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['id'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting default academic year ID: " . $e->getMessage());
        return null;
    }
}

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Only GET requests are allowed'
    ]);
    exit();
}

// Validate student_id parameter
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error',
        'message' => 'Student ID parameter is required'
    ]);
    exit();
}

// Sanitize and validate student_id
$studentId = filter_var($_GET['student_id'], FILTER_VALIDATE_INT);

if ($studentId === false || $studentId <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error',
        'message' => 'Valid Student ID must be a positive integer'
    ]);
    exit();
}

try {
    // Verify student exists and get basic info
    $studentCheckSql = "SELECT id, first_name, last_name, class_id FROM students WHERE id = ?";
    $stmt = $conn->prepare($studentCheckSql);
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $studentId);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $studentResult = $stmt->get_result();

    if ($studentResult->num_rows === 0) {
        http_response_code(404); // Not Found
        echo json_encode([
            'status' => 'error',
            'message' => 'Student not found'
        ]);
        exit();
    }
    
    $studentData = $studentResult->fetch_assoc();
    $stmt->close();

    // Get default term and academic year
    $termId = getDefaultTermId($conn);
    $academicYearId = getDefaultAcademicYearId($conn);

    if ($termId && $academicYearId) {
        echo json_encode([
            'status' => 'success',
            'term_id' => $termId,
            'academic_year_id' => $academicYearId,
            'student_id' => $studentId,
            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
            'class_id' => $studentData['class_id']
        ]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not determine default term and academic year. Please ensure terms and academic years are properly configured in the system.',
            'student_id' => $studentId
        ]);
    }

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error in get_defaults.php: " . $e->getMessage());
    
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'An internal server error occurred. Please try again later.'
    ]);
}

// Don't close the connection as it might be used by other parts of the application
?>