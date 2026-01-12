<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - GEBSCO</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="img/logo.png" alt="GEBSCO Logo" width="100">
            <h1>Access Denied</h1>
            <p>You don't have permission to access this page</p>
        </div>
        
        <div class="error-message" style="text-align: center;">
            <i class="fas fa-ban" style="font-size: 48px; margin-bottom: 20px; color: #dc3545;"></i>
            <h3>Access Restricted</h3>
            <p>Your account (<strong><?= htmlspecialchars($_SESSION['role'] ?? 'Unknown') ?></strong>) does not have permission to access this page.</p>
            <p>Please contact an administrator if you believe this is an error.</p>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="login-btn" style="display: inline-block; text-decoration: none;">
                Go to Dashboard
            </a>
        </div>
    </div>
</body>
</html>