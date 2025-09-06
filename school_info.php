<?php
require_once 'config.php';

function getSchoolSettings($conn) {
    $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}
