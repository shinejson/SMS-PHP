<?php
// database_setup.php
$host = 'localhost';
$user = 'root';
$password = ''; // Default XAMPP password is empty
$database = 'gebsco_db';

// Create connection
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($database);

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        role ENUM('admin', 'teacher', 'staff') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(20) NOT NULL UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        dob DATE NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        address TEXT,
        parent_name VARCHAR(100),
        parent_contact VARCHAR(20),
        email VARCHAR(100),
        class_id INT,
        status ENUM('Active', 'Inactive', 'Graduated') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(50) NOT NULL,
        class_teacher_id INT,
        academic_year VARCHAR(20) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id VARCHAR(20) NOT NULL UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        specialization VARCHAR(100),
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        term ENUM('First', 'Second', 'Third', 'Final') NOT NULL,
        marks DECIMAL(5,2) NOT NULL,
        grade VARCHAR(2),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id),
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receipt_no VARCHAR(20) NOT NULL UNIQUE,
        student_id INT NOT NULL,
        payment_date DATE NOT NULL,
        payment_type ENUM('Tuition', 'Exam', 'Library', 'Other') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash', 'Cheque', 'Bank Transfer', 'Mobile Money') NOT NULL,
        status ENUM('Paid', 'Pending', 'Cancelled') DEFAULT 'Paid',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )"
];

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
    echo "Table created successfully<br>";
    } else {
   echo "Error creating table: " . $conn->error . "<br>";
    }
}


// Insert admin user if not exists
$passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (username, password, full_name, email, role) 
        VALUES ('admin', '$passwordHash', 'Admin User', 'admin@gebsco.edu', 'admin')";
$conn->query($sql);

echo "Database setup completed successfully!";
$conn->close();
?>