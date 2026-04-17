<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';

checkAuth();
checkRole(['admin']);

header('Content-Type: application/json');

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid parent email address.']);
    exit();
}

$otp = rand(100000, 999999);
$_SESSION['parent_email_otp'] = $otp;
$_SESSION['parent_email_to_verify'] = $email;
$_SESSION['parent_email_otp_time'] = time();
$_SESSION['parent_email_verified'] = 0;

$subject = 'Parent Email Verification OTP';
$message = "Your verification code is: $otp\n\nPlease enter this code in the admin panel to confirm the parent's email address.";

if(sendEmail($email, $subject, $message)) {
    echo json_encode(['success' => true, 'message' => 'OTP sent to the parent email address.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Unable to send OTP. Please check the email address and try again.']);
}
