 <?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['redirect_url'] ?? 'index.php';
    unset($_SESSION['redirect_url']);
    header("Location: $redirect");
    exit();
}
 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GEBSCO Admin Login</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="img/logo.png" alt="GEBSCO Logo" width="100">
            <h1>GEBSCO Dashboard</h1>
            <p>Administrator Portal</p>
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
                    required 
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
                    required 
                >
            </div>

             <?php


            // Capture and clear login message and error
            $loginMessage = $_SESSION['login_message'] ?? '';
            $loginError   = $_SESSION['login_error'] ?? '';
            unset($_SESSION['login_message'], $_SESSION['login_error']);
            ?>

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
            </div>
        </form>
    </div>
</body>
</html>
