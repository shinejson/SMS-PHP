<?php
// Script to update config paths in all API files
$apiDir = __DIR__ . '/api/';

echo "Updating files in api/reports/...\n";

// Files in api/reports/ directory
$reportFiles = glob($apiDir . 'reports/*.php');
foreach ($reportFiles as $file) {
    $content = file_get_contents($file);
    // Replace require_once '../../config.php'; with correct path
    $newContent = preg_replace(
        '/require_once\s+[\'"]\.\.\/\.\.\/config\.php[\'"];/',
        "require_once dirname(__DIR__, 2) . '/config.php';",
        $content
    );
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✓ Updated: " . basename($file) . "\n";
    }
}

echo "\nUpdating files in api/ directory...\n";

// Files in api/ directory (get_classes.php, etc.)
$apiFiles = glob($apiDir . '*.php');
foreach ($apiFiles as $file) {
    if (basename($file) === 'fix_paths.php') continue;
    
    $content = file_get_contents($file);
    // Replace require_once '../config.php'; with correct path
    $newContent = preg_replace(
        '/require_once\s+[\'"]\.\.\/config\.php[\'"];/',
        "require_once dirname(__DIR__) . '/config.php';",
        $content
    );
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✓ Updated: " . basename($file) . "\n";
    }
}

echo "\n✅ All files updated successfully!\n";
?>