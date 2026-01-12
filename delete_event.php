<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = intval($_POST['id']);

try {
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
} catch (Exception $e) {
    error_log("Delete Event Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting event']);
}
?>