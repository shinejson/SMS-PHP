<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // DB connection

/**
 * Enhanced email sending function with detailed error logging
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML or plain text)
 * @param string|null $attachmentPath File path to attach (optional)
 * @param bool $isHtml Whether the body is HTML (default: false)
 * @param bool $debug Enable debug mode (default: false)
 * @return array Result array with success status and detailed info
 */
function sendEmail($to, $subject, $body, $attachmentPath = null, $isHtml = false, $debug = false) {
    global $conn;
    
    $result = [
        'success' => false,
        'message' => '',
        'debug_info' => []
    ];
    
    // Input validation
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result['message'] = "Invalid recipient email address: $to";
        error_log("Email Error: " . $result['message']);
        return $result;
    }
    
    if (empty($subject)) {
        $result['message'] = "Email subject cannot be empty";
        error_log("Email Error: " . $result['message']);
        return $result;
    }
    
    if (empty($body)) {
        $result['message'] = "Email body cannot be empty";
        error_log("Email Error: " . $result['message']);
        return $result;
    }
    
    try {
        // === Fetch School Settings ===
        $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
        $settingsResult = $conn->query($sql);
        
        if (!$settingsResult) {
            $result['message'] = "Database query failed: " . $conn->error;
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        if ($settingsResult->num_rows === 0) {
            $result['message'] = "No school settings found in database";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        $settings = $settingsResult->fetch_assoc();
        $result['debug_info']['settings_found'] = true;
        
        // Validate required settings
        $schoolEmail = trim($settings['email'] ?? '');
        $schoolName = trim($settings['school_name'] ?? 'School System');
        $encryptedPassword = $settings['app_password'] ?? '';
        
        if (empty($schoolEmail)) {
            $result['message'] = "School email not configured in settings";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        if (!filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = "Invalid school email format: $schoolEmail";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        if (empty($encryptedPassword)) {
            $result['message'] = "App password not configured in settings";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        // Decrypt password
        $appPassword = decryptPassword($encryptedPassword);
        if ($appPassword === null) {
            $result['message'] = "Failed to decrypt app password - check encryption configuration";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        if (empty($appPassword)) {
            $result['message'] = "Decrypted app password is empty";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        $result['debug_info']['password_decrypted'] = true;
        $result['debug_info']['school_email'] = $schoolEmail;
        $result['debug_info']['school_name'] = $schoolName;
        
        // Check attachment if provided
        if ($attachmentPath && !file_exists($attachmentPath)) {
            $result['message'] = "Attachment file not found: $attachmentPath";
            error_log("Email Error: " . $result['message']);
            return $result;
        }
        
        // === Initialize PHPMailer ===
        $mail = new PHPMailer(true);
        
        // Enable debug mode if requested
        if ($debug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) use (&$result) {
                $result['debug_info']['smtp_debug'][] = "Level $level: $str";
                error_log("SMTP Debug Level $level: $str");
            };
        }
        
        // === Server Configuration ===
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $schoolEmail;
        $mail->Password = $appPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Timeout settings
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;
        
        // === SSL/TLS Configuration for Local Development ===
        // Fix SSL certificate verification issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'cafile' => false,
                'capath' => false,
            ),
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'cafile' => false,
                'capath' => false,
            )
        );
        
        // Additional connection settings for XAMPP/local development
        $mail->SMTPAutoTLS = false; // Disable automatic TLS
        $mail->SMTPSecure = false;  // Disable secure connection initially
        $mail->Port = 587;          // Use standard port
        
        // Then enable STARTTLS manually
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        
        // === Recipients ===
        $mail->setFrom($schoolEmail, $schoolName);
        $mail->addAddress($to);
        $mail->addReplyTo($schoolEmail, $schoolName);
        
        // === Content ===
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Set plain text version for HTML emails
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        // === Attachment ===
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
            $result['debug_info']['attachment_added'] = basename($attachmentPath);
        }
        
        // === Send Email ===
        $sendResult = $mail->send();
        
        if ($sendResult) {
            $result['success'] = true;
            $result['message'] = "Email sent successfully to $to";
            $result['debug_info']['sent_at'] = date('Y-m-d H:i:s');
            
            // Log successful send
            error_log("Email Success: Sent to $to | Subject: $subject");
        } else {
            $result['message'] = "Email sending failed - no specific error returned";
            error_log("Email Error: " . $result['message']);
        }
        
    } catch (Exception $e) {
        $result['message'] = "Email sending failed: " . $e->getMessage();
        $result['debug_info']['exception'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        
        error_log("Email Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        
        // Additional Gmail-specific error handling
        if (strpos($e->getMessage(), 'Username and Password not accepted') !== false) {
            $result['message'] .= " | Check if 2-factor authentication is enabled and you're using an App Password, not your regular password.";
        } elseif (strpos($e->getMessage(), 'Connection timed out') !== false) {
            $result['message'] .= " | Connection timeout - check your internet connection and firewall settings.";
        } elseif (strpos($e->getMessage(), 'Could not authenticate') !== false) {
            $result['message'] .= " | Authentication failed - verify your email and app password are correct.";
        }
    } catch (Throwable $e) {
        $result['message'] = "Unexpected error: " . $e->getMessage();
        error_log("Email Throwable: " . $e->getMessage());
    }
    
    // Clean up
    if (isset($mail)) {
        $mail->smtpClose();
    }
    
    return $result;
}

/**
 * Backward compatibility wrapper - returns boolean like original function
 * But logs detailed error information
 */
function sendEmailLegacy($to, $subject, $body, $attachmentPath = null, $isHtml = false) {
    $result = sendEmail($to, $subject, $body, $attachmentPath, $isHtml, false);
    
    if (!$result['success']) {
        error_log("Email sending failed: " . $result['message']);
    }
    
    return $result['success'];
}

/**
 * Test email sending with detailed debugging
 */
function testEmailSending($testEmail = null) {
    global $conn;
    
    // Use a test email or fetch from settings
    if (!$testEmail) {
        $sql = "SELECT email FROM school_settings ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $testEmail = $row['email'];
        }
    }
    
    if (!$testEmail) {
        echo "<p style='color: red;'>No test email address available</p>";
        return false;
    }
    
    echo "<h3>Testing Email Send to: $testEmail</h3>";
    
    $testResult = sendEmail(
        $testEmail,
        'Test Email - ' . date('Y-m-d H:i:s'),
        '<h2>Test Email</h2><p>This is a test email sent at ' . date('Y-m-d H:i:s') . '</p><p>If you receive this, your email configuration is working!</p>',
        null,
        true,
        true  // Enable debug mode
    );
    
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin: 10px 0;'>";
    
    if ($testResult['success']) {
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Email failed to send</p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($testResult['message']) . "</p>";
    }
    
    if (!empty($testResult['debug_info'])) {
        echo "<h4>Debug Information:</h4>";
        echo "<pre>" . htmlspecialchars(print_r($testResult['debug_info'], true)) . "</pre>";
    }
    
    echo "</div>";
    
    return $testResult['success'];
}

// Allow testing via URL parameter
if (isset($_GET['test_email']) && $_GET['test_email'] === '1') {
    $testEmail = $_GET['email'] ?? null;
    testEmailSending($testEmail);
    exit;
}
?>