<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .error-container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 400px;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #e74a3b;
            margin-bottom: 20px;
        }
        
        .error-title {
            color: #e74a3b;
            margin-bottom: 10px;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4e73df;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2e59d9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">You don't have permission to access this page.</p>
        <a href="dashboard.php" class="btn">Go to Dashboard</a>
        <a href="login.php" class="btn" style="background: #6c757d; margin-left: 10px;">Login Again</a>
    </div>
</body>
</html>