    <?php
    require_once 'functions.php';
    $favicon_path = getSchoolFavicon($conn);
    $school_settings = getSchoolSettings($conn);
    ?>
    
      <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($school_settings['school_short_name'] ?? 'SchoolMS'); ?>">
    
    <!-- Icons for Apple devices -->
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($school_settings['logo'] ?? 'img/default-logo.png'); ?>">
    
    <!-- Dynamic Favicon with multiple sizes -->
    <link rel="icon" type="img/x-icon" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="icon" type="img/png" sizes="32x32" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="icon" type="img/png" sizes="16x16" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($favicon_path); ?>">
    
    <!-- PWA Manifest (optional) -->
    <?php if (!empty($school_settings['school_short_name'])): ?>
    <link rel="manifest" href="/gebsco/manifest.php">
    <?php endif; ?>