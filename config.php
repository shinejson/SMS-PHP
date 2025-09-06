<?php
// config.php - Enhanced Version with Better Error Handling

// ===============================
// 🗄️ Database Configuration
// ===============================
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'gebsco_db';

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// ===============================
// 🐛 Error Reporting Configuration
// ===============================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log'); // Create logs directory

// ===============================
// 🔑 Encryption / Decryption Helpers
// ===============================
// IMPORTANT: Change these to strong, unique values in production!
define('ENCRYPTION_KEY', 'cd1628eaedbc5201dea8a1ee32bd8a94');
define('ENCRYPTION_IV', '3b08aecc72123188'); // must be 16 chars for AES-128-CTR

/**
 * Encrypts sensitive text (like app password).
 * 
 * @param string $plainText The text to encrypt
 * @return string|false The encrypted text or false on failure
 */
function encryptPassword($plainText) {
    if (empty($plainText)) {
        return false;
    }
    
    try {
        $encrypted = openssl_encrypt(
            $plainText,
            'AES-128-CTR',
            ENCRYPTION_KEY,
            0,
            ENCRYPTION_IV
        );
        
        if ($encrypted === false) {
            error_log("Encryption failed: " . openssl_error_string());
            return false;
        }
        
        return $encrypted;
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return false;
    }
}

/**
 * Decrypts sensitive text.
 * 
 * @param string $encryptedText The encrypted text to decrypt
 * @return string|null The decrypted text or null on failure
 */
function decryptPassword($encryptedText) {
    if (empty($encryptedText)) {
        error_log("Decrypt: Empty encrypted text provided");
        return null;
    }
    
    try {
        $decrypted = openssl_decrypt(
            $encryptedText,
            'AES-128-CTR',
            ENCRYPTION_KEY,
            0,
            ENCRYPTION_IV
        );
        
        if ($decrypted === false) {
            error_log("Decryption failed: " . openssl_error_string());
            return null;
        }
        
        return $decrypted;
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return null;
    }
}

// ===============================
// 📧 Email Configuration Validation
// ===============================
/**
 * Validates email configuration and provides detailed debugging info
 * 
 * @param mysqli $conn Database connection
 * @return array Validation results
 */
function validateEmailConfig($conn) {
    $results = [
        'valid' => false,
        'errors' => [],
        'warnings' => [],
        'school_settings' => null
    ];
    
    try {
        // Fetch school settings
        $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        
        if (!$result) {
            $results['errors'][] = "Database query failed: " . $conn->error;
            return $results;
        }
        
        if ($result->num_rows === 0) {
            $results['errors'][] = "No school settings found in database";
            return $results;
        }
        
        $settings = $result->fetch_assoc();
        $results['school_settings'] = $settings;
        
        // Check email field
        if (empty($settings['email'])) {
            $results['errors'][] = "School email is empty or missing";
        } elseif (!filter_var($settings['email'], FILTER_VALIDATE_EMAIL)) {
            $results['errors'][] = "Invalid email format: " . $settings['email'];
        }
        
        // Check app password
        if (empty($settings['app_password'])) {
            $results['errors'][] = "App password is empty or missing";
        } else {
            // Try to decrypt
            $decryptedPassword = decryptPassword($settings['app_password']);
            if ($decryptedPassword === null) {
                $results['errors'][] = "Failed to decrypt app password";
            } elseif (empty($decryptedPassword)) {
                $results['errors'][] = "Decrypted app password is empty";
            } else {
                $results['warnings'][] = "App password decrypted successfully (length: " . strlen($decryptedPassword) . ")";
            }
        }
        
        // Check other required fields
        $requiredFields = ['school_name'];
        foreach ($requiredFields as $field) {
            if (empty($settings[$field])) {
                $results['warnings'][] = "Missing or empty field: $field";
            }
        }
        
        $results['valid'] = empty($results['errors']);
        
    } catch (Exception $e) {
        $results['errors'][] = "Exception during validation: " . $e->getMessage();
    }
    
    return $results;
}

// ===============================
// 🛠️ Debug Helper Functions
// ===============================
/**
 * Log detailed debugging information
 */
function logDebugInfo($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= " | Data: " . print_r($data, true);
    }
    
    error_log($logMessage);
}

/**
 * Test email configuration without sending actual email
 */
function testEmailConfiguration() {
    global $conn;
    
    $validation = validateEmailConfig($conn);
    
    echo "<h3>Email Configuration Test Results</h3>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>";
    
    if ($validation['valid']) {
        echo "<p style='color: green;'>✅ Configuration appears valid!</p>";
    } else {
        echo "<p style='color: red;'>❌ Configuration has issues:</p>";
    }
    
    if (!empty($validation['errors'])) {
        echo "<h4>Errors:</h4><ul>";
        foreach ($validation['errors'] as $error) {
            echo "<li style='color: red;'>$error</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($validation['warnings'])) {
        echo "<h4>Warnings:</h4><ul>";
        foreach ($validation['warnings'] as $warning) {
            echo "<li style='color: orange;'>$warning</li>";
        }
        echo "</ul>";
    }
    
    if ($validation['school_settings']) {
        echo "<h4>Current Settings:</h4>";
        echo "<p><strong>School Name:</strong> " . htmlspecialchars($validation['school_settings']['school_name'] ?? 'Not set') . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($validation['school_settings']['email'] ?? 'Not set') . "</p>";
        echo "<p><strong>App Password Status:</strong> " . (empty($validation['school_settings']['app_password']) ? 'Not set' : 'Set (encrypted)') . "</p>";
    }
    
    echo "</div>";
    
    return $validation;
}

// ===============================
// 📁 Directory Setup
// ===============================
// Create necessary directories
$directories = [
    __DIR__ . '/logs',
    __DIR__ . '/receipts'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: $dir");
        }
    }
}

// ===============================
// 🔧 Development Mode Check
// ===============================
// Add this at the end of your config file for debugging
if (isset($_GET['test_email_config']) && $_GET['test_email_config'] === '1') {
    testEmailConfiguration();
    exit;
}
?>