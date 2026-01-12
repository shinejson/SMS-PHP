<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$settings = getSchoolSettings($conn);

$manifest = [
    "name" => $settings['school_name'] ?? 'School Management System',
    "short_name" => $settings['school_short_name'] ?? 'SchoolMS',
    "description" => $settings['motto'] ?? 'Educational Management Platform',
    "icons" => [
        [
            "src" => $settings['favicon'] ?? '/gebsco/img/favicon/favicon.ico',
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $settings['logo'] ?? '/gebsco/img/logo.png',
            "sizes" => "512x512", 
            "type" => "img/favicon/png"
        ]
    ],
    "theme_color" => "#2c3e50",
    "background_color" => "#ffffff",
    "display" => "standalone",
    // In manifest.php - Ensure correct start_url
"start_url" => "/gebsco/login.php",  // Change this from "/gebsco/"
"scope" => "/gebsco/",
    "orientation" => "portrait-primary"
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>