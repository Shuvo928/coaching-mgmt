<?php
session_start();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/parent_helpers.php';
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

// Pending Fees = (Monthly Fees Due) + (Unpaid Admission Fees)
// Get unpaid monthly fees
$unpaid_monthly = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(expected_amount - paid_amount) as total FROM fee_collections WHERE payment_status != 'paid'")
)['total'] ?? 0;

// Get unpaid admission fees
$unpaid_admission = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(application_fee) as total FROM admission_applications WHERE status = 'Approved' AND (transaction_id = '' OR transaction_id IS NULL)")
)['total'] ?? 0;

$stats['pending_fees'] = $unpaid_monthly + $unpaid_admission;
$stats['discontinue_requests'] = getPendingParentDiscontinueRequestCount($conn);
$stats['pending_admissions'] = getPendingAdmissionsCount($conn);

// Monthly Income = (Paid Admission Fees) + (Paid Monthly Fees) for current month
$month = date('m');
$year = date('Y');

// Get paid monthly fees for current month
$stmt = mysqli_prepare($conn, "SELECT SUM(paid_amount) as total FROM fee_collections WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ? AND payment_status = 'paid'");
mysqli_stmt_bind_param($stmt, 'ii', $month, $year);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$paid_monthly = mysqli_fetch_assoc($result)['total'] ?? 0;

// Get paid admission fees for current month
$stmt = mysqli_prepare($conn, "SELECT SUM(application_fee) as total FROM admission_applications WHERE MONTH(application_date) = ? AND YEAR(application_date) = ? AND application_fee > 0 AND transaction_id <> ''");
mysqli_stmt_bind_param($stmt, 'ii', $month, $year);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$paid_admission = mysqli_fetch_assoc($result)['total'] ?? 0;

$stats['monthly_income'] = $paid_monthly + $paid_admission;

// Today's Attendance
$today = date('Y-m-d');
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM attendance WHERE date = ? AND status = 'Present'");
mysqli_stmt_bind_param($stmt, 's', $today);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['today_present'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Upcoming Exams (next 7 days)
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM exam_routine WHERE exam_date BETWEEN ? AND ?");
mysqli_stmt_bind_param($stmt, 'ss', $today, $next_week);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['upcoming_exams'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Handle deleting parent account
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_parent'])) {
    $admission_id = (int) $_POST['admission_id'];
    
    // Get the parent info first
    $parent_query = "SELECT parent_email, parent_phone FROM admission_applications WHERE id = $admission_id LIMIT 1";
    $parent_result = mysqli_query($conn, $parent_query);
    $parent_data = mysqli_fetch_assoc($parent_result);
    
    if ($parent_data) {
        $parent_email = mysqli_real_escape_string($conn, $parent_data['parent_email']);
        $parent_phone = mysqli_real_escape_string($conn, $parent_data['parent_phone']);
        
        // Delete from users table (parent account)
        mysqli_query($conn, "DELETE FROM users WHERE email = '$parent_email' AND role = 'parent'");
        
        // Get parent_id before deleting from parents table
        $get_parent_id = "SELECT id FROM parents WHERE parent_email = '$parent_email'";
        $parent_id_result = mysqli_query($conn, $get_parent_id);
        if ($parent_id_result && $row = mysqli_fetch_assoc($parent_id_result)) {
            $parent_id = (int)$row['id'];
            
            // Clear parent_id from students table
            mysqli_query($conn, "UPDATE students SET parent_id = NULL WHERE parent_id = $parent_id");
        }
        
        // Delete from parents table
        $delete_parent = "DELETE FROM parents WHERE parent_email = '$parent_email'";
        $delete_result = mysqli_query($conn, $delete_parent);
        
        // Clear username from admission_applications
        mysqli_query($conn, "UPDATE admission_applications SET username = NULL WHERE id = $admission_id");
        
        if ($delete_result && mysqli_affected_rows($conn) > 0) {
            $_SESSION['success'] = "Parent account deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting parent account. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Admission record not found.";
    }
    
    header("Location: dashboard.php");
    exit();
}

// Handle setting parent credentials
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_credentials'])) {
    $admission_id = (int) $_POST['admission_id'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $hasMobileColumn = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'")) > 0;
    $hasPhoneColumn = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'phone'")) > 0;
    $phoneField = $hasMobileColumn ? 'mobile' : ($hasPhoneColumn ? 'phone' : "''");

    $app_query = "SELECT parent_name, parent_email, parent_phone, $phoneField AS student_mobile FROM admission_applications WHERE id = $admission_id AND status = 'Approved' LIMIT 1";
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
            
            // Update admission_applications table with username for display
            $update_admission = "UPDATE admission_applications SET username = '$username' WHERE id = $admission_id";
            mysqli_query($conn, $update_admission);
            
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
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
            height: 100vh;
            color: white;
            position: fixed;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin-top: 10px;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex: 1;
            overflow-y: auto;
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

        .notification-badge {
            background: red;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 50%;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-3x"></i>
                <h3>Coaching</h3>
                <small>Admin Panel</small>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admission-management.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Admissions</span>
                    <?php if($stats['pending_admissions'] > 0): ?>
                        <span class="notification-badge"><?php echo $stats['pending_admissions']; ?></span>
                    <?php endif; ?>
                </a>

                <a href="student-management.php" class="menu-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                </a>
                 
                <a href="teacher-management.php" class="menu-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
                </a>
                
               
                <a href="routine-management.php" class="menu-item">
    <i class="fas fa-calendar-alt"></i>
    <span>Routine Management</span>
</a>
                <a href="parent-discontinue-requests.php" class="menu-item">
                    <i class="fas fa-user-slash"></i>
                    <span>Discontinue Requests</span>
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
                        <h3><?php echo $stats['discontinue_requests']; ?></h3>
                        <p>Discontinue Requests</p>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-bell"></i>
                    </div>
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
                                    <button class="btn btn-sm btn-danger" onclick="deleteParent(<?php echo $admission['id']; ?>, '<?php echo htmlspecialchars($admission['full_name']); ?>')" title="Delete Parent Account">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
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
        
        // Function to delete parent account
        function deleteParent(id, studentName) {
            if (confirm('Are you sure you want to delete the parent account for ' + studentName + '? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="admission_id" value="${id}">
                    <input type="hidden" name="delete_parent" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Initialize Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart')?.getContext('2d');
        if (attendanceCtx) {
            new Chart(attendanceCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Present',
                        data: [65, 59, 80, 81, 56, 55, 70],
                        borderColor: '#2a5298',
                        backgroundColor: 'rgba(42, 82, 152, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>