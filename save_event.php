<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Required fields
$required = ['event_title', 'event_type', 'event_date', 'start_time', 'academic_year_id', 'term_id'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize input
$event_id = $_POST['event_id'] ?? null;
$event_title = trim($_POST['event_title']);
$event_type = trim($_POST['event_type']);
$event_date = trim($_POST['event_date']);
$start_time = trim($_POST['start_time']);
$end_time = trim($_POST['end_time'] ?? '');
$location = trim($_POST['location'] ?? '');
$academic_year_id = intval($_POST['academic_year_id']);
$term_id = intval($_POST['term_id']);
$description = trim($_POST['description'] ?? '');

try {
    if ($event_id) {
        // Update existing event
        $stmt = $conn->prepare("
            UPDATE events 
            SET event_title = ?, event_type = ?, event_date = ?, start_time = ?, end_time = ?, 
                location = ?, academic_year_id = ?, term_id = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssiisi", $event_title, $event_type, $event_date, $start_time, $end_time, 
                         $location, $academic_year_id, $term_id, $description, $event_id);
    } else {
        // Insert new event
        $stmt = $conn->prepare("
            INSERT INTO events (event_title, event_type, event_date, start_time, end_time, 
                               location, academic_year_id, term_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssiis", $event_title, $event_type, $event_date, $start_time, $end_time, 
                         $location, $academic_year_id, $term_id, $description);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Event saved successfully']);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
} catch (Exception $e) {
    error_log("Save Event Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving event']);
}
?>