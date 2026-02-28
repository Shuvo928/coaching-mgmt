<?php
session_start();
require_once '../includes/db.php';

$error = '';
$success = '';

// Check if already logged in
if(isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle Login Form Submission
if(isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Query to check user
        $query = "SELECT u.*, 
                  CASE 
                    WHEN u.role = 'admin' THEN 'Admin'
                    WHEN u.role = 'teacher' THEN t.first_name
                    WHEN u.role = 'student' THEN s.first_name
                  END as display_name
                  FROM users u 
                  LEFT JOIN teachers t ON u.id = t.user_id
                  LEFT JOIN students s ON u.id = s.user_id
                  WHERE u.username = '$username' AND u.status = 1";
        
        $result = mysqli_query($conn, $query);
        
        if(mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password (you'll hash passwords later)
            if(password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['display_name'] = $user['display_name'];
                
                // Update last login
                $update = "UPDATE users SET last_login = NOW() WHERE id = ".$user['id'];
                mysqli_query($conn, $update);
                
                // Redirect based on role
                if($user['role'] == 'admin') {
                    header("Location: dashboard.php");
                } elseif($user['role'] == 'teacher') {
                    header("Location: ../teacher/dashboard.php");
                } elseif($user['role'] == 'student') {
                    header("Location: ../student/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Username not found or account inactive!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 40px;
            text-align: center;
            color: white;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }

        .form-control {
            height: 55px;
            padding: 10px 15px 10px 50px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            outline: none;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            height: 55px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .alert-danger {
            background: #fee;
            color: #c33;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .back-to-home {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-home a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .back-to-home a:hover {
            color: #667eea;
        }

        .back-to-home i {
            margin-right: 5px;
        }

        .role-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .role-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            background: white;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .role-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
        }

        .role-btn i {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }

        @media (max-width: 480px) {
            .login-header {
                padding: 30px;
            }
            
            .login-body {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-graduation-cap fa-3x mb-3"></i>
            <h2>Welcome Back!</h2>
            <p>Login to access your dashboard</p>
        </div>
        
        <div class="login-body">
            <!-- Role Selector (Optional - for demo) -->
            <div class="role-selector">
                <button type="button" class="role-btn active" onclick="setRole('admin')">
                    <i class="fas fa-user-shield"></i>
                    Admin
                </button>
                <button type="button" class="role-btn" onclick="setRole('teacher')">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Teacher
                </button>
                <button type="button" class="role-btn" onclick="setRole('student')">
                    <i class="fas fa-user-graduate"></i>
                    Student
                </button>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>

                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>

                <div class="text-center">
                    <p class="text-muted mb-0">Demo Credentials:</p>
                    <small class="text-muted">Admin: admin / admin123</small>
                </div>
            </form>

            <div class="back-to-home">
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Role selector functionality (for demo)
        function setRole(role) {
            // Remove active class from all buttons
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            event.target.closest('.role-btn').classList.add('active');
            
            // Set demo credentials based on role
            const usernameInput = document.querySelector('input[name="username"]');
            const passwordInput = document.querySelector('input[name="password"]');
            
            switch(role) {
                case 'admin':
                    usernameInput.value = 'admin';
                    passwordInput.value = 'admin123';
                    break;
                case 'teacher':
                    usernameInput.value = 'teacher';
                    passwordInput.value = 'teacher123';
                    break;
                case 'student':
                    usernameInput.value = 'student';
                    passwordInput.value = 'student123';
                    break;
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            if(username.trim() === '' || password.trim() === '') {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });

        // Auto-fill admin credentials on page load (for demo)
        window.onload = function() {
            document.querySelector('input[name="username"]').value = 'admin';
            document.querySelector('input[name="password"]').value = 'admin123';
        };
    </script>
</body>
</html>