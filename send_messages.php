<?php
require_once 'config.php';
// Add PHPMailer autoloader
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// This script would be called by a cron job to process pending messages
$pending_messages = $conn->query("
    SELECT m.*, e.event_title, e.event_date, e.start_time, e.location,
           c.class_name, t.first_name as teacher_first, t.last_name as teacher_last,
           s.first_name as student_first, s.last_name as student_last, s.student_id
    FROM messages m
    LEFT JOIN events e ON m.event_id = e.id
    LEFT JOIN classes c ON m.specific_class_id = c.id
    LEFT JOIN teachers t ON m.specific_teacher_id = t.id
    LEFT JOIN students s ON m.specific_student_id = s.id
    WHERE m.status = 'Pending'
");

// Get school settings once
$school_settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();

while ($message = $pending_messages->fetch_assoc()) {
    // Update status to processing
    $conn->query("UPDATE messages SET status = 'Processing' WHERE id = {$message['id']}");
    
    try {
        // Get recipients based on recipient type
        $recipients = [];
        
        switch ($message['recipient_type']) {
            case 'all_teachers':
                $result = $conn->query("SELECT email, phone, first_name, last_name FROM teachers WHERE status = 'Active'");
                break;
                
            case 'all_parents':
                $result = $conn->query("
                    SELECT p.email, p.phone, p.first_name, p.last_name
                    FROM parents p 
                    INNER JOIN students s ON p.student_id = s.id 
                    WHERE s.status = 'Active'
                ");
                break;
                
            case 'specific_class':
                $result = $conn->query("
                    SELECT p.email, p.phone, p.first_name, p.last_name
                    FROM parents p 
                    INNER JOIN students s ON p.student_id = s.id 
                    WHERE s.class_id = {$message['specific_class_id']} AND s.status = 'Active'
                ");
                break;
                
            case 'specific_teacher':
                $result = $conn->query("
                    SELECT email, phone, first_name, last_name
                    FROM teachers 
                    WHERE id = {$message['specific_teacher_id']} AND status = 'Active'
                ");
                break;
                
            case 'specific_parent':
                $result = $conn->query("
                    SELECT p.email, p.phone, p.first_name, p.last_name
                    FROM parents p 
                    WHERE p.student_id = {$message['specific_student_id']}
                ");
                break;
        }
        
        $allSuccess = true;
        $failureReasons = [];
        $sentCount = 0;

        while ($recipient = $result->fetch_assoc()) {
            // --- Send Email ---
            if ($message['send_email'] && !empty($recipient['email'])) {
                try {
                    $emailSent = sendEmail(
                        $recipient['email'],
                        $message['subject'],
                        $message['content'],
                        $school_settings,
                        $recipient['first_name'] . ' ' . $recipient['last_name']
                    );

                    if ($emailSent) {
                        $sentCount++;
                    } else {
                        $allSuccess = false;
                        $failureReasons[] = "Email to {$recipient['email']} failed.";
                    }
                } catch (Exception $e) {
                    $allSuccess = false;
                    $failureReasons[] = "Email to {$recipient['email']} failed: " . $e->getMessage();
                }
            }

            // --- Send SMS ---
            if ($message['send_sms'] && !empty($recipient['phone'])) {
                try {
                    $smsSent = sendSMS($recipient['phone'], $message['content']);

                    if (!$smsSent) {
                        $allSuccess = false;
                        $failureReasons[] = "SMS to {$recipient['phone']} failed.";
                    }
                } catch (Exception $e) {
                    $allSuccess = false;
                    $failureReasons[] = "SMS to {$recipient['phone']} failed: " . $e->getMessage();
                }
            }
        }

        // --- Update DB status ---
        $status = ($allSuccess && $sentCount > 0) ? 'Sent' : 'Failed';
        $reason = !empty($failureReasons) ? implode(" | ", $failureReasons) : null;

        $stmt = $conn->prepare("
            UPDATE messages 
            SET status = ?, sent_at = NOW(), failure_reason = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $status, $reason, $message['id']);
        $stmt->execute();
        
        echo "Message ID {$message['id']}: $status. Sent to $sentCount recipients.\n";
        
    } catch (Exception $e) {
        // Update status to failed
        $stmt = $conn->prepare("UPDATE messages SET status = 'Failed', failure_reason = ? WHERE id = ?");
        $error_msg = "System error: " . $e->getMessage();
        $stmt->bind_param("si", $error_msg, $message['id']);
        $stmt->execute();
        
        error_log("Message sending failed: " . $e->getMessage());
        echo "Message ID {$message['id']}: Failed - " . $e->getMessage() . "\n";
    }
}

function sendEmail($to, $subject, $content, $school_settings, $recipientName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - MODIFIED FOR XAMPP
        $mail->SMTPDebug = 0; // Disable debug output (was causing issues)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $school_settings['email']; // Your Gmail address
        $mail->Password = $school_settings['app_password']; // App password, not regular password
        
        // FIXED: Use proper SSL settings for XAMPP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL encryption
        $mail->Port = 465; // SSL port
        
        // Alternative for non-SSL environments (uncomment if SSL still fails)
        // $mail->SMTPSecure = false;
        // $mail->SMTPAutoTLS = false;
        // $mail->Port = 587;
        
        // Recipients
        $mail->setFrom($school_settings['email'], $school_settings['school_name']);
        $mail->addAddress($to, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Create HTML email template with school branding
        $htmlContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>" . htmlspecialchars($subject) . "</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                    .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                    .content { padding: 30px 20px; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #dee2e6; }
                    .school-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
                    .school-info { font-size: 14px; opacity: 0.9; }
                    .message-content { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='school-name'>" . htmlspecialchars($school_settings['school_name']) . "</div>
                        <div class='school-info'>" . htmlspecialchars($school_settings['address']) . "</div>
                        <div class='school-info'>Phone: " . htmlspecialchars($school_settings['phone']) . "</div>
                    </div>
                    <div class='content'>
                        " . ($recipientName ? "<p>Dear " . htmlspecialchars($recipientName) . ",</p>" : "") . "
                        <div class='message-content'>
                            " . nl2br(htmlspecialchars($content)) . "
                        </div>
                    </div>
                    <div class='footer'>
                        <p>This email was sent by " . htmlspecialchars($school_settings['school_name']) . "</p>
                        <p>Please do not reply to this automated message.</p>
                        <p>Sent on: " . date('Y-m-d H:i:s') . "</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->Body = $htmlContent;
        
        // Alternative plain text version
        $mail->AltBody = "Dear $recipientName,\n\n" . $content . "\n\n---\n" . $school_settings['school_name'];
        
        $result = $mail->send();
        
        if ($result) {
            error_log("Email sent successfully to: $to");
            return true;
        } else {
            error_log("Email failed to send to: $to - Unknown error");
            return false;
        }
        
    } catch (Exception $e) {
        $errorMsg = "Email sending failed to $to: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage();
        error_log($errorMsg);
        throw new Exception($errorMsg);
    }
}

function sendSMS($phone, $content) {
    // Implement SMS sending logic using an SMS gateway API
    // This is a placeholder implementation - replace with your SMS provider
    
    try {
        // Example for generic SMS API (replace with your provider)
        $api_key = "YOUR_SMS_API_KEY";
        $sender_id = "SCHOOL";
        
        $data = array(
            'api_key' => $api_key,
            'to' => $phone,
            'from' => $sender_id,
            'message' => $content
        );
        
        // Example API call (replace with your SMS provider's API)
        $url = "https://api.smsprovider.com/send";
        
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result !== FALSE) {
            error_log("SMS sent successfully to: $phone");
            return true;
        } else {
            error_log("SMS failed to send to: $phone");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("SMS sending failed to $phone: " . $e->getMessage());
        return false;
    }
}

echo "Message processing completed.\n";
?>