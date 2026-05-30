<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';
require_once '../includes/payment_helpers.php';
require_once '../includes/sslcommerz_config.php';

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../parent-login.php');
    exit();
}

$_SESSION['error'] = 'Payment failed or was cancelled. Please try again.';
header('Location: fees.php');
exit();
