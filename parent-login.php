<?php
session_start();
require_once 'includes/db.php';

// If already logged in, redirect to dashboard
if(isset($_SESSION['parent_id'])) {
    header("Location: parent/dashboard.php");
    exit();
}

$error = '';

// Handle login
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $parent_email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check in admission_applications table first
    $query = "SELECT * FROM admission_applications WHERE parent_email = '$parent_email' AND status = 'Approved' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) > 0) {
        $parent = mysqli_fetch_assoc($result);
        
        // For now, password is the student's mobile number (as shown in form)
        // You can change this to a hashed password system
        if($password == $parent['mobile']) {
            $_SESSION['parent_id'] = $parent['id'];
            $_SESSION['parent_name'] = $parent['parent_name'];
            $_SESSION['parent_email'] = $parent['parent_email'];
            $_SESSION['student_name'] = $parent['full_name'];
            $_SESSION['student_mobile'] = $parent['mobile'];
            
            header("Location: parent/dashboard.php");
            exit();
        } else {
            $error = "Invalid password. Use your child's mobile number.";
        }
    } else {
        $error = "Parent email not found or admission not approved.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal Login - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header i {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
        }

        .login-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #999;
        }

        .password-hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
            font-style: italic;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 25px;
            border: none;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s;
            margin-bottom: 15px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-back {
            background: #f0f4f9;
            color: #333;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #333;
        }

        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #666;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #666;
        }

        .feature-item i {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <i class="fas fa-users"></i>
                <h2>Parent Portal</h2>
                <p>Monitor Your Child's Progress</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Parent Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                        <div class="password-hint">
                            <i class="fas fa-info-circle"></i> Use your child's mobile number as password
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to Parent Portal
                    </button>
                </form>

                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Check child's attendance</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>View exam results & grades</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Track fee status</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Monitor academic progress</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <a href="index.php" class="me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Home
                </a> | 
                <a href="admission.php" class="ms-2">
                    Student Admission <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
