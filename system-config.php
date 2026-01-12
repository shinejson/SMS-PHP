<?php
// system-config.php
// System Configuration using your school_settings table

function getSchoolSettings() {
    global $conn;
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        $result = $conn->query("SELECT * FROM school_settings ORDER BY id DESC LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $settings = $result->fetch_assoc();
        } else {
            // Fallback defaults if no settings found
            $settings = [
                'school_name' => 'GEBSCO',
                'school_short_name' => 'GEBSCO Dashboard',
                'logo' => 'img/logo.png',
                'motto' => 'Administrator Portal',
                'favicon' => 'img/favicon/favicon.ico'
            ];
        }
    }
    
    return $settings;
}

function getSchoolSetting($key, $default = '') {
    $settings = getSchoolSettings();
    return $settings[$key] ?? $default;
}

// You can add this to your system-config.php or a separate functions file

/**
 * Get all school settings as an array
 */
function getSchoolInfo() {
    return getSchoolSettings();
}

/**
 * Get specific school setting with fallback
 */
function getSchoolInfoItem($key, $default = '') {
    return getSchoolSetting($key, $default);
}

/**
 * Display school logo HTML
 */
function displaySchoolLogo($class = '', $width = 100) {
    $logo = getSchoolSetting('logo');
    $school_name = getSchoolSetting('school_name');
    
    if (!empty($logo)) {
        return '<img src="' . htmlspecialchars($logo) . '" 
                    alt="' . htmlspecialchars($school_name) . ' Logo" 
                    width="' . $width . '" 
                    class="' . $class . '"
                    onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
    }
    
    return '';
}


?>
