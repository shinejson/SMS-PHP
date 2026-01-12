<?php
// backup_cleanup.php
require_once 'config.php';
require_once 'session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}

$backup_dir = 'backups/';
$deleted_count = 0;

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    $now = time();
    $days_to_keep = 30; // Keep backups for 30 days
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $file_path = $backup_dir . $file;
            if (is_file($file_path)) {
                if ($now - filemtime($file_path) >= $days_to_keep * 24 * 60 * 60) {
                    if (unlink($file_path)) {
                        $deleted_count++;
                    }
                }
            }
        }
    }
}

$_SESSION['success'] = "Cleanup completed! Deleted $deleted_count old backup files.";
header("Location: backup.php");
exit();
?>