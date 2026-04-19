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

// Get current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$student_id = $student_ids[0] ?? 0;
$student_ids_list = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// If no student rows were found through the parent mapping, fall back to old admission application logic.
if (empty($student_ids) && !empty($student_mobile)) {
    $student_query = "SELECT id FROM students WHERE phone = '$student_mobile' LIMIT 1";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);
    $student_id = $student['id'] ?? 0;
    $student_ids_list = $student_id > 0 ? (string) $student_id : '0';
}

// Get monthly attendance summary
$attendance_query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
                     FROM attendance 
                     WHERE YEAR(date) = $current_year 
                     AND MONTH(date) = $current_month 
                     AND student_id = $student_id";

$attendance_result = mysqli_query($conn, $attendance_query);
$summary = mysqli_fetch_assoc($attendance_result);

// Get daily attendance records
$daily_query = "SELECT date, status FROM attendance 
                WHERE YEAR(date) = $current_year 
                AND MONTH(date) = $current_month 
                AND student_id = $student_id
                ORDER BY date DESC";
$daily_result = mysqli_query($conn, $daily_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Parent Portal</title>
    
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

        /* Attendance Stats */
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .stat-box.present {
            border-left: 5px solid #4caf50;
        }

        .stat-box.absent {
            border-left: 5px solid #f44336;
        }

        .stat-box.late {
            border-left: 5px solid #ff9800;
        }

        .stat-box.total {
            border-left: 5px solid #2196f3;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            display: block;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

        /* Attendance Table */
        .attendance-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
        }

        .attendance-table-container h4 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f8f9fa;
        }

        .table th {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #333;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .badge-present {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .badge-absent {
            background: #ffcdd2;
            color: #c62828;
        }

        .badge-late {
            background: #ffe0b2;
            color: #e65100;
        }

        .date-format {
            font-weight: 600;
            color: #333;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .month-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .month-selector form {
            display: flex;
            gap: 10px;
        }

        .month-selector select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }

        .month-selector button {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .month-selector button:hover {
            background: #764ba2;
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

            .attendance-stats {
                grid-template-columns: 1fr 1fr;
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
                    <a href="attendance.php" class="active">
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
                <h2><i class="fas fa-calendar-check me-3" style="color: #667eea;"></i>Attendance</h2>
                <p>View <?php echo htmlspecialchars($student_name); ?>'s attendance records</p>
            </div>

            <!-- Attendance Stats -->
            <div class="attendance-stats">
                <div class="stat-box total">
                    <span class="stat-number"><?php echo $summary['total_days'] ?? 0; ?></span>
                    <div class="stat-label">Total Days</div>
                </div>
                <div class="stat-box present">
                    <span class="stat-number"><?php echo $summary['present_days'] ?? 0; ?></span>
                    <div class="stat-label">Days Present</div>
                </div>
                <div class="stat-box absent">
                    <span class="stat-number"><?php echo $summary['absent_days'] ?? 0; ?></span>
                    <div class="stat-label">Days Absent</div>
                </div>
                <div class="stat-box late">
                    <span class="stat-number"><?php echo $summary['late_days'] ?? 0; ?></span>
                    <div class="stat-label">Days Late</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="attendance-table-container">
                <h4><i class="fas fa-list me-2"></i>Attendance Details - <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h4>
                
                <!-- Month Selector -->
                <div class="month-selector">
                    <form method="GET" class="d-flex gap-2">
                        <select name="month" class="form-control" style="width: auto;">
                            <?php
                            for($i = 1; $i <= 12; $i++) {
                                $selected = ($i == $current_month) ? 'selected' : '';
                                echo "<option value='$i' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                            }
                            ?>
                        </select>
                        <select name="year" class="form-control" style="width: auto;">
                            <?php
                            for($y = date('Y') - 2; $y <= date('Y'); $y++) {
                                $selected = ($y == $current_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <?php if(mysqli_num_rows($daily_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while($row = mysqli_fetch_assoc($daily_result)) {
                                $date = new DateTime($row['date']);
                                $day_name = $date->format('l');
                                $formatted_date = $date->format('d M, Y');
                                $status = $row['status'];
                                
                                if($status == 'Present') {
                                    $badge_class = 'badge-present';
                                } elseif($status == 'Absent') {
                                    $badge_class = 'badge-absent';
                                } else {
                                    $badge_class = 'badge-late';
                                }
                            ?>
                            <tr>
                                <td class="date-format"><?php echo $formatted_date; ?></td>
                                <td><?php echo $day_name; ?></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <i class="fas fa-check-circle me-1"></i><?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                    <p>No attendance records found for this month</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
