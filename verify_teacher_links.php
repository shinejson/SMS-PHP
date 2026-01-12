<?php
require_once 'config.php';
require_once 'session.php';
require_once 'access_control.php';
// Only accessible by admin
if ($_SESSION['role'] !== 'teacher') {
    header('Location: access_denied.php');
    exit();
}

// Get all users with teacher role
$sql = "SELECT u.id as user_id, u.full_name, u.email, u.role, 
               t.id as teacher_id, t.teacher_id as teacher_code, t.status as teacher_status
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        WHERE u.role = 'teacher'
        ORDER BY u.full_name";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Link Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-ok {
            color: #4CAF50;
            font-weight: bold;
        }
        .status-error {
            color: #f44336;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #45a049;
        }
        .fix-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .fix-btn:hover {
            background: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teacher Link Diagnostic Tool</h1>
        <?php
// Show success/error messages
if (isset($_GET['success'])) {
    echo '<div style="background:#d4edda;color:#155724;padding:12px;border-radius:4px;margin:15px 0;">
            <strong>Success:</strong> ' . htmlspecialchars(urldecode($_GET['success'])) . '
          </div>';
}
if (isset($_GET['error'])) {
    echo '<div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:4px;margin:15px 0;">
            <strong>Error:</strong> ' . htmlspecialchars(urldecode($_GET['error'])) . '
          </div>';
}
?>
        <p>This tool checks if all teacher users have corresponding records in the teachers table.</p>
        
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Teacher ID (DB)</th>
                    <th>Teacher Code</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['user_id'] ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= $row['teacher_id'] ?? '<span class="status-error">NULL</span>' ?></td>
                        <td><?= htmlspecialchars($row['teacher_code'] ?? 'N/A') ?></td>
                        <td>
                            <?php if ($row['teacher_id']): ?>
                                <span class="status-ok">✓ Linked</span>
                            <?php else: ?>
                                <span class="status-error">✗ NOT LINKED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$row['teacher_id']): ?>
                                <form method="POST" action="fix_teacher_link.php" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                    <input type="hidden" name="full_name" value="<?= htmlspecialchars($row['full_name']) ?>">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($row['email']) ?>">
                                    <button type="submit" class="fix-btn">Create Teacher Record</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #4CAF50;">✓ OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <a href="admin_dashboard.php" class="btn">← Back to Dashboard</a>
    </div>
</body>
</html>