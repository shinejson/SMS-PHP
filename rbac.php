<?php
// Role-Based Access Control Helper Functions

function hasPermission($requiredRole) {
    $userRole = $_SESSION['role'] ?? 'guest';
    
    // Define role hierarchy
    $roleHierarchy = [
        'admin' => ['admin', 'teacher', 'staff'],
        'teacher' => ['teacher'],
        'staff' => ['staff'],
        'guest' => ['guest']
    ];
    
    return in_array($userRole, $roleHierarchy[$requiredRole] ?? []);
}

function requirePermission($requiredRole) {
    if (!hasPermission($requiredRole)) {
        http_response_code(403);
        die("Access Denied. You don't have permission to access this page.");
    }
}

function getAccessiblePages() {
    $userRole = $_SESSION['role'] ?? 'guest';
    
    $pagesByRole = [
        'admin' => [
            'index.php', 'students.php', 'subjects.php', 'teachers.php', 
            'users.php', 'classes.php', 'marks.php', 'master-Score.php',
            'payments.php', 'view_bills.php', 'payment-ladger.php', 'event.php',
            'messages.php', 'report.php', 'school_settings.php'
        ],
        'teacher' => [
            'index.php', 'subjects.php', 'classes.php', 'marks.php', 
            'master-Score.php', 'messages.php'
        ],
        'staff' => [
            'index.php', 'students.php', 'teachers.php', 'classes.php',
            'payments.php', 'view_bills.php', 'payment-ladger.php', 'event.php',
            'messages.php', 'report.php'
        ]
    ];
    
    return $pagesByRole[$userRole] ?? ['index.php', 'messages.php'];
}
?>