<?php
// functions.php

function getSchoolFavicon($conn) {
    // Check session cache first
    if (isset($_SESSION['school_favicon'])) {
        return $_SESSION['school_favicon'];
    }
    
    $sql = "SELECT favicon FROM school_settings ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $favicon = $data['favicon'] ?? 'img/default-favicon.ico';
        
        // Cache in session
        $_SESSION['school_favicon'] = $favicon;
        return $favicon;
    }
    
    return 'img/default-favicon.ico';
}

function getSchoolSettings($conn) {
    // Check session cache first
    if (isset($_SESSION['school_settings'])) {
        return $_SESSION['school_settings'];
    }
    
    $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        
        // Cache in session
        $_SESSION['school_settings'] = $settings;
        return $settings;
    }
    
    // Return default structure if no settings found
    return [
        'school_short_name' => 'Dashboard',
        'school_name' => 'School Management System',
        'logo' => 'img/logo.png',
        'favicon' => 'img/favicon/favicon.ico',
        'motto' => 'Administrator Portal'
    ];
}

// functions.php

function getPageTitle($conn, $page_name = '') { 
    $settings = getSchoolSettings($conn); // Now $conn is the actual DB connection object
    $school_short_name = $settings['school_short_name'] ?? 'Dashboard';
    
    if (!empty($page_name)) {
        return "{$page_name} - {$school_short_name}";
    }
    return $school_short_name;
}

// Helper function to get individual setting
function getSchoolSetting($conn, $key, $default = '') {
    $settings = getSchoolSettings($conn);
    return $settings[$key] ?? $default;
}
?>