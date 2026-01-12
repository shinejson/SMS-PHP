<?php
// api/ping.php
require_once './config.php';
require_once './session.php';

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    // Update session activity
    $_SESSION['LAST_ACTIVITY'] = time();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session extended',
        'user_id' => $_SESSION['user_id']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No active session'
    ]);
}
?>