<?php
// access_control.php

function checkAccess($allowed_roles = ['admin']) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: access_denied.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}
?>