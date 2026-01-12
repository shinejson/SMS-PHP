<?php
// functions/activity_logger.php

function logActivity($conn, $title, $description = null, $type = 'system', $icon = 'fas fa-info-circle', $user_id = null, $related_id = null) {
    // Use session user_id if not provided
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    // Get IP address
    $ip_address = getClientIP();
    
    $sql = "INSERT INTO activities (title, description, type, icon, user_id, related_id, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiss", $title, $description, $type, $icon, $user_id, $related_id, $ip_address);
    
    return $stmt->execute();
}

function getClientIP() {
    $ip_address = '';
    
    // Check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IP addresses from proxies
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip_address = trim($ip_address_list[0]);
    }
    // Check for the remote IP address
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }
    
    // Validate IP address format
    if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
        return $ip_address;
    }
    
    return 'Unknown';
}

function getActivityIcon($type) {
    $icons = [
        'login' => 'fas fa-sign-in-alt',
        'logout' => 'fas fa-sign-out-alt',
        'create' => 'fas fa-plus-circle',
        'update' => 'fas fa-edit',
        'delete' => 'fas fa-trash',
        'view' => 'fas fa-eye',
        'download' => 'fas fa-download',
        'upload' => 'fas fa-upload',
        'system' => 'fas fa-cog',
        'error' => 'fas fa-exclamation-triangle',
        'success' => 'fas fa-check-circle',
        'warning' => 'fas fa-exclamation-circle'
    ];
    
    return $icons[$type] ?? 'fas fa-info-circle';
}

// Helper function to log common activities
function logLoginActivity($conn, $user_id, $username) {
    $ip_address = getClientIP();
    return logActivity(
        $conn, 
        "User Login", 
        "User {$username} logged in successfully from IP: {$ip_address}", 
        'login', 
        getActivityIcon('login'), 
        $user_id
    );
}

function logCreateActivity($conn, $module, $item_name, $item_id = null) {
    return logActivity(
        $conn, 
        "{$module} Created", 
        "Created new {$module}: {$item_name}", 
        'create', 
        getActivityIcon('create'), 
        $_SESSION['user_id'] ?? null,
        $item_id
    );
}

function logUpdateActivity($conn, $module, $item_name, $item_id = null) {
    return logActivity(
        $conn, 
        "{$module} Updated", 
        "Updated {$module}: {$item_name}", 
        'update', 
        getActivityIcon('update'), 
        $_SESSION['user_id'] ?? null,
        $item_id
    );
}

function logDeleteActivity($conn, $module, $item_name, $item_id = null) {
    return logActivity(
        $conn, 
        "{$module} Deleted", 
        "Deleted {$module}: {$item_name}", 
        'delete', 
        getActivityIcon('delete'), 
        $_SESSION['user_id'] ?? null,
        $item_id
    );
}

// Function to get IP address information (optional - for enhanced logging)
function getIPInfo($ip) {
    if ($ip === 'Unknown' || $ip === '127.0.0.1' || $ip === '::1') {
        return 'Localhost';
    }
    
    // You can integrate with IP geolocation services here
    // Example: ipinfo.io, ipapi.com, etc.
    
    return $ip; // Return just IP for now, can be enhanced later
}