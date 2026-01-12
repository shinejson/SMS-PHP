<?php
require_once 'config.php';
require_once 'session.php';

SessionManager::destroy();

$timeout = isset($_GET['timeout']) ? '?timeout=1' : '';
header('Location: login.php' . $timeout);
exit();
?>