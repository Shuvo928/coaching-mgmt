<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $message, $isHtml = true, &$errorMessage = null) {
    if (empty($to)) {
        $errorMessage = 'Recipient email address is empty.';
        return false;
    }

    $smtpUser = '22203229cse@gmail.com';
    $smtpPassword = 'dpdvvpkcimgzmsyl';
    $fromEmail = '22203229cse@gmail.com';
    $fromName = 'CoachingPro Admin';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $sent = $mail->send();
        if (!$sent) {
            $errorMessage = $mail->ErrorInfo;
        }
        return $sent;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        error_log('PHPMailer error: ' . $errorMessage);
        return false;
    }
}
