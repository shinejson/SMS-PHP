<?php
// backup.php
require_once 'config.php';
require_once 'session.php';
require_once 'functions/activity_logger.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle backup requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_backup') {
            createDatabaseBackup($conn, $user_id);
        } elseif ($action === 'delete_backup' && isset($_POST['backup_file'])) {
            deleteBackupFile($_POST['backup_file'], $conn, $user_id);
        } elseif ($action === 'restore_backup' && isset($_POST['backup_file'])) {
            restoreDatabaseBackup($_POST['backup_file'], $conn, $user_id);
        } elseif ($action === 'download_backup' && isset($_POST['backup_file'])) {
            downloadBackupFile($_POST['backup_file']);
        }
    }
}

// Function to create database backup
function createDatabaseBackup($conn, $user_id) {
    // Backup directory
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Generate backup filename
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . 'backup_' . $timestamp . '.sql';
    $backup_zip = $backup_dir . 'backup_' . $timestamp . '.zip';
    
    // Get database configuration
    $db_host = 'localhost';
    $db_name = 'gebsco_db';
    $db_user = 'root';
    $db_pass = '';
    
    try {
        // Create SQL dump using mysqldump
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_file}";
        system($command, $output);
        
        if ($output === 0 && file_exists($backup_file)) {
            // Create zip archive
            $zip = new ZipArchive();
            if ($zip->open($backup_zip, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backup_file, 'database_backup.sql');
                $zip->close();
                
                // Remove the SQL file
                unlink($backup_file);
                
                // Log backup information in database
                $file_size = filesize($backup_zip);
                $file_size_mb = round($file_size / (1024 * 1024), 2);
                
                // FIXED: Store basename in variables
                $filename = basename($backup_zip);
                $filepath = $backup_zip;
                
                $sql = "INSERT INTO backup_logs (filename, file_path, file_size, backup_type, created_by) 
                        VALUES (?, ?, ?, 'manual', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssdi', $filename, $filepath, $file_size_mb, $user_id);
                $stmt->execute();
                $backup_id = $stmt->insert_id;
                $stmt->close();
                
                // Log activity with correct parameters
                logActivity(
                    $conn,
                    "Database Backup Created",
                    "Backup file: $filename ($file_size_mb MB)",
                    "create",
                    "fas fa-database",
                    $user_id,
                    $backup_id
                );
                
                $_SESSION['success'] = "Database backup created successfully! File: $filename ($file_size_mb MB)";
            } else {
                throw new Exception("Failed to create zip archive");
            }
        } else {
            throw new Exception("Failed to create database dump");
        }
    } catch (Exception $e) {
        // Fallback: Create simple SQL backup using PHP
        createPhpBackup($conn, $backup_zip, $user_id);
    }
}

// Fallback PHP-based backup function
function createPhpBackup($conn, $backup_file, $user_id) {
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $sql_script = "-- Database Backup Generated on " . date('Y-m-d H:i:s') . "\n";
    $sql_script .= "-- Backup System: PHP Fallback Method\n\n";
    
    foreach ($tables as $table) {
        // Add table structure
        $sql_script .= "--\n-- Table structure for table `$table`\n--\n";
        $sql_script .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $create_table = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $create_table->fetch_row();
        $sql_script .= $row[1] . ";\n\n";
        
        // Add table data
        $sql_script .= "--\n-- Dumping data for table `$table`\n--\n";
        
        $data_result = $conn->query("SELECT * FROM `$table`");
        if ($data_result->num_rows > 0) {
            $sql_script .= "INSERT INTO `$table` VALUES ";
            $rows = array();
            
            while ($data_row = $data_result->fetch_assoc()) {
                $values = array();
                foreach ($data_row as $value) {
                    $values[] = is_null($value) ? "NULL" : "'" . $conn->real_escape_string($value) . "'";
                }
                $rows[] = "(" . implode(", ", $values) . ")";
            }
            
            $sql_script .= implode(",\n", $rows) . ";\n\n";
        }
    }
    
    // Save to file
    if (file_put_contents($backup_file, $sql_script)) {
        $file_size = filesize($backup_file);
        $file_size_mb = round($file_size / (1024 * 1024), 2);
        
        // FIXED: Store basename in variable first
        $filename = basename($backup_file);
        $filepath = $backup_file;
        
        // Log backup information
        $sql = "INSERT INTO backup_logs (filename, file_path, file_size, backup_type, created_by) 
                VALUES (?, ?, ?, 'manual_php', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdi', $filename, $filepath, $file_size_mb, $user_id);
        $stmt->execute();
        $backup_id = $stmt->insert_id;
        $stmt->close();
        
        // Log activity with correct parameters
        logActivity(
            $conn,
            "Database Backup Created (PHP Method)",
            "Backup file: $filename ($file_size_mb MB)",
            "create",
            "fas fa-database",
            $user_id,
            $backup_id
        );
        
        $_SESSION['success'] = "Database backup created successfully (PHP method)! File: $filename ($file_size_mb MB)";
    } else {
        $_SESSION['error'] = "Failed to create backup file. Please check directory permissions.";
    }
}

// Function to delete backup file
function deleteBackupFile($filename, $conn, $user_id) {
    $backup_dir = 'backups/';
    $file_path = $backup_dir . $filename;
    
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            // Update log in database
            $sql = "UPDATE backup_logs SET deleted_at = NOW(), deleted_by = ? WHERE filename = ? AND deleted_at IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $user_id, $filename);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
          logActivity(
    $conn,
    "Backup File Deleted",
    "File: $filename",
    "delete",
    "fas fa-trash",
    $user_id,
    null
);
            
            $_SESSION['success'] = "Backup file deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete backup file.";
        }
    } else {
        $_SESSION['error'] = "Backup file not found.";
    }
}

// Function to download backup file
function downloadBackupFile($filename) {
    $backup_dir = 'backups/';
    $file_path = $backup_dir . $filename;
    
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $_SESSION['error'] = "Backup file not found.";
        header("Location: backup.php");
        exit();
    }
}

// Function to restore database (with caution)
function restoreDatabaseBackup($filename, $conn, $user_id) {
    $backup_dir = 'backups/';
    $file_path = $backup_dir . $filename;
    
    if (!file_exists($file_path)) {
        $_SESSION['error'] = "Backup file not found.";
        return;
    }
    
    // Check if file is SQL or ZIP
    $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
    $sql_file = $file_path;
    
    if ($file_ext === 'zip') {
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($file_path) === TRUE) {
            $zip->extractTo($backup_dir . 'temp/');
            $sql_file = $backup_dir . 'temp/database_backup.sql';
            $zip->close();
        } else {
            $_SESSION['error'] = "Failed to extract backup file.";
            return;
        }
    }
    
    // Read SQL file
    $sql_commands = file_get_contents($sql_file);
    
    if ($sql_commands) {
        // Split SQL commands
        $commands = array_filter(array_map('trim', explode(';', $sql_commands)));
        
        // Disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        
        $success = true;
        $error_message = '';
        
        foreach ($commands as $command) {
            if (!empty($command) && substr($command, 0, 2) != '--') {
                if (!$conn->query($command)) {
                    $success = false;
                    $error_message = $conn->error;
                    break;
                }
            }
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        
        // Clean up temp files
        if ($file_ext === 'zip' && file_exists($sql_file)) {
            unlink($sql_file);
            rmdir($backup_dir . 'temp/');
        }
        
        if ($success) {
            // Log restore activity
            logActivity(
    $conn,
    "Database Restored from Backup",
    "Restored from: $filename",
    "update",
    "fas fa-undo",
    $user_id,
    null
);
            
            $_SESSION['success'] = "Database restored successfully from backup: " . $filename;
        } else {
            $_SESSION['error'] = "Database restore failed: " . $error_message;
        }
    } else {
        $_SESSION['error'] = "Failed to read backup file.";
    }
}

// Get existing backup files
$backup_files = [];
$backup_dir = 'backups/';

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (pathinfo($file, PATHINFO_EXTENSION) === 'sql' || pathinfo($file, PATHINFO_EXTENSION) === 'zip')) {
            $file_path = $backup_dir . $file;
            $backup_files[] = [
                'filename' => $file,
                'filepath' => $file_path,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path)
            ];
        }
    }
    
    // Sort by modification time (newest first)
    usort($backup_files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Get backup logs from database
$backup_logs = [];
$sql = "SELECT bl.*, u.full_name as created_by_name 
        FROM backup_logs bl 
        LEFT JOIN users u ON bl.created_by = u.id 
        ORDER BY bl.created_at DESC 
        LIMIT 50";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $backup_logs[] = $row;
}

// Get backup statistics
$stats_sql = "SELECT 
              COUNT(*) as total_backups,
              SUM(file_size) as total_size,
              COUNT(CASE WHEN backup_type = 'manual' THEN 1 END) as manual_backups,
              COUNT(CASE WHEN backup_type = 'auto' THEN 1 END) as auto_backups,
              COUNT(CASE WHEN backup_type = 'manual_php' THEN 1 END) as php_backups
              FROM backup_logs 
              WHERE deleted_at IS NULL";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Database Backup Management</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <style>
        .backup-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .backup-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-backup {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white;
        }
        
        .btn-backup:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .file-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-icon.small {
            padding: 6px 8px;
            font-size: 0.8rem;
        }
        
        .file-size {
            color: #666;
            font-size: 0.9rem;
        }
        
        .backup-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .type-manual { background: #dbeafe; color: #1e40af; }
        .type-auto { background: #d1fae5; color: #065f46; }
        .type-php { background: #fef3c7; color: #92400e; }
        
        .auto-backup-settings {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .setting-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .setting-label {
            min-width: 150px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .backup-actions {
                flex-direction: column;
            }
            
            .btn-backup {
                width: 100%;
                justify-content: center;
            }
            
            .setting-group {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'topnav.php'; ?>
            
            <div class="page-content">
                <!-- Flash Messages -->
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="page-header">
                    <h1>
                        <i class="fas fa-database"></i> Database Backup Management
                    </h1>
                </div>

                <!-- Statistics Cards -->
                <div class="backup-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_backups'] ?></div>
                        <div class="stat-label">Total Backups</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number">
                            <?= number_format($stats['total_size'] ?? 0, 1) ?> MB
                        </div>
                        <div class="stat-label">Total Size</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['manual_backups'] ?></div>
                        <div class="stat-label">Manual Backups</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['auto_backups'] ?></div>
                        <div class="stat-label">Auto Backups</div>
                    </div>
                </div>

                <!-- Backup Actions -->
                <div class="backup-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" class="btn-backup btn-primary" onclick="return confirm('Create a new database backup?')">
                            <i class="fas fa-plus"></i> Create Backup Now
                        </button>
                    </form>
                    
                    <button class="btn-backup btn-success" onclick="scheduleAutoBackup()">
                        <i class="fas fa-clock"></i> Schedule Auto Backup
                    </button>
                    
                    <button class="btn-backup btn-warning" onclick="showCleanupModal()">
                        <i class="fas fa-broom"></i> Cleanup Old Backups
                    </button>
                </div>

                <!-- Auto Backup Settings -->
                <div class="auto-backup-settings">
                    <h3><i class="fas fa-cog"></i> Auto Backup Settings</h3>
                    <form method="POST" action="backup_cron.php">
                        <div class="setting-group">
                            <label class="setting-label">Auto Backup:</label>
                            <select name="auto_backup_enabled">
                                <option value="1">Enabled</option>
                                <option value="0" selected>Disabled</option>
                            </select>
                        </div>
                        
                        <div class="setting-group">
                            <label class="setting-label">Backup Frequency:</label>
                            <select name="backup_frequency">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        
                        <div class="setting-group">
                            <label class="setting-label">Keep Backups For:</label>
                            <select name="retention_days">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                                <option value="365">1 year</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-backup btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>

                <!-- Backup Files List -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Backup Files</h3>
                        <span class="pagination-info">
                            Showing <?= count($backup_files) ?> backup files
                        </span>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($backup_files)): ?>
                                    <?php foreach ($backup_files as $file): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($file['filename']) ?></strong>
                                            </td>
                                            <td class="file-size">
                                                <?= round($file['size'] / (1024 * 1024), 2) ?> MB
                                            </td>
                                            <td>
                                                <?= date('M j, Y H:i', $file['modified']) ?>
                                            </td>
                                            <td>
                                                <span class="backup-type type-<?= pathinfo($file['filename'], PATHINFO_EXTENSION) === 'zip' ? 'manual' : 'php' ?>">
                                                    <?= pathinfo($file['filename'], PATHINFO_EXTENSION) === 'zip' ? 'ZIP' : 'SQL' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="file-actions">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="download_backup">
                                                        <input type="hidden" name="backup_file" value="<?= $file['filename'] ?>">
                                                        <button type="submit" class="btn-icon small primary" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="restore_backup">
                                                        <input type="hidden" name="backup_file" value="<?= $file['filename'] ?>">
                                                        <button type="submit" class="btn-icon small success" 
                                                                title="Restore" 
                                                                onclick="return confirm('WARNING: This will overwrite your current database. Are you sure?')">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_backup">
                                                        <input type="hidden" name="backup_file" value="<?= $file['filename'] ?>">
                                                        <button type="submit" class="btn-icon small danger" 
                                                                title="Delete"
                                                                onclick="return confirm('Are you sure you want to delete this backup?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            No backup files found. Create your first backup using the button above.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Backup Logs -->
                <div class="table-container" style="margin-top: 2rem;">
                    <div class="table-header">
                        <h3>Backup Logs</h3>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Created By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($backup_logs)): ?>
                                    <?php foreach ($backup_logs as $log): ?>
                                        <tr>
                                            <td><?= date('M j, Y H:i', strtotime($log['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($log['filename']) ?></td>
                                            <td>
                                                <span class="backup-type type-<?= $log['backup_type'] ?>">
                                                    <?= ucfirst($log['backup_type']) ?>
                                                </span>
                                            </td>
                                            <td class="file-size"><?= $log['file_size'] ?> MB</td>
                                            <td><?= htmlspecialchars($log['created_by_name']) ?></td>
                                            <td>
                                                <span class="status-badge <?= $log['deleted_at'] ? 'status-closed' : 'status-active' ?>">
                                                    <?= $log['deleted_at'] ? 'Deleted' : 'Active' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No backup logs found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
     <script src="js/dashboard.js"></script>
     <script src="js/darkmode.js"></script>

    <!-- JavaScript for additional functionality -->
    <script>
        function scheduleAutoBackup() {
            alert('Auto backup scheduling would be configured here. This typically requires server cron job setup.');
        }
        
        function showCleanupModal() {
            if (confirm('This will delete backup files older than 30 days. Continue?')) {
                window.location.href = 'backup_cleanup.php';
            }
        }
        
        // Auto refresh backup list every 30 seconds
        setInterval(function() {
            // You can implement AJAX refresh here if needed
        }, 30000);
    </script>
</body>
</html>