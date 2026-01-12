<?php
require_once 'config.php';

$student_id = $_GET['student_id'] ?? 1;

echo "<h1>Debug Student Records</h1>";
echo "<h3>Student ID: $student_id</h3>";

// Check if student exists
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "<p style='color: red;'>Student not found!</p>";
    exit;
}

echo "<p>Student: {$student['first_name']} {$student['last_name']}</p>";

// Check each marks table
$tables = ['midterm_marks', 'class_score_marks', 'exam_score_marks'];

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    
    $query = "SELECT COUNT(*) as count FROM $table WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    echo "<p>Records found: $count</p>";
    
    if ($count > 0) {
        $sample_query = "SELECT * FROM $table WHERE student_id = ? LIMIT 5";
        $stmt = $conn->prepare($sample_query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $records = $stmt->get_result();
        
        echo "<table border='1'><tr>";
        if ($records->num_rows > 0) {
            // Header row
            $field_info = $records->fetch_fields();
            foreach ($field_info as $field) {
                echo "<th>{$field->name}</th>";
            }
            echo "</tr>";
            
            // Data rows
            $records->data_seek(0); // Reset pointer
            while ($row = $records->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>$value</td>";
                }
                echo "</tr>";
            }
        }
        echo "</table>";
    }
}
?>