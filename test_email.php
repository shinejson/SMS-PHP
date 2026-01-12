<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get school settings
$school_settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();

if (!$school_settings) {
    die("School settings not found in database.");
}

echo "<h2>Gmail SMTP Test for XAMPP</h2>";

// Display current settings (without password)
echo "<h3>Current Settings:</h3>";
echo "<ul>";
echo "<li><strong>Email:</strong> " . htmlspecialchars($school_settings['email']) . "</li>";
echo "<li><strong>App Password Set:</strong> " . (!empty($school_settings['app_password']) ? 'Yes (' . strlen($school_settings['app_password']) . ' characters)' : 'No') . "</li>";
echo "<li><strong>School Name:</strong> " . htmlspecialchars($school_settings['school_name']) . "</li>";
echo "</ul>";

// Test email
$testEmail = "shineakakpo08@gmail.com"; // Send to yourself for testing
$subject = "Test Email from " . $school_settings['school_name'];
$content = "This is a test email to verify the email system is working correctly. Sent at: " . date('Y-m-d H:i:s');

$mail = new PHPMailer(true);

try {
    // Method 1: Try SSL first (Port 465)
    echo "<h3>🔄 Attempting Method 1: SSL (Port 465)</h3>";
    
    $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = trim($school_settings['email']);
    $mail->Password = trim($school_settings['app_password']);
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    // Add SSL options for local development
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Recipients
    $mail->setFrom($school_settings['email'], $school_settings['school_name']);
    $mail->addAddress($testEmail);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = "<h2>Test Email - Method 1 (SSL)</h2><p>$content</p>";
    $mail->AltBody = $content;
    
    $mail->send();
    echo '<div style="color: green; font-weight: bold;">✅ SUCCESS: Email sent using SSL (Port 465)!</div>';
    
} catch (Exception $e) {
    echo '<div style="color: red;">❌ Method 1 Failed: ' . $e->getMessage() . '</div>';
    
    // Method 2: Try TLS (Port 587)
    echo "<h3>🔄 Attempting Method 2: TLS (Port 587)</h3>";
    
    try {
        $mail->clearAllRecipients();
        $mail->clearAttachments();
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->addAddress($testEmail);
        $mail->Subject = $subject . " - Method 2";
        $mail->Body = "<h2>Test Email - Method 2 (TLS)</h2><p>$content</p>";
        
        $mail->send();
        echo '<div style="color: green; font-weight: bold;">✅ SUCCESS: Email sent using TLS (Port 587)!</div>';
        
    } catch (Exception $e2) {
        echo '<div style="color: red;">❌ Method 2 Failed: ' . $e2->getMessage() . '</div>';
        
        // Method 3: Try without encryption (Port 587)
        echo "<h3>🔄 Attempting Method 3: No Encryption (Port 587)</h3>";
        
        try {
            $mail->clearAllRecipients();
            $mail->clearAttachments();
            
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
            $mail->Port = 587;
            $mail->addAddress($testEmail);
            $mail->Subject = $subject . " - Method 3";
            $mail->Body = "<h2>Test Email - Method 3 (No Encryption)</h2><p>$content</p>";
            
            $mail->send();
            echo '<div style="color: green; font-weight: bold;">✅ SUCCESS: Email sent without encryption!</div>';
            
        } catch (Exception $e3) {
            echo '<div style="color: red;">❌ Method 3 Failed: ' . $e3->getMessage() . '</div>';
            
            echo "<h3>🚨 All Methods Failed - Diagnostic Information:</h3>";
            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">';
            echo '<p><strong>Most likely causes:</strong></p>';
            echo '<ol>';
            echo '<li><strong>App Password Issue:</strong> You need to generate an App Password from Google Account settings</li>';
            echo '<li><strong>2-Factor Authentication:</strong> Must be enabled to use App Passwords</li>';
            echo '<li><strong>Account Security:</strong> Google might be blocking the login attempt</li>';
            echo '</ol>';
            
            echo '<p><strong>Next Steps:</strong></p>';
            echo '<ol>';
            echo '<li>Check if 2FA is enabled on your Google account</li>';
            echo '<li>Generate a new App Password specifically for this application</li>';
            echo '<li>Update the database with the new App Password</li>';
            echo '<li>Try signing into Gmail manually to check for security alerts</li>';
            echo '</ol>';
            echo '</div>';
        }
    }
}

echo "<hr>";
echo "<h3>📋 System Information:</h3>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>OpenSSL Extension:</strong> " . (extension_loaded('openssl') ? '✅ Loaded' : '❌ Not Loaded') . "</li>";
echo "<li><strong>CURL Extension:</strong> " . (extension_loaded('curl') ? '✅ Loaded' : '❌ Not Loaded') . "</li>";
echo "<li><strong>PHPMailer Version:</strong> " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ Loaded' : '❌ Not Loaded') . "</li>";
echo "<li><strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "</li>";
echo "</ul>";

// Quick App Password Generator Link
echo "<hr>";
echo '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; border-left: 4px solid #2196f3;">';
echo '<h4>🔑 Need to Generate App Password?</h4>';
echo '<p>1. Go to: <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></p>';
echo '<p>2. Select "Mail" and "Other (Custom name)"</p>';
echo '<p>3. Type "School SMS" as the name</p>';
echo '<p>4. Copy the 16-character password and update your database:</p>';
echo '<code style="background: #f5f5f5; padding: 5px;">UPDATE school_setting SET app_password = \'your-app-password\' WHERE id = 1;</code>';
echo '</div>';
?>