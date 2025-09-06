<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_billing'])) {
    $id = intval($_POST['id'] ?? 0);
    
    try {
        // First delete any tuition details if they exist
        $conn->query("DELETE FROM tuition_details WHERE billing_id = $id");
        
        // Then delete the main billing record
        $result = $conn->query("DELETE FROM billing WHERE id = $id");
        
        if ($conn->affected_rows > 0) {
            $response = [
                'success' => true,
                'message' => 'Billing record deleted successfully'
            ];
        } else {
            $response['message'] = 'No record found to delete';
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        http_response_code(500);
    }
}

echo json_encode($response);
exit;