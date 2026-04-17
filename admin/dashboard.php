<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only admin can access this page
if($role != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get dashboard statistics
$stats = [];

// Total Students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status = 1");
$stats['total_students'] = mysqli_fetch_assoc($result)['total'];

// Total Teachers
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers WHERE status = 1");
$stats['total_teachers'] = mysqli_fetch_assoc($result)['total'];

// Total Classes
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM classes");
$stats['total_classes'] = mysqli_fetch_assoc($result)['total'];

// Pending Fees
$result = mysqli_query($conn, "SELECT SUM(expected_amount - paid_amount) as total FROM fee_collections WHERE payment_status != 'paid'");
$stats['pending_fees'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Monthly Income (current month)
$month = date('m');
$year = date('Y');
$result = mysqli_query($conn, "SELECT SUM(paid_amount) as total FROM fee_collections WHERE MONTH(payment_date) = $month AND YEAR(payment_date) = $year AND payment_status = 'paid'");
$stats['monthly_income'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Today's Attendance
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE date = '$today' AND status = 'Present'");
$stats['today_present'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Upcoming Exams (next 7 days)
$next_week = date('Y-m-d', strtotime('+7 days'));
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM exam_routine WHERE exam_date BETWEEN '$today' AND '$next_week'");
$stats['upcoming_exams'] = mysqli_fetch_assoc($result)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CoachingPro</title>
    
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
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            min-height: 100vh;
            color: white;
            position: fixed;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin-top: 10px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 35px;
        }

        .menu-item i {
            width: 30px;
            font-size: 18px;
        }

        .menu-item span {
            font-size: 14px;
            font-weight: 500;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 30px;
        }

        /* Top Navbar */
        .top-navbar {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h4 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }

        .stat-info p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
        .stat-icon.green { background: #e8f5e9; color: #388e3c; }
        .stat-icon.orange { background: #fff3e0; color: #f57c00; }
        .stat-icon.red { background: #ffebee; color: #d32f2f; }
        .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        /* Recent Activities */
        .recent-activities {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f4f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2a5298;
        }

        .activity-details p {
            margin: 0;
            font-weight: 500;
        }

        .activity-details small {
            color: #999;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h3, .menu-item span {
                display: none;
            }
            
            .menu-item {
                justify-content: center;
                padding: 15px;
            }
            
            .menu-item i {
                width: auto;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-3x"></i>
                <h3>CoachingPro</h3>
                <small>Admin Panel</small>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="student-management.php" class="menu-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                </a>
                <a href="teacher-management.php" class="menu-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
                </a>
                <a href="add_routine.php" class="menu-item">
    <i class="fas fa-calendar-plus"></i>
    <span>Add Routine</span>
</a>
                <a href="admission-management.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Admissions</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="result-system.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Result System</span>
                </a>
                <a href="fees-management.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Fees Management</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="page-title">
                    <h4>Dashboard</h4>
                </div>
                <div class="user-info">
                    <i class="fas fa-bell text-muted"></i>
                    <i class="fas fa-envelope text-muted"></i>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['display_name']; ?>&background=2a5298&color=fff" alt="User">
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $stats['total_teachers']; ?></h3>
                        <p>Total Teachers</p>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $stats['total_classes']; ?></h3>
                        <p>Total Classes</p>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-school"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $stats['today_present']; ?></h3>
                        <p>Present Today</p>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>৳<?php echo number_format($stats['monthly_income']); ?></h3>
                        <p>Monthly Income</p>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>৳<?php echo number_format($stats['pending_fees']); ?></h3>
                        <p>Pending Fees</p>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5>Attendance Overview (Last 7 Days)</h5>
                        <select class="form-select form-select-sm w-auto">
                            <option>This Week</option>
                            <option>Last Week</option>
                            <option>This Month</option>
                        </select>
                    </div>
                    <canvas id="attendanceChart" style="height: 300px;"></canvas>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h5>Fee Collection</h5>
                        <select class="form-select form-select-sm w-auto">
                            <option>2024</option>
                            <option>2023</option>
                        </select>
                    </div>
                    <canvas id="feeChart" style="height: 300px;"></canvas>
                </div>
            </div>

            <!-- Recent Activities & Upcoming Exams -->
            <div class="row">
                <div class="col-md-6">
                    <div class="recent-activities">
                        <h5 class="mb-4">Recent Activities</h5>
                        
                        <?php
                        // Get recent fee collections
                        $recent = mysqli_query($conn, "SELECT fc.*, s.first_name, s.last_name 
                                                       FROM fee_collections fc 
                                                       JOIN students s ON fc.student_id = s.id 
                                                       ORDER BY fc.created_at DESC 
                                                       LIMIT 5");
                        
                        if(mysqli_num_rows($recent) > 0) {
                            while($row = mysqli_fetch_assoc($recent)) {
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-rupee-sign"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo $row['first_name'] . ' ' . $row['last_name']; ?> paid ₹<?php echo $row['paid_amount']; ?></p>
                                        <small><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="recent-activities">
                        <h5 class="mb-4">Upcoming Exams</h5>
                        
                        <?php
                        // Get upcoming exams
                        $exams = mysqli_query($conn, "SELECT er.*, et.exam_name, c.class_name, s.subject_name 
                                                       FROM exam_routine er
                                                       JOIN exam_types et ON er.exam_type_id = et.id
                                                       JOIN classes c ON er.class_id = c.id
                                                       JOIN subjects s ON er.subject_id = s.id
                                                       WHERE er.exam_date >= CURDATE()
                                                       ORDER BY er.exam_date ASC
                                                       LIMIT 5");
                        
                        if(mysqli_num_rows($exams) > 0) {
                            while($row = mysqli_fetch_assoc($exams)) {
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-pen"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo $row['exam_name']; ?> - <?php echo $row['subject_name']; ?></p>
                                        <small><?php echo $row['class_name']; ?> | <?php echo date('d M Y', strtotime($row['exam_date'])); ?></small>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Attendance Chart
        const ctx1 = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Present',
                    data: [65, 72, 68, 75, 70, 55, 48],
                    borderColor: '#2a5298',
                    backgroundColor: 'rgba(42, 82, 152, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Absent',
                    data: [12, 8, 10, 7, 9, 15, 18],
                    borderColor: '#d32f2f',
                    backgroundColor: 'rgba(211, 47, 47, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Fee Chart
        const ctx2 = document.getElementById('feeChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Collected', 'Pending', 'Overdue'],
                datasets: [{
                    data: [75, 15, 10],
                    backgroundColor: ['#388e3c', '#f57c00', '#d32f2f'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>