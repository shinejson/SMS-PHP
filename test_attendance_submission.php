<?php
require_once 'config.php';
require_once 'session.php';


if ($_SESSION['role'] !== 'teacher') {
    header('Location: access_denied.php');
    exit();
}


header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Submission Test</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 8px;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #1e1e1e;
            border-left: 4px solid #4ec9b0;
            border-radius: 4px;
        }
        .success {
            border-left-color: #4caf50;
            color: #4caf50;
        }
        .error {
            border-left-color: #f44336;
            color: #f44336;
        }
        .warning {
            border-left-color: #ff9800;
            color: #ff9800;
        }
        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            color: #ce9178;
        }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            color: #d4d4d4;
        }
        .value {
            color: #b5cea8;
            font-weight: bold;
        }
        .key {
            color: #9cdcfe;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Attendance Submission Test Results</h1>

<?php
// Step 1: Check session
echo '<div class="step">';
echo '<h2>Step 1: Session Check</h2>';
echo '<p><span class="key">User ID:</span> <span class="value">' . ($_SESSION['user_id'] ?? 'NOT SET') . '</span></p>';
echo '<p><span class="key">User Role:</span> <span class="value">' . ($_SESSION['role'] ?? 'NOT SET') . '</span></p>';
echo '<p><span class="key">Full Name:</span> <span class="value">' . ($_SESSION['full_name'] ?? 'NOT SET') . '</span></p>';
echo '</div>';

// Step 2: Get teacher_id
echo '<div class="step">';
echo '<h2>Step 2: Fetch Teacher ID</h2>';

$user_id = 6; // Richard's user_id
$sql = "SELECT id, teacher_id, first_name, last_name, status FROM teachers WHERE user_id = ? AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $teacher = $result->fetch_assoc();
    echo '<div class="success">';
    echo '<p>✓ Teacher record found!</p>';
    echo '<pre>' . print_r($teacher, true) . '</pre>';
    echo '</div>';
    $teacher_id = $teacher['id'];
} else {
    echo '<div class="error">';
    echo '<p>✗ No teacher record found for user_id: ' . $user_id . '</p>';
    echo '</div>';
    $teacher_id = null;
}
$stmt->close();
echo '</div>';

// Step 3: Prepare test data
echo '<div class="step">';
echo '<h2>Step 3: Prepare Test Data</h2>';

// Get a test student
$student_sql = "SELECT id FROM students WHERE status = 'active' LIMIT 1";
$student_result = $conn->query($student_sql);
$student_id = null;
if ($student_result && $student_result->num_rows > 0) {
    $student_row = $student_result->fetch_assoc();
    $student_id = $student_row['id'];
    echo '<p><span class="key">Test Student ID:</span> <span class="value">' . $student_id . '</span></p>';
} else {
    echo '<div class="error"><p>✗ No active students found</p></div>';
}

// Get a test class
$class_sql = "SELECT id FROM classes LIMIT 1";
$class_result = $conn->query($class_sql);
$class_id = null;
if ($class_result && $class_result->num_rows > 0) {
    $class_row = $class_result->fetch_assoc();
    $class_id = $class_row['id'];
    echo '<p><span class="key">Test Class ID:</span> <span class="value">' . $class_id . '</span></p>';
} else {
    echo '<div class="error"><p>✗ No classes found</p></div>';
}

// Get academic year and term
$year_sql = "SELECT id FROM academic_years ORDER BY start_date DESC LIMIT 1";
$year_result = $conn->query($year_sql);
$academic_year_id = null;
if ($year_result && $year_result->num_rows > 0) {
    $year_row = $year_result->fetch_assoc();
    $academic_year_id = $year_row['id'];
    echo '<p><span class="key">Academic Year ID:</span> <span class="value">' . $academic_year_id . '</span></p>';
}

$term_sql = "SELECT id FROM terms ORDER BY start_date DESC LIMIT 1";
$term_result = $conn->query($term_sql);
$term_id = null;
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['id'];
    echo '<p><span class="key">Term ID:</span> <span class="value">' . $term_id . '</span></p>';
}

$attendance_date = date('Y-m-d');
$status = 'present';
$remarks = 'Test attendance record';
$marked_by = $_SESSION['user_id'];

echo '<p><span class="key">Date:</span> <span class="value">' . $attendance_date . '</span></p>';
echo '<p><span class="key">Status:</span> <span class="value">' . $status . '</span></p>';
echo '<p><span class="key">Teacher ID to use:</span> <span class="value">' . ($teacher_id ?? 'NULL') . '</span></p>';
echo '</div>';

// Step 4: Attempt insert
echo '<div class="step">';
echo '<h2>Step 4: Test INSERT Query</h2>';

if ($student_id && $class_id && $academic_year_id && $term_id && $teacher_id) {
    echo '<p>Attempting to insert test record...</p>';
    
    $insertSql = "INSERT INTO attendance 
        (student_id, class_id, academic_year_id, term_id, teacher_id, 
         attendance_date, status, time_in, time_out, remarks, marked_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIME, NULL, ?, ?, CURRENT_TIMESTAMP)";
    
    echo '<p><strong>SQL Query:</strong></p>';
    echo '<pre>' . $insertSql . '</pre>';
    
    echo '<p><strong>Parameters:</strong></p>';
    echo '<pre>';
    echo "student_id: $student_id\n";
    echo "class_id: $class_id\n";
    echo "academic_year_id: $academic_year_id\n";
    echo "term_id: $term_id\n";
    echo "teacher_id: $teacher_id\n";
    echo "attendance_date: $attendance_date\n";
    echo "status: $status\n";
    echo "remarks: $remarks\n";
    echo "marked_by: $marked_by\n";
    echo '</pre>';
    
    $insertStmt = $conn->prepare($insertSql);
    if ($insertStmt) {
        $insertStmt->bind_param("iiiiisssi",
            $student_id, $class_id, $academic_year_id, $term_id, $teacher_id,
            $attendance_date, $status, $remarks, $marked_by
        );
        
        if ($insertStmt->execute()) {
            echo '<div class="success">';
            echo '<p>✓ INSERT SUCCESSFUL!</p>';
            echo '<p>Inserted ID: ' . $insertStmt->insert_id . '</p>';
            echo '</div>';
            
            // Clean up test record
            $delete_sql = "DELETE FROM attendance WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_id = $insertStmt->insert_id;
            $delete_stmt->bind_param("i", $delete_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            echo '<p style="color: #ff9800;">Test record cleaned up (deleted).</p>';
        } else {
            echo '<div class="error">';
            echo '<p>✗ INSERT FAILED!</p>';
            echo '<p><strong>Error:</strong> ' . $insertStmt->error . '</p>';
            echo '<p><strong>Error Code:</strong> ' . $insertStmt->errno . '</p>';
            echo '</div>';
        }
        $insertStmt->close();
    } else {
        echo '<div class="error">';
        echo '<p>✗ Failed to prepare statement</p>';
        echo '<p><strong>Error:</strong> ' . $conn->error . '</p>';
        echo '</div>';
    }
} else {
    echo '<div class="warning">';
    echo '<p>⚠ Cannot proceed with test - missing required data:</p>';
    echo '<ul>';
    if (!$student_id) echo '<li>Student ID is missing</li>';
    if (!$class_id) echo '<li>Class ID is missing</li>';
    if (!$academic_year_id) echo '<li>Academic Year ID is missing</li>';
    if (!$term_id) echo '<li>Term ID is missing</li>';
    if (!$teacher_id) echo '<li>Teacher ID is NULL</li>';
    echo '</ul>';
    echo '</div>';
}
echo '</div>';

// Step 5: Verify teachers table
echo '<div class="step">';
echo '<h2>Step 5: Verify Teachers Table</h2>';
$verify_sql = "SELECT * FROM teachers WHERE user_id = $user_id";
$verify_result = $conn->query($verify_sql);
if ($verify_result && $verify_result->num_rows > 0) {
    echo '<div class="success">';
    echo '<p>✓ Teacher record exists</p>';
    echo '<pre>' . print_r($verify_result->fetch_assoc(), true) . '</pre>';
    echo '</div>';
} else {
    echo '<div class="error">';
    echo '<p>✗ No teacher record found</p>';
    echo '</div>';
}
echo '</div>';

?>

        <p style="margin-top: 30px;">
            <a href="javascript:history.back()" style="color: #4ec9b0; text-decoration: none;">← Back to Debug Tool</a>
        </p>
    </div>
</body>
</html>