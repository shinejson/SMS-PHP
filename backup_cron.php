<?php
// backup_cron.php - Run this via cron job for automatic backups
require_once 'config.php';

function autoBackup() {
    global $conn;
    
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . 'auto_backup_' . $timestamp . '.sql';
    $backup_zip = $backup_dir . 'auto_backup_' . $timestamp . '.zip';
    
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    
    try {
        // Try mysqldump first
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_file}";
        system($command, $output);
        
        if ($output === 0 && file_exists($backup_file)) {
            $zip = new ZipArchive();
            if ($zip->open($backup_zip, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backup_file, 'database_backup.sql');
                $zip->close();
                unlink($backup_file);
                
                $file_size = filesize($backup_zip);
                $file_size_mb = round($file_size / (1024 * 1024), 2);
                
                // Log the backup
                $sql = "INSERT INTO backup_logs (filename, file_path, file_size, backup_type, created_by) 
                        VALUES (?, ?, ?, 'auto', 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssd', basename($backup_zip), $backup_zip, $file_size_mb);
                $stmt->execute();
                $stmt->close();
                
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("Auto backup failed: " . $e->getMessage());
    }
    
    return false;
}

// Run auto backup
if (autoBackup()) {
    echo "Auto backup completed successfully.\n";
} else {
    echo "Auto backup failed.\n";
}

// Cleanup old backups (older than 30 days)
$backup_dir = 'backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    $now = time();
    $days_to_keep = 30;
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $file_path = $backup_dir . $file;
            if (is_file($file_path)) {
                if ($now - filemtime($file_path) >= $days_to_keep * 24 * 60 * 60) {
                    unlink($file_path);
                    echo "Deleted old backup: $file\n";
                }
            }
        }
    }
}
?>