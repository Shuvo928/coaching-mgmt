<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['admin']);

header('Content-Type: application/json');

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid parent email address.']);
    exit();
}

if(empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Please enter the OTP sent to the parent email.']);
    exit();
}

if(!isset($_SESSION['parent_email_otp'], $_SESSION['parent_email_to_verify'], $_SESSION['parent_email_otp_time'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP was requested. Please send the OTP first.']);
    exit();
}

if($_SESSION['parent_email_to_verify'] !== $email) {
    echo json_encode(['success' => false, 'message' => 'The email does not match the one used to request OTP.']);
    exit();
}

if(time() - $_SESSION['parent_email_otp_time'] > 600) {
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
    exit();
}

if($_SESSION['parent_email_otp'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
    exit();
}

$_SESSION['parent_email_verified'] = 1;
echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
