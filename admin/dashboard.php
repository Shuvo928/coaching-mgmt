<?php
session_start();
require_once '../includes/db.php';require_once '../includes/parent_helpers.php';
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

// Handle setting parent credentials
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_credentials'])) {
    $admission_id = (int) $_POST['admission_id'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $app_query = "SELECT parent_name, parent_email, parent_phone, COALESCE(mobile, phone) AS student_mobile FROM admission_applications WHERE id = $admission_id AND status = 'Approved' LIMIT 1";
    $app_result = mysqli_query($conn, $app_query);
    $app = mysqli_fetch_assoc($app_result);

    if ($app) {
        $parent_id = createOrUpdateParentRecord(
            $conn,
            $app['parent_name'],
            $app['parent_email'],
            $app['parent_phone'],
            $username,
            $password_hash,
            'Active'
        );

        if ($parent_id) {
            linkParentToStudentByPhone($conn, $parent_id, $app['student_mobile']);
            $_SESSION['success'] = "Parent credentials set successfully!";
        } else {
            $_SESSION['error'] = "Error creating parent account.";
        }
    } else {
        $_SESSION['error'] = "Admission record not found or not approved.";
    }

    header("Location: dashboard.php");
    exit();
}

// Get approved admissions for parent management
$query = "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, parent_name, parent_email, parent_phone, username FROM admission_applications WHERE status = 'Approved' ORDER BY id DESC";
$admissions = mysqli_query($conn, $query);
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
                <a href="#parent-management" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Parent Accounts</span>
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

            <!-- Alerts -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

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
                        <i class="taka-sign"></i>
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

           

                
            <!-- Parent Management -->
            <div id="parent-management" class="recent-activities">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Parent Account Management</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Parent Name</th>
                                <th>Parent Email</th>
                                <th>Parent Phone</th>
                                <th>Username</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($admission = mysqli_fetch_assoc($admissions)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admission['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($admission['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($admission['parent_email']); ?></td>
                                <td><?php echo htmlspecialchars($admission['parent_phone']); ?></td>
                                <td><?php echo $admission['username'] ? htmlspecialchars($admission['username']) : '<span class="text-muted">Not Set</span>'; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="setCredentials(<?php echo $admission['id']; ?>)">Set Credentials</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal for Setting Credentials -->
            <div class="modal fade" id="credentialsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Set Parent Credentials</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="admission_id" id="admission_id">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="set_credentials" class="btn btn-primary">Set Credentials</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Function to set credentials
        function setCredentials(id) {
            document.getElementById('admission_id').value = id;
            var modal = new bootstrap.Modal(document.getElementById('credentialsModal'));
            modal.show();
        }
    </script>
</body>
</html>