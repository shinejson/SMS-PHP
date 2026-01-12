<?php
require_once 'config.php';
require_once 'functions.php';

$school_settings = getSchoolSettings($conn);
$school_name = $school_settings['school_name'] ?? 'School Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install App - <?php echo htmlspecialchars($school_name); ?></title>
    <style>
        .install-steps {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .step {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .step img {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="install-steps">
        <h1>Install <?php echo htmlspecialchars($school_name); ?> App</h1>
        
        <div class="step">
            <h3>Step 1: Look for the Install Button</h3>
            <p>On mobile devices, look for the "Add to Home Screen" or "Install" option in your browser's menu.</p>
        </div>
        
        <div class="step">
            <h3>Step 2: On Desktop (Chrome/Edge)</h3>
            <p>Look for the install icon (📱) in the address bar and click "Install".</p>
        </div>
        
        <div class="step">
            <h3>Benefits of Installing</h3>
            <ul>
                <li>Faster loading</li>
                <li>Works offline</li>
                <li>App-like experience</li>
                <li>Direct access from home screen</li>
            </ul>
        </div>
        
        <button onclick="installPWA()" style="padding: 15px 30px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Install App Now
        </button>
    </div>
    
    <script src="js/pwa.js"></script>
</body>
</html>