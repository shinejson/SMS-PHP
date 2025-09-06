<?php
/**
 * Email Configuration Test Backend
 * Handles AJAX requests from the test page safely
 */

// Prevent direct access issues and set proper headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

try {
    // Include required files
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions/sendEmail.php';
    
    // Determine the action
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'check_config':
        case 'check':
            handleConfigCheck();
            break;
            
        case 'send_test':
        case 'send':
            handleEmailTest();
            break;
            
        default:
            // If no action specified, show simple HTML interface
            if (empty($action)) {
                showSimpleInterface();
                exit;
            } else {
                sendJsonResponse(false, 'Invalid action specified', ['action' => $action]);
            }
    }
    
} catch (Exception $e) {
    error_log("Email test error: " . $e->getMessage());
    sendJsonResponse(false, 'Server error occurred: ' . $e->getMessage());
} catch (Throwable $e) {
    error_log("Email test fatal error: " . $e->getMessage());
    sendJsonResponse(false, 'Fatal error occurred');
}

/**
 * Handle configuration checking
 */
function handleConfigCheck() {
    global $conn;
    
    $result = [
        'success' => false,
        'message' => '',
        'errors' => [],
        'warnings' => [],
        'debug_info' => []
    ];
    
    try {
        // Check database connection
        if (!$conn || $conn->connect_error) {
            $result['errors'][] = 'Database connection failed: ' . ($conn->connect_error ?? 'Unknown error');
            sendJsonResponse(false, 'Database connection failed', $result);
            return;
        }
        
        $result['debug_info']['database'] = 'Connected successfully';
        
        // Check if school_settings table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'school_settings'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $result['errors'][] = 'school_settings table does not exist';
            sendJsonResponse(false, 'Configuration table missing', $result);
            return;
        }
        
        $result['debug_info']['table'] = 'school_settings table found';
        
        // Fetch school settings
        $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
        $settingsResult = $conn->query($sql);
        
        if (!$settingsResult) {
            $result['errors'][] = 'Failed to query school settings: ' . $conn->error;
            sendJsonResponse(false, 'Database query failed', $result);
            return;
        }
        
        if ($settingsResult->num_rows === 0) {
            $result['errors'][] = 'No school settings found - please add configuration data';
            sendJsonResponse(false, 'No settings found', $result);
            return;
        }
        
        $settings = $settingsResult->fetch_assoc();
        $result['debug_info']['settings_count'] = $settingsResult->num_rows;
        
        // Validate email
        $email = trim($settings['email'] ?? '');
        if (empty($email)) {
            $result['errors'][] = 'School email is not configured';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['errors'][] = 'Invalid email format: ' . $email;
        } else {
            $result['debug_info']['email'] = 'Valid email found: ' . $email;
        }
        
        // Check app password
        $appPassword = $settings['app_password'] ?? '';
        if (empty($appPassword)) {
            $result['errors'][] = 'App password is not configured';
        } else {
            // Test decryption
            if (function_exists('decryptPassword')) {
                $decrypted = decryptPassword($appPassword);
                if ($decrypted === null) {
                    $result['errors'][] = 'Failed to decrypt app password - check encryption configuration';
                } elseif (empty($decrypted)) {
                    $result['errors'][] = 'Decrypted app password is empty';
                } else {
                    $result['debug_info']['app_password'] = 'Successfully decrypted (length: ' . strlen($decrypted) . ')';
                }
            } else {
                $result['errors'][] = 'decryptPassword function not found';
            }
        }
        
        // Check other fields
        $schoolName = trim($settings['school_name'] ?? '');
        if (empty($schoolName)) {
            $result['warnings'][] = 'School name is not set';
        } else {
            $result['debug_info']['school_name'] = $schoolName;
        }
        
        // Check encryption configuration
        if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_IV')) {
            $result['errors'][] = 'Encryption constants not defined in config.php';
        } elseif (ENCRYPTION_KEY === 'your-very-strong-secret-key-32chars' || ENCRYPTION_IV === '1234567891011121') {
            $result['errors'][] = 'Encryption keys are still using default values - please update them';
        } else {
            $result['debug_info']['encryption'] = 'Encryption keys configured';
        }
        
        // Check PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $result['errors'][] = 'PHPMailer not found - check if Composer packages are installed';
        } else {
            $result['debug_info']['phpmailer'] = 'PHPMailer class available';
        }
        
        $result['success'] = empty($result['errors']);
        $result['message'] = $result['success'] ? 'Configuration appears valid' : 'Configuration has issues';
        
    } catch (Exception $e) {
        $result['errors'][] = 'Exception during configuration check: ' . $e->getMessage();
        error_log("Config check exception: " . $e->getMessage());
    }
    
    sendJsonResponse($result['success'], $result['message'], $result);
}

/**
 * Handle email testing
 */
function handleEmailTest() {
    $testEmail = trim($_POST['email'] ?? $_GET['email'] ?? '');
    $subject = trim($_POST['subject'] ?? $_GET['subject'] ?? 'Email Test - ' . date('Y-m-d H:i:s'));
    
    if (empty($testEmail)) {
        sendJsonResponse(false, 'Test email address is required');
        return;
    }
    
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid email address format: ' . $testEmail);
        return;
    }
    
    // Create HTML email body
    $htmlBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;'>
            <h2 style='color: #2c3e50; margin: 0;'>📧 Email Test Successful!</h2>
            <p style='color: #7f8c8d; margin: 10px 0 0 0;'>Your email configuration is working correctly</p>
        </div>
        
        <div style='background: white; padding: 20px; border: 1px solid #e9ecef; border-radius: 8px;'>
            <h3 style='color: #495057; margin-top: 0;'>Test Details:</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #e9ecef;'><strong>Sent At:</strong></td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #e9ecef;'>" . date('F j, Y g:i:s A') . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #e9ecef;'><strong>Test Email:</strong></td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #e9ecef;'>" . htmlspecialchars($testEmail) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0;'><strong>Subject:</strong></td>
                    <td style='padding: 8px 0;'>" . htmlspecialchars($subject) . "</td>
                </tr>
            </table>
            
            <div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px;'>
                <strong>✅ Success!</strong> If you receive this email, your school management system email configuration is working properly.
            </div>
            
            <p style='margin-top: 20px; font-size: 14px; color: #6c757d;'>
                This is an automated test email from your school management system.
            </p>
        </div>
    </div>
    ";
    
    // Use the enhanced sendEmail function
    if (function_exists('sendEmail')) {
        $result = sendEmail($testEmail, $subject, $htmlBody, null, true, true);
        sendJsonResponse($result['success'], $result['message'], $result);
    } else {
        sendJsonResponse(false, 'sendEmail function not found - check if sendEmail.php is properly included');
    }
}

/**
 * Send JSON response
 */
function sendJsonResponse($success, $message, $data = []) {
    // Clear any output buffer
    ob_clean();
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Add additional data
    if (is_array($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Show simple HTML interface for direct access
 */
function showSimpleInterface() {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Test - Direct Access</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
            .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .success { background: #d4edda; border-color: #c3e6cb; }
            .error { background: #f8d7da; border-color: #f5c6cb; }
            button, a { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
            button:hover, a:hover { background: #0056b3; }
            input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1>📧 Email Configuration Test</h1>
        
        <div class="section">
            <h2>Quick Tests</h2>
            <p><a href="?action=check">🔍 Check Configuration</a></p>
            <form method="get" style="display: inline;">
                <input type="hidden" name="action" value="send">
                <input type="email" name="email" placeholder="test@example.com" required>
                <button type="submit">📤 Send Test Email</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Common URLs</h2>
            <ul>
                <li><code>test_email_config.php?action=check</code> - Check configuration</li>
                <li><code>test_email_config.php?action=send&email=test@example.com</code> - Send test email</li>
            </ul>
        </div>
    </body>
    </html>
    <?php
}
?>