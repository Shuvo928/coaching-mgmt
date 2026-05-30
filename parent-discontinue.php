<?php
session_start();

if (!isset($_SESSION['parent_id'])) {
    header('Location: parent-login.php');
    exit();
}

$_SESSION['error'] = 'Enrollment discontinuation has been disabled.';
header('Location: parent/dashboard.php');
exit();
