<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Test database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Test if tables exist
    $tables = ['academic_years', 'students', 'student', 'classes', 'terms'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $existingTables[] = $table;
            
            // Get record count for each existing table
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($countResult) {
                $count = $countResult->fetch_assoc()['count'];
                $existingTables[count($existingTables) - 1] = "$table ($count records)";
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'database_connected' => true,
        'existing_tables' => $existingTables,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>