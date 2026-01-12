<?php
session_start();
// Include database connection and system configuration
require_once 'config.php';
require_once 'system-config.php';

// After successful login
$_SESSION['LAST_ACTIVITY'] = time();
// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['redirect_url'] ?? 'index.php';
    unset($_SESSION['redirect_url']);
    header("Location: $redirect");
    exit();
}

// Get school settings
$school_settings = getSchoolSettings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSchoolSetting('school_short_name')); ?> - Login</title>
    <link rel="icon" href="<?php echo htmlspecialchars(getSchoolSetting('favicon')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .logo-fallback {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            color: white;
            font-size: 2.5rem;
        }
        
        .dynamic-header {
            transition: all 0.3s ease;
        }
        
        .dynamic-header:hover h1 {
            color: #224abe;
        }
        
        .school-info {
            text-align: center;
            margin-top: 0.5rem;
        }
        
        .school-name {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Dynamic Login Header -->
        <div class="dynamic-header">
            <?php include 'login-header.php'; ?>
            
            <!-- Optional: Display full school name if different from short name -->
            <?php if (!empty($school_settings['school_name']) && $school_settings['school_name'] !== $school_settings['school_short_name']): ?>
                <div class="school-info">
                    <p class="school-name"><?php echo htmlspecialchars($school_settings['school_name']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <form id="login-form" class="login-form" action="auth.php" method="POST" novalidate>
            <?php
            // Capture and clear login message and error
            $loginMessage = $_SESSION['login_message'] ?? '';
            $loginError = $_SESSION['login_error'] ?? '';
            unset($_SESSION['login_message'], $_SESSION['login_error']);
            ?>
            
            <!-- Informational messages (blue) -->
            <?php if (!empty($loginMessage)) : ?>
                <div class="info-message"><?= htmlspecialchars($loginMessage) ?></div>
            <?php endif; ?>
            
            <!-- Error messages (red) -->
            <?php if (!empty($loginError)) : ?>
                <div class="error-message"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    placeholder="Enter your username"
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Enter your password"
                    autocomplete="current-password"
                >
            </div>

            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="login-btn">Login</button>

            <div class="security-notice">
                <p>For security reasons, please log out when you're done.</p>
                <p>All activities are monitored.</p>
                
                <!-- Optional: Display school contact info -->
                <?php if (!empty($school_settings['email']) || !empty($school_settings['phone'])): ?>
                    <div class="contact-info">
                        <p><small>
                            Contact: 
                            <?php if (!empty($school_settings['email'])): ?>
                                <?php echo htmlspecialchars($school_settings['email']); ?>
                            <?php endif; ?>
                            <?php if (!empty($school_settings['email']) && !empty($school_settings['phone'])): ?> | <?php endif; ?>
                            <?php if (!empty($school_settings['phone'])): ?>
                                Tel: <?php echo htmlspecialchars($school_settings['phone']); ?>
                            <?php endif; ?>
                        </small></p>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
    console.log('🔍 Login Page Debug Info:');
    console.log('URL:', window.location.href);
    console.log('Service Worker:', navigator.serviceWorker?.controller);
    console.log('Display Mode:', window.matchMedia('(display-mode: standalone)').matches);
    console.log('Referrer:', document.referrer);

    // Check if we're in PWA mode
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('📱 Running as installed PWA');
        document.body.classList.add('pwa-mode');
    }

    // Enhanced logo error handling
    document.addEventListener('DOMContentLoaded', function() {
        const logo = document.querySelector('.login-header img');
        const fallback = document.querySelector('.logo-fallback');
        
        if (logo) {
            logo.addEventListener('error', function() {
                this.style.display = 'none';
                if (fallback) {
                    fallback.style.display = 'flex';
                }
            });
            
            // Check if logo loaded successfully
            if (logo.complete && logo.naturalHeight === 0) {
                logo.style.display = 'none';
                if (fallback) {
                    fallback.style.display = 'flex';
                }
            }
        }
    });
    </script>
</body>
</html>