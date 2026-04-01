<?php
session_start();
require_once '../includes/db.php';

// Check if parent is logged in
if(!isset($_SESSION['parent_id'])) {
    header("Location: ../parent-login.php");
    exit();
}

$parent_id = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];
$student_name = $_SESSION['student_name'];
$student_mobile = $_SESSION['student_mobile'];

// Get student info and program details
$query = "SELECT * FROM admission_applications WHERE id = $parent_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// Get attendance count (assuming student exists in students table)
$attendance_query = "SELECT COUNT(*) as total_present FROM attendance 
                     WHERE EXTRACT(YEAR FROM date) = YEAR(NOW()) 
                     AND EXTRACT(MONTH FROM date) = MONTH(NOW())
                     LIMIT 1";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance = mysqli_fetch_assoc($attendance_result);

// Get pending fees
$fees_query = "SELECT SUM(due_amount) as total_pending FROM fee_collections 
               WHERE status != 'Paid' LIMIT 1";
$fees_result = mysqli_query($conn, $fees_query);
$fees = mysqli_fetch_assoc($fees_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - CoachingPro</title>
    
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
            background: #f4f7fc;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            opacity: 0.9;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #64b5f6;
        }

        .sidebar-menu i {
            width: 25px;
            margin-right: 15px;
            font-size: 18px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .top-bar-title h2 {
            margin: 0;
            font-weight: 700;
            color: #333;
            font-size: 28px;
        }

        .top-bar-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .user-info small {
            color: #999;
            display: block;
        }

        .logout-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
        .stat-icon.green { background: #e8f5e9; color: #388e3c; }
        .stat-icon.orange { background: #fff3e0; color: #f57c00; }
        .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Student Info Card */
        .student-info-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .student-info-card h4 {
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .info-item strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
        }

        /* Quick Links */
        .quick-links {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .quick-links h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .quick-link {
            background: #f8f9fa;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .quick-link:hover {
            border-color: #667eea;
            background: #f0f4f9;
            color: #667eea;
        }

        .quick-link i {
            display: block;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .quick-link span {
            font-weight: 600;
            font-size: 14px;
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                margin-left: -280px;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>CoachingPro</h3>
                <small>Parent Portal</small>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="attendance.php">
                        <i class="fas fa-calendar-check"></i>
                        Attendance
                    </a>
                </li>
                <li>
                    <a href="results.php">
                        <i class="fas fa-chart-bar"></i>
                        Results & Grades
                    </a>
                </li>
                <li>
                    <a href="fees.php">
                        <i class="fas fa-money-bill"></i>
                        Fees & Payments
                    </a>
                </li>
                <li>
                    <a href="progress.php">
                        <i class="fas fa-graduation-cap"></i>
                        Progress
                    </a>
                </li>
                <li>
                    <a href="../parent-logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-title">
                    <h2>Dashboard</h2>
                    <p>Welcome back, <?php echo htmlspecialchars($parent_name); ?>!</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <p><?php echo htmlspecialchars($parent_name); ?></p>
                        <small>Parent</small>
                    </div>
                    <a href="../parent-logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>

            <!-- Student Info Card -->
            <div class="student-info-card">
                <h4><i class="fas fa-user-graduate me-2"></i>Your Child's Information</h4>
                <div class="info-row">
                    <div class="info-item">
                        <p>Student Name</p>
                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                    </div>
                    <div class="info-item">
                        <p>Program</p>
                        <strong><?php echo htmlspecialchars($student['program']); ?> - <?php echo htmlspecialchars($student['group']); ?></strong>
                    </div>
                    <div class="info-item">
                        <p>Enrollment Status</p>
                        <strong style="color: #4caf50;">✓ Approved</strong>
                    </div>
                    <div class="info-item">
                        <p>Application Date</p>
                        <strong><?php echo date('d M, Y', strtotime($student['created_at'])); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value"><?php echo $attendance['total_present'] ?? 0; ?></div>
                    <div class="stat-label">This Month's Attendance</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-award"></i></div>
                    <div class="stat-value">--</div>
                    <div class="stat-label">Latest Grade</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-value">৳<?php echo number_format($fees['total_pending'] ?? 0, 2); ?></div>
                    <div class="stat-label">Pending Fees</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value">--</div>
                    <div class="stat-label">Overall Progress</div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="quick-links">
                <h5><i class="fas fa-star me-2"></i>Quick Access</h5>
                <div class="links-grid">
                    <a href="attendance.php" class="quick-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>Check Attendance</span>
                    </a>
                    <a href="results.php" class="quick-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Results</span>
                    </a>
                    <a href="fees.php" class="quick-link">
                        <i class="fas fa-receipt"></i>
                        <span>Fee Details</span>
                    </a>
                    <a href="progress.php" class="quick-link">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Check Progress</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
