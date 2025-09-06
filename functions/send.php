<?php
function sendSmsReceipt($recipient, $message, $apiKey, $senderId, $apiEndpoint) {
    // Add debug logging
    error_log("SMS Debug - Recipient: " . $recipient);
    error_log("SMS Debug - Message: " . $message);
    error_log("SMS Debug - Sender ID: " . $senderId);
    
    $payload = [
        "messages" => [
            [
                "from" => $senderId,
                "destinations" => [["to" => $recipient]],
                "text" => $message
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($apiEndpoint, '/') . "/sms/2/text/advanced");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: App " . $apiKey,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Enhanced logging
    error_log("SMS Debug - HTTP Code: " . $httpCode);
    error_log("SMS Debug - Response: " . $response);
    error_log("SMS Debug - cURL Error: " . $error);

    // Parse the response to check actual delivery status
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && empty($error)) {
        // Check if Infobip returned any message status details
        if (isset($responseData['messages']) && !empty($responseData['messages'])) {
            $messageStatus = $responseData['messages'][0]['status'] ?? null;
            error_log("SMS Debug - Message Status: " . json_encode($messageStatus));
            
            // Return more detailed response
            return [
                'status' => 'success', 
                'message' => 'SMS submitted successfully.',
                'response_data' => $responseData,
                'infobip_status' => $messageStatus
            ];
        }
        return ['status' => 'success', 'message' => 'SMS sent successfully.', 'response_data' => $responseData];
    } else {
        return ['status' => 'error', 'message' => "SMS failed. HTTP: $httpCode. Error: $error. Response: $response"];
    }
}

?>