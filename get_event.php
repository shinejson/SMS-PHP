<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = intval($_GET['id']);

try {
    $stmt = $conn->prepare("
        SELECT e.*, ay.year_name, t.term_name 
        FROM events e 
        LEFT JOIN academic_years ay ON e.academic_year_id = ay.id 
        LEFT JOIN terms t ON e.term_id = t.id 
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }
} catch (Exception $e) {
    error_log("Get Event Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving event']);
}
?>