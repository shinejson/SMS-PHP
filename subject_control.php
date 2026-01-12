<?php
require_once 'config.php';
require_once 'session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];

    if ($action === 'add_subject') {
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $description = $conn->real_escape_string($_POST['description']);
        
        // Check if the subject name already exists
        $sql_check = "SELECT id FROM subjects WHERE subject_name = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $subject_name);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            // A subject with this name already exists
            $_SESSION['error'] = "Error: A subject named '$subject_name' already exists.";
        } else {
            // Generate a random, unique subject code
            $prefix = strtoupper(substr($subject_name, 0, 3));
            $unique_id = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            $subject_code = $prefix . '-' . $unique_id;

            // Optional: A more robust check for subject_code collision
            $sql_code_check = "SELECT subject_code FROM subjects WHERE subject_code = '$subject_code'";
            $result_code_check = $conn->query($sql_code_check);
            while ($result_code_check->num_rows > 0) {
                $unique_id = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
                $subject_code = $prefix . '-' . $unique_id;
                $sql_code_check = "SELECT subject_code FROM subjects WHERE subject_code = '$subject_code'";
                $result_code_check = $conn->query($sql_code_check);
            }
            
            // Insert the new subject
            $sql = "INSERT INTO subjects (subject_name, subject_code, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $conn->prepare($sql);
            $stmt_insert->bind_param("sss", $subject_name, $subject_code, $description);

            if ($stmt_insert->execute()) {
                $_SESSION['message'] = "Subject '$subject_name' added successfully with code: " . $subject_code;
            } else {
                $_SESSION['error'] = "Error adding subject: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
        
    } elseif ($action === 'update_subject') {
        $id = intval($_POST['id']);
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "UPDATE subjects SET subject_name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $subject_name, $description, $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Subject updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating subject: " . $stmt->error;
        }
        $stmt->close();
        
    } elseif ($_POST['form_action'] === 'delete_subject') {
    $subject_id = $_POST['id'];
    
    // Check if subject has related records
    $check_sql = "SELECT COUNT(*) as count FROM class_score_marks WHERE subject_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $subject_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error'] = "Cannot delete subject. It has " . $row['count'] . " related score records. Please delete the related records first.";
        header("Location: subjects.php");
        exit();
    }
    
    // Proceed with deletion if no dependencies
    $sql = "DELETE FROM subjects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Subject deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting subject: " . $conn->error;
    }
    
    header("Location: subjects.php");
    exit();
}

    $conn->close();
    header("Location: subjects.php");
    exit();
}
?>