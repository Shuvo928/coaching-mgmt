<?php
session_start();
require_once '../includes/db.php';

$error = '';
$success = '';
$step = 'email';
$email = '';

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '22203229cse@gmail.com');
define('SMTP_PASS', 'xkizbspqkeytgzmp');
define('SMTP_FROM_EMAIL', '22203229cse@gmail.com');
define('SMTP_FROM_NAME', 'CoachingPro');
define('SMTP_SECURE', 'tls');
define('OTP_EXPIRY_SECONDS', 300);
define('OTP_RESEND_COOLDOWN', 30);

function getSmtpResponse($socket) {
    $response = '';
    while ($str = fgets($socket, 515)) {
        $response .= $str;
        if (isset($str[3]) && $str[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtpSendMail($to, $subject, $body) {
    $smtpHost = SMTP_HOST;
    $smtpPort = SMTP_PORT;
    $smtpUser = SMTP_USER;
    $smtpPass = SMTP_PASS;
    $smtpFromEmail = SMTP_FROM_EMAIL;
    $smtpFromName = SMTP_FROM_NAME;
    $smtpSecure = SMTP_SECURE;
    $newline = "\r\n";

    $socket = stream_socket_client("tcp://{$smtpHost}:{$smtpPort}", $errno, $errstr, 30);
    if (!$socket) {
        return "SMTP connect error: {$errstr} ({$errno})";
    }

    $response = fgets($socket, 515);
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return 'SMTP server response: ' . trim($response);
    }

    fputs($socket, "EHLO localhost{$newline}");
    $response = getSmtpResponse($socket);

    if ($smtpSecure === 'tls') {
        fputs($socket, "STARTTLS{$newline}");
        $response = getSmtpResponse($socket);
        if (strpos($response, '220') !== 0) {
            fclose($socket);
            return 'SMTP STARTTLS failed: ' . trim($response);
        }
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO localhost{$newline}");
        $response = getSmtpResponse($socket);
    }

    fputs($socket, "AUTH LOGIN{$newline}");
    $response = getSmtpResponse($socket);
    if (strpos($response, '334') !== 0) {
        fclose($socket);
        return 'SMTP auth init failed: ' . trim($response);
    }

    fputs($socket, base64_encode($smtpUser) . $newline);
    $response = getSmtpResponse($socket);
    if (strpos($response, '334') !== 0) {
        fclose($socket);
        return 'SMTP auth username failed: ' . trim($response);
    }

    fputs($socket, base64_encode($smtpPass) . $newline);
    $response = getSmtpResponse($socket);
    if (strpos($response, '235') !== 0) {
        fclose($socket);
        return 'SMTP auth password failed: ' . trim($response);
    }

    fputs($socket, "MAIL FROM:<{$smtpFromEmail}>{$newline}");
    $response = getSmtpResponse($socket);
    if (strpos($response, '250') !== 0) {
        fclose($socket);
        return 'MAIL FROM failed: ' . trim($response);
    }

    fputs($socket, "RCPT TO:<{$to}>{$newline}");
    $response = getSmtpResponse($socket);
    if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
        fclose($socket);
        return 'RCPT TO failed: ' . trim($response);
    }

    fputs($socket, "DATA{$newline}");
    $response = getSmtpResponse($socket);
    if (strpos($response, '354') !== 0) {
        fclose($socket);
        return 'DATA command failed: ' . trim($response);
    }

    $headers = "From: {$smtpFromName} <{$smtpFromEmail}>{$newline}";
    $headers .= "To: {$to}{$newline}";
    $headers .= "Subject: {$subject}{$newline}";
    $headers .= "MIME-Version: 1.0{$newline}";
    $headers .= "Content-Type: text/plain; charset=UTF-8{$newline}";
    $headers .= "Content-Transfer-Encoding: 7bit{$newline}{$newline}";

    $body = str_replace("\n", $newline, $body);
    fputs($socket, $headers . $body . $newline . ".{$newline}");
    $response = getSmtpResponse($socket);

    fputs($socket, "QUIT{$newline}");
    fclose($socket);

    if (strpos($response, '250') !== 0) {
        return 'SMTP send error: ' . trim($response);
    }

    return true;
}

if(isset($_POST['send_email'])) {
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));

    if(empty($email)) {
        $error = 'Please enter your registered admin email.';
    } else {
        $query = "SELECT id, username, email FROM users WHERE email = '$email' AND role = 'admin' AND status = 1";
        $result = mysqli_query($conn, $query);

        if(mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            $otp = rand(100000, 999999);
            $expires = time() + OTP_EXPIRY_SECONDS;

            $subject = 'CoachingPro Admin Password Reset OTP';
            $message = "Your OTP for password reset is: $otp\nThis code is valid for 5 minutes.";

            $sendStatus = smtpSendMail($user['email'], $subject, $message);
            if ($sendStatus === true) {
                $_SESSION['forgot_admin_id'] = $user['id'];
                $_SESSION['forgot_email'] = $user['email'];
                $_SESSION['forgot_otp'] = (string)$otp;
                $_SESSION['forgot_otp_expires'] = $expires;
                $_SESSION['forgot_otp_last_send'] = time();
                $_SESSION['forgot_verified'] = false;

                $step = 'otp';
                $success = 'OTP sent to your email. Please enter it below to reset your password.';
            } else {
                $error = 'Unable to send OTP to your email. ' . $sendStatus;
            }
        } else {
            $error = 'No active admin account was found for that email.';
        }
    }
} elseif(isset($_POST['verify_otp'])) {
    $enteredOtp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['forgot_email'] ?? '';

    if(empty($enteredOtp)) {
        $error = 'Please enter the OTP sent to your email.';
        $step = 'otp';
    } elseif(!isset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires']) || time() > $_SESSION['forgot_otp_expires']) {
        $error = 'OTP has expired. Please request a new OTP.';
        unset($_SESSION['forgot_admin_id'], $_SESSION['forgot_email'], $_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires'], $_SESSION['forgot_verified']);
        $step = 'email';
    } elseif($enteredOtp !== $_SESSION['forgot_otp']) {
        $error = 'Invalid OTP. Please try again.';
        $step = 'otp';
    } else {
        $_SESSION['forgot_verified'] = true;
        $success = 'OTP verified. Please set your new password.';
        $step = 'reset';
    }
} elseif(isset($_POST['resend_otp'])) {
    if(!isset($_SESSION['forgot_admin_id'], $_SESSION['forgot_email'])) {
        $error = 'Please start password reset again.';
        $step = 'email';
    } elseif(isset($_SESSION['forgot_otp_last_send']) && (time() - $_SESSION['forgot_otp_last_send']) < OTP_RESEND_COOLDOWN) {
        $remaining = OTP_RESEND_COOLDOWN - (time() - $_SESSION['forgot_otp_last_send']);
        $error = 'Please wait ' . $remaining . ' seconds before resending OTP.';
        $step = 'otp';
    } else {
        $otp = rand(100000, 999999);
        $expires = time() + OTP_EXPIRY_SECONDS;

        $subject = 'CoachingPro Admin Password Reset OTP';
        $message = "Your OTP for password reset is: $otp\nThis code is valid for 5 minutes.";

        $sendStatus = smtpSendMail($_SESSION['forgot_email'], $subject, $message);
        if ($sendStatus === true) {
            $_SESSION['forgot_otp'] = (string)$otp;
            $_SESSION['forgot_otp_expires'] = $expires;
            $_SESSION['forgot_otp_last_send'] = time();
            $_SESSION['forgot_verified'] = false;
            $success = 'OTP sent to your email. Please enter it below.';
            $step = 'otp';
        } else {
            $error = 'Unable to resend OTP. ' . $sendStatus;
            $step = 'otp';
        }
    }
} elseif(isset($_POST['reset_password'])) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if(empty($password) || empty($confirmPassword)) {
        $error = 'Please enter and confirm your new password.';
        $step = 'reset';
    } elseif($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
        $step = 'reset';
    } elseif(!isset($_SESSION['forgot_verified']) || $_SESSION['forgot_verified'] !== true) {
        $error = 'OTP verification is required before changing your password.';
        $step = 'email';
    } else {
        $adminId = $_SESSION['forgot_admin_id'] ?? 0;
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $update = "UPDATE users SET password = '$hashedPassword' WHERE id = $adminId AND role = 'admin'";

        if(mysqli_query($conn, $update)) {
            $success = 'Your password has been updated successfully. You may now log in with your new password.';
            $step = 'completed';
            unset($_SESSION['forgot_admin_id'], $_SESSION['forgot_email'], $_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires'], $_SESSION['forgot_verified']);
        } else {
            $error = 'Unable to update password. Please try again later.';
            $step = 'reset';
        }
    }
}

$otpResendCooldown = 0;
if ($step === 'otp' && isset($_SESSION['forgot_otp_last_send'])) {
    $otpResendCooldown = max(0, OTP_RESEND_COOLDOWN - (time() - $_SESSION['forgot_otp_last_send']));
}

function sanitize($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-container {
            width: 100%;
            max-width: 500px;
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .reset-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .reset-header h2 {
            margin-bottom: 10px;
            font-size: 28px;
        }
        .reset-body {
            padding: 35px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            height: 55px;
            border-radius: 10px;
            border: 1px solid #ced4da;
        }
        .btn-primary {
            width: 100%;
            border-radius: 10px;
            height: 55px;
            font-size: 16px;
        }
        .help-text {
            font-size: 14px;
            color: #6c757d;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-key fa-3x mb-3"></i>
            <h2>Forgot Password</h2>
            <p>Use your registered admin email to reset your password.</p>
        </div>
        <div class="reset-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo sanitize($error); ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo sanitize($success); ?>
                </div>
            <?php endif; ?>

            <?php if($step === 'email' || $step === 'completed'): ?>
                <form method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">Registered Admin Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="admin@example.com" value="<?php echo sanitize($email); ?>" required>
                    </div>
                    <button type="submit" name="send_email" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Send OTP
                    </button>
                </form>
                <?php if($step === 'completed'): ?>
                    <div class="back-link">
                        <a href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Return to login</a>
                    </div>
                <?php endif; ?>
            <?php elseif($step === 'otp'): ?>
                <form method="POST" action="" novalidate>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?php echo sanitize($_SESSION['forgot_email'] ?? $email); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="otp" class="form-label">Enter OTP</label>
                        <input type="text" id="otp" name="otp" class="form-control" maxlength="6" placeholder="6-digit code" required>
                        <div class="help-text">OTP is valid for 5 minutes.</div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i>Verify OTP
                    </button>
                    <button type="submit" name="resend_otp" id="resendOtpBtn" class="btn btn-outline-secondary mt-3" <?php echo $otpResendCooldown ? 'disabled' : ''; ?>>
                        Resend OTP
                    </button>
                    <?php if($otpResendCooldown): ?>
                        <div class="help-text mt-2" id="resendTimer">Resend available in <?php echo $otpResendCooldown; ?> seconds.</div>
                    <?php else: ?>
                        <div class="help-text mt-2" id="resendTimer">You can resend OTP after 30 seconds.</div>
                    <?php endif; ?>
                </form>
            <?php elseif($step === 'reset'): ?>
                <form method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="New password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        const resendCooldown = <?php echo json_encode($otpResendCooldown); ?>;
        function updateResendTimer(seconds) {
            const btn = document.getElementById('resendOtpBtn');
            const timer = document.getElementById('resendTimer');
            if (!btn || !timer) return;
            if (seconds <= 0) {
                btn.disabled = false;
                timer.textContent = 'You can resend OTP after 30 seconds.';
                return;
            }
            btn.disabled = true;
            timer.textContent = 'Resend available in ' + seconds + ' seconds.';
            setTimeout(() => updateResendTimer(seconds - 1), 1000);
        }
        if (resendCooldown > 0) {
            updateResendTimer(resendCooldown);
        }
    </script>
</body>
</html>
