<?php
require_once 'config.php';
require_once 'session.php';

if ($_SESSION['role'] !== 'teacher') {
    header('Location: access_denied.php');
    exit();
}


// Test the getTeacherIdFromUserId function
function testGetTeacherIdFromUserId($conn, $user_id) {
    $sql = "SELECT id FROM teachers WHERE user_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        return $teacher['id'];
    }
    return null;
}

// Get the teacher user info
$teacher_user_id = 6; // Richard's user_id
$teacher_info = null;
$teacher_id_result = null;

$sql = "SELECT u.*, t.id as teacher_db_id, t.teacher_id as teacher_code, t.status as teacher_status
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $teacher_info = $result->fetch_assoc();
}

// Test the function
$teacher_id_result = testGetTeacherIdFromUserId($conn, $teacher_user_id);

// Check attendance table constraints
$constraint_sql = "SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'attendance'
AND REFERENCED_TABLE_NAME IS NOT NULL";
$constraints = $conn->query($constraint_sql);

// Check if there are any invalid teacher_id values in attendance
$invalid_sql = "SELECT a.id, a.teacher_id, a.attendance_date, a.class_id
                FROM attendance a
                LEFT JOIN teachers t ON a.teacher_id = t.id
                WHERE t.id IS NULL
                LIMIT 10";
$invalid_result = $conn->query($invalid_sql);

// Get a sample of attendance records
$sample_sql = "SELECT a.*, t.teacher_id as teacher_code, t.first_name, t.last_name
               FROM attendance a
               LEFT JOIN teachers t ON a.teacher_id = t.id
               ORDER BY a.created_at DESC
               LIMIT 5";
$sample_result = $conn->query($sample_sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Attendance Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-ok {
            color: #4CAF50;
            font-weight: bold;
        }
        .status-error {
            color: #f44336;
            font-weight: bold;
        }
        .status-warning {
            color: #ff9800;
            font-weight: bold;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #45a049;
        }
        .test-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .test-btn:hover {
            background: #0b7dda;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }
        .alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            color: #e65100;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Advanced Attendance Debug Tool</h1>
        
        <!-- Teacher Info Section -->
        <div class="section">
            <h2>👤 Teacher Information</h2>
            <?php if ($teacher_info): ?>
                <div class="info-grid">
                    <div class="info-label">User ID:</div>
                    <div class="info-value"><?= $teacher_info['id'] ?></div>
                    
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?= htmlspecialchars($teacher_info['full_name']) ?></div>
                    
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?= htmlspecialchars($teacher_info['email']) ?></div>
                    
                    <div class="info-label">Role:</div>
                    <div class="info-value"><?= htmlspecialchars($teacher_info['role']) ?></div>
                    
                    <div class="info-label">Teacher DB ID:</div>
                    <div class="info-value">
                        <?php if ($teacher_info['teacher_db_id']): ?>
                            <span class="status-ok"><?= $teacher_info['teacher_db_id'] ?> ✓</span>
                        <?php else: ?>
                            <span class="status-error">NULL ✗</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-label">Teacher Code:</div>
                    <div class="info-value"><?= htmlspecialchars($teacher_info['teacher_code'] ?? 'N/A') ?></div>
                    
                    <div class="info-label">Teacher Status:</div>
                    <div class="info-value">
                        <?php if ($teacher_info['teacher_status'] === 'active'): ?>
                            <span class="status-ok"><?= $teacher_info['teacher_status'] ?> ✓</span>
                        <?php else: ?>
                            <span class="status-warning"><?= $teacher_info['teacher_status'] ?? 'N/A' ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-label">Function Test Result:</div>
                    <div class="info-value">
                        <?php if ($teacher_id_result): ?>
                            <span class="status-ok">Returns: <?= $teacher_id_result ?> ✓</span>
                        <?php else: ?>
                            <span class="status-error">Returns: NULL ✗</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-error">Teacher user not found!</div>
            <?php endif; ?>
        </div>

        <!-- Database Constraints -->
        <div class="section">
            <h2>🔗 Database Foreign Key Constraints</h2>
            <table>
                <thead>
                    <tr>
                        <th>Constraint Name</th>
                        <th>Column</th>
                        <th>References Table</th>
                        <th>References Column</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($constraint = $constraints->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($constraint['CONSTRAINT_NAME']) ?></code></td>
                            <td><code><?= htmlspecialchars($constraint['COLUMN_NAME']) ?></code></td>
                            <td><code><?= htmlspecialchars($constraint['REFERENCED_TABLE_NAME']) ?></code></td>
                            <td><code><?= htmlspecialchars($constraint['REFERENCED_COLUMN_NAME']) ?></code></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Invalid Records -->
        <div class="section">
            <h2>⚠️ Invalid Attendance Records (Orphaned teacher_id)</h2>
            <?php if ($invalid_result->num_rows > 0): ?>
                <div class="alert alert-error">
                    <strong>Warning:</strong> Found <?= $invalid_result->num_rows ?> attendance record(s) with invalid teacher_id!
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Attendance ID</th>
                            <th>Teacher ID (Invalid)</th>
                            <th>Date</th>
                            <th>Class ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($invalid = $invalid_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $invalid['id'] ?></td>
                                <td class="status-error"><?= $invalid['teacher_id'] ?? 'NULL' ?></td>
                                <td><?= $invalid['attendance_date'] ?></td>
                                <td><?= $invalid['class_id'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-success">
                    ✓ No invalid attendance records found. All teacher_id values are valid.
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Attendance Sample -->
        <div class="section">
            <h2>📋 Recent Attendance Records (Sample)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Teacher ID</th>
                        <th>Teacher Name</th>
                        <th>Date</th>
                        <th>Class ID</th>
                        <th>Student ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sample_result->num_rows > 0): ?>
                        <?php while ($sample = $sample_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sample['id'] ?></td>
                                <td><?= $sample['teacher_id'] ?></td>
                                <td><?= htmlspecialchars(($sample['first_name'] ?? '') . ' ' . ($sample['last_name'] ?? '')) ?></td>
                                <td><?= $sample['attendance_date'] ?></td>
                                <td><?= $sample['class_id'] ?></td>
                                <td><?= $sample['student_id'] ?></td>
                                <td><?= $sample['status'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No attendance records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Test Attendance Submission -->
        <div class="section">
            <h2>🧪 Test Attendance Submission</h2>
            <p>This will simulate the attendance marking process and show you where the error occurs.</p>
            
            <form method="POST" action="test_attendance_submission.php" target="_blank">
                <input type="hidden" name="test_mode" value="1">
                <button type="submit" class="test-btn">Run Test Submission</button>
            </form>
            
            <div style="margin-top: 20px;">
                <h3>SQL Query that will be executed:</h3>
                <code style="display: block; padding: 15px; background: #f4f4f4; overflow-x: auto;">
                    INSERT INTO attendance 
                    (student_id, class_id, academic_year_id, term_id, teacher_id, 
                     attendance_date, status, time_in, time_out, remarks, marked_by, created_at) 
                    VALUES (?, ?, ?, ?, <strong style="color: red;"><?= $teacher_id_result ?? 'NULL' ?></strong>, ?, ?, ?, NULL, ?, ?, CURRENT_TIMESTAMP)
                </code>
            </div>
        </div>

        <a href="admin_dashboard.php" class="btn">← Back to Dashboard</a>
        <a href="verify_teacher_links.php" class="btn">View Teacher Links</a>
    </div>
</body>
</html>