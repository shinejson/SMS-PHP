<?php
/**
 * Run this ONCE to generate secure encryption keys
 * Copy the output into your config.php file
 */

echo "<h2>🔑 Generated Encryption Keys</h2>";
echo "<div style='font-family: monospace; background: #f0f8ff; padding: 20px; border: 1px solid #4a90e2; border-radius: 8px;'>";

// Generate a random 32-character key
$encryptionKey = bin2hex(random_bytes(16)); // 32 hex characters
echo "<p><strong>ENCRYPTION_KEY:</strong><br>";
echo "<code style='background: #fff; padding: 5px; border: 1px solid #ddd;'>'{$encryptionKey}'</code></p>";

// Generate a random 16-character IV
$encryptionIV = bin2hex(random_bytes(8)); // 16 hex characters  
echo "<p><strong>ENCRYPTION_IV:</strong><br>";
echo "<code style='background: #fff; padding: 5px; border: 1px solid #ddd;'>'{$encryptionIV}'</code></p>";

echo "<h3>⚠️ IMPORTANT NOTES:</h3>";
echo "<ul>";
echo "<li><strong>Keep these keys SECRET</strong> - never share them!</li>";
echo "<li><strong>Use the SAME keys</strong> across your entire application</li>";
echo "<li><strong>If you change these keys</strong>, you'll need to re-encrypt all existing passwords in your database</li>";
echo "<li><strong>Backup these keys securely</strong> - losing them means losing access to encrypted data</li>";
echo "</ul>";

echo "<h3>📝 Copy this into your config.php:</h3>";
echo "<pre style='background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;'>";
echo "define('ENCRYPTION_KEY', '{$encryptionKey}');\n";
echo "define('ENCRYPTION_IV', '{$encryptionIV}');";
echo "</pre>";

echo "</div>";
?>