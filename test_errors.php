<?php
// test_errors.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

echo "Testing database connection...<br>";

if ($conn) {
    echo "Database connected successfully!<br>";
    
    // Test a simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        echo "Query executed successfully!<br>";
    } else {
        echo "Query failed: " . $conn->error . "<br>";
    }
} else {
    echo "Database connection failed!<br>";
}
?>