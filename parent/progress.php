<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';

// Check if parent is logged in
if(!isset($_SESSION['parent_id'])) {
    header("Location: ../parent-login.php");
    exit();
}

$parent_id = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];
$student_name = $_SESSION['student_name'] ?? '';
$student_mobile = $_SESSION['student_mobile'] ?? '';

$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$firstStudent = getFirstParentStudent($conn, $parent_id, $student_mobile);
$student_id = $firstStudent['id'] ?? 0;
$student_mobile = $student_mobile ?: ($firstStudent['phone'] ?? '');

// Get student enrollment and program info
$student_query = "SELECT 
                    full_name,
                    email,
                    COALESCE(mobile, phone) AS mobile,
                    program,
                    `group`,
                    monthly_fee,
                    created_at,
                    status
                  FROM admission_applications 
                  WHERE COALESCE(mobile, phone) = '$student_mobile' LIMIT 1";

$student_result = mysqli_query($conn, $student_query);
$student = mysqli_fetch_assoc($student_result);

$student_ids_list = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// Get total classes attended
$class_query = "SELECT COUNT(*) as classes_attended FROM attendance 
                WHERE student_id IN ($student_ids_list) AND status = 'Present'";
$class_result = mysqli_query($conn, $class_query);
$class_data = mysqli_fetch_assoc($class_result);

// Get average marks
$marks_query = "SELECT AVG(percentage) as avg_marks FROM results 
                WHERE student_id IN ($student_ids_list)";
$marks_result = mysqli_query($conn, $marks_query);
$marks_data = mysqli_fetch_assoc($marks_result);

// Get enrollment duration
$enrollment_date = new DateTime($student['created_at']);
$today = new DateTime();
$duration = $today->diff($enrollment_date);
$months = ($duration->y * 12) + $duration->m;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - Parent Portal</title>
    
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

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .top-bar h2 {
            margin: 0;
            font-weight: 700;
            color: #333;
        }

        .top-bar p {
            margin: 5px 0 0 0;
            color: #666;
        }

        /* Enrollment Card */
        .enrollment-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .enrollment-card h4 {
            font-weight: 600;
            margin-bottom: 20px;
        }

        .enrollment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .enrollment-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
        }

        .enrollment-item-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .enrollment-item-value {
            font-size: 18px;
            font-weight: 600;
        }

        /* Progress Metrics */
        .progress-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-align: center;
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 15px;
        }

        .metric-icon.blue {
            background: #e3f2fd;
            color: #1976d2;
        }

        .metric-icon.green {
            background: #e8f5e9;
            color: #388e3c;
        }

        .metric-icon.orange {
            background: #fff3e0;
            color: #f57c00;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .metric-label {
            color: #666;
            font-size: 14px;
        }

        /* Detailed Info Cards */
        .detail-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .detail-card h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
        }

        .detail-card h5 i {
            margin-right: 12px;
            color: #667eea;
            font-size: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item-label {
            color: #666;
            font-size: 14px;
        }

        .detail-item-value {
            font-weight: 600;
            color: #333;
        }

        .badge-success {
            display: inline-block;
            background: #c8e6c9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .progress-bar-custom {
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
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

            .enrollment-grid {
                grid-template-columns: 1fr;
            }

            .progress-metrics {
                grid-template-columns: 1fr;
            }

            .detail-cards {
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
                <h3>CoachingPro</h3>
                <small>Parent Portal</small>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
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
                    <a href="progress.php" class="active">
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
                <h2><i class="fas fa-graduation-cap me-3" style="color: #667eea;"></i>Progress</h2>
                <p>Track <?php echo htmlspecialchars($student_name); ?>'s academic progress</p>
            </div>

            <!-- Enrollment Info Card -->
            <div class="enrollment-card">
                <h4><i class="fas fa-user-graduate me-2"></i>Enrollment Information</h4>
                <div class="enrollment-grid">
                    <div class="enrollment-item">
                        <div class="enrollment-item-label">Program</div>
                        <div class="enrollment-item-value"><?php echo htmlspecialchars($student['program']); ?></div>
                    </div>
                    <div class="enrollment-item">
                        <div class="enrollment-item-label">Group/Section</div>
                        <div class="enrollment-item-value"><?php echo htmlspecialchars($student['group']); ?></div>
                    </div>
                    <div class="enrollment-item">
                        <div class="enrollment-item-label">Enrollment Date</div>
                        <div class="enrollment-item-value"><?php echo date('d M, Y', strtotime($student['created_at'])); ?></div>
                    </div>
                    <div class="enrollment-item">
                        <div class="enrollment-item-label">Status</div>
                        <div class="enrollment-item-value">
                            <span class="badge-success">✓ <?php echo htmlspecialchars($student['status']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="progress-metrics">
                <div class="metric-card">
                    <div class="metric-icon blue"><i class="fas fa-calendar-check"></i></div>
                    <div class="metric-value"><?php echo isset($class_data['classes_attended']) ? number_format($class_data['classes_attended']) : 0; ?></div>
                    <div class="metric-label">Classes Attended</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon green"><i class="fas fa-chart-line"></i></div>
                    <div class="metric-value"><?php echo isset($marks_data['avg_marks']) ? number_format($marks_data['avg_marks'], 1) : '--'; ?>%</div>
                    <div class="metric-label">Average Marks</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon orange"><i class="fas fa-clock"></i></div>
                    <div class="metric-value"><?php echo $months; ?></div>
                    <div class="metric-label">Months Enrolled</div>
                </div>
            </div>

            <!-- Detailed Information -->
            <div class="detail-cards">
                <!-- Personal Info -->
                <div class="detail-card">
                    <h5><i class="fas fa-user"></i>Personal Information</h5>
                    <div class="detail-item">
                        <span class="detail-item-label">Full Name</span>
                        <span class="detail-item-value"><?php echo htmlspecialchars($student['full_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Email</span>
                        <span class="detail-item-value" style="font-size: 12px;"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Mobile</span>
                        <span class="detail-item-value"><?php echo htmlspecialchars($student['mobile']); ?></span>
                    </div>
                </div>

                <!-- Academic Standing -->
                <div class="detail-card">
                    <h5><i class="fas fa-medal"></i>Academic Standing</h5>
                    <div class="detail-item">
                        <span class="detail-item-label">Monthly Fee</span>
                        <span class="detail-item-value">৳<?php echo number_format($student['monthly_fee'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Enrollment Duration</span>
                        <span class="detail-item-value"><?php echo $months > 0 ? ($months >= 12 ? round($months/12, 1) . " years" : $months . " months") : "Recently enrolled"; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Status</span>
                        <span class="badge-success">✓ Active</span>
                    </div>
                </div>

                <!-- Performance Overview -->
                <div class="detail-card">
                    <h5><i class="fas fa-chart-bar"></i>Performance Overview</h5>
                    <div class="detail-item">
                        <span class="detail-item-label">Classes Attended</span>
                        <span class="detail-item-value"><?php echo isset($class_data['classes_attended']) ? $class_data['classes_attended'] : 0; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Average Score</span>
                        <span class="detail-item-value"><?php echo isset($marks_data['avg_marks']) ? number_format($marks_data['avg_marks'], 1) . "%" : "N/A"; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-item-label">Overall Progress</span>
                        <div style="width: 100%;">
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo isset($marks_data['avg_marks']) && $marks_data['avg_marks'] > 0 ? min($marks_data['avg_marks'], 100) : 0; ?>%;"></div>
                            </div>
                            <small style="color: #666;"><?php echo isset($marks_data['avg_marks']) && $marks_data['avg_marks'] > 0 ? number_format($marks_data['avg_marks'], 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
