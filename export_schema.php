<?php
// export_schema.php - Run via browser or CLI
$host = 'localhost';
$username = 'root';
$password = ''; // Set your password
$dbname = 'gebsco_db';
$output_file = 'schema_only.sql';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Get all tables
$result = $conn->query('SHOW TABLES');
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$dump = '';
foreach ($tables as $table) {
    $dump .= $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'] . ";\n\n";
}

// Write to file
file_put_contents($output_file, $dump);

echo "Schema exported to $output_file";
$conn->close();
?>