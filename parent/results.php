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
$student_name = $_SESSION['student_name'] ?? '';
$student_mobile = $_SESSION['student_mobile'] ?? '';

$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$student_ids_list = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// Check if exam_types table exists
$examTypesTableExists = false;
$examTypesCheck = mysqli_query($conn, "SHOW TABLES LIKE 'exam_types'");
if ($examTypesCheck && mysqli_num_rows($examTypesCheck) > 0) {
    $examTypesTableExists = true;
}

// Check if results table has percentage column
$resultsPercentageExists = false;
$resultsPercentageCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'percentage'");
if ($resultsPercentageCheck && mysqli_num_rows($resultsPercentageCheck) > 0) {
    $resultsPercentageExists = true;
}

// Check if results table has marks_obtained and total_marks columns
$resultsMarksColumns = false;
$marksObtainedCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'marks_obtained'");
$totalMarksCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'total_marks'");
$resultsMarksColumns = ($marksObtainedCheck && mysqli_num_rows($marksObtainedCheck) > 0) && 
                        ($totalMarksCheck && mysqli_num_rows($totalMarksCheck) > 0);

$student_id = 0;
if (!empty($student_ids)) {
    $student_id = (int) $student_ids[0];
}

// Get all results for this student
// Build column list based on what exists
$resultsCols = "r.id, s.subject_name";

if ($resultsMarksColumns) {
    $resultsCols .= ", r.marks_obtained, r.total_marks";
} else {
    $resultsCols .= ", NULL as marks_obtained, NULL as total_marks";
}

if ($resultsPercentageExists) {
    $resultsCols .= ", r.percentage";
} else {
    $resultsCols .= ", NULL as percentage";
}

if ($examTypesTableExists) {
    $resultsCols .= ", e.exam_name as exam_type";
    $examJoin = "LEFT JOIN exam_types e ON r.exam_type_id = e.id";
} else {
    $resultsCols .= ", NULL as exam_type";
    $examJoin = "";
}

$results_query = "SELECT $resultsCols
                  FROM results r
                  LEFT JOIN subjects s ON r.subject_id = s.id
                  $examJoin
                  WHERE r.student_id IN ($student_ids_list)
                  ORDER BY r.id DESC";

$results_result = mysqli_query($conn, $results_query);

// Calculate overall statistics
if ($resultsPercentageExists) {
    $stats_query = "SELECT 
                        AVG(percentage) as avg_percentage,
                        MAX(percentage) as max_percentage,
                        MIN(percentage) as min_percentage,
                        COUNT(*) as total_exams
                    FROM results
                    WHERE student_id IN ($student_ids_list)";
} else if ($resultsMarksColumns) {
    $stats_query = "SELECT 
                        AVG(CASE WHEN total_marks > 0 THEN (marks_obtained / total_marks * 100) ELSE 0 END) as avg_percentage,
                        MAX(CASE WHEN total_marks > 0 THEN (marks_obtained / total_marks * 100) ELSE 0 END) as max_percentage,
                        MIN(CASE WHEN total_marks > 0 THEN (marks_obtained / total_marks * 100) ELSE 0 END) as min_percentage,
                        COUNT(*) as total_exams
                    FROM results
                    WHERE student_id IN ($student_ids_list)";
} else {
    $stats_query = "SELECT 
                        NULL as avg_percentage,
                        NULL as max_percentage,
                        NULL as min_percentage,
                        COUNT(*) as total_exams
                    FROM results
                    WHERE student_id IN ($student_ids_list)";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results & Grades - Parent Portal</title>
    
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

        /* Stats Grid */
        .results-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card.avg {
            border-top: 5px solid #2196f3;
        }

        .stat-card.max {
            border-top: 5px solid #4caf50;
        }

        .stat-card.min {
            border-top: 5px solid #ff9800;
        }

        .stat-value {
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

        /* Results Table */
        .results-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .results-container-header {
            padding: 25px;
            border-bottom: 2px solid #e2e8f0;
        }

        .results-container h4 {
            font-weight: 700;
            color: #333;
            margin: 0;
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

        .subject-name {
            font-weight: 600;
            color: #333;
        }

        .exam-type {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .marks-badge {
            font-weight: 600;
            color: #333;
        }

        .percentage-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        .percentage-badge.high {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .percentage-badge.medium {
            background: #fff9c4;
            color: #f57f17;
        }

        .percentage-badge.low {
            background: #ffcccc;
            color: #c62828;
        }

        .grade-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }

        .grade-a {
            background: #c8e6c9;
            color: #1b5e20;
        }

        .grade-b {
            background: #bbdefb;
            color: #0d47a1;
        }

        .grade-c {
            background: #ffe0b2;
            color: #e65100;
        }

        .grade-f {
            background: #ffcccc;
            color: #b71c1c;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-data i {
            font-size: 64px;
            opacity: 0.2;
            display: block;
            margin-bottom: 15px;
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

            .results-stats {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 13px;
            }

            .table th,
            .table td {
                padding: 10px;
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
                    <a href="results.php" class="active">
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
                <h2><i class="fas fa-chart-bar me-3" style="color: #667eea;"></i>Results & Grades</h2>
                <p>View <?php echo htmlspecialchars($student_name); ?>'s exam results and grades</p>
            </div>

            <!-- Stats -->
            <?php if($stats['total_exams'] > 0): ?>
            <div class="results-stats">
                <div class="stat-card avg">
                    <span class="stat-value"><?php echo number_format($stats['avg_percentage'], 2); ?>%</span>
                    <div class="stat-label">Average Percentage</div>
                </div>
                <div class="stat-card max">
                    <span class="stat-value"><?php echo number_format($stats['max_percentage'], 2); ?>%</span>
                    <div class="stat-label">Highest Score</div>
                </div>
                <div class="stat-card min">
                    <span class="stat-value"><?php echo number_format($stats['min_percentage'], 2); ?>%</span>
                    <div class="stat-label">Lowest Score</div>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $stats['total_exams']; ?></span>
                    <div class="stat-label">Total Exams</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Results Table -->
            <div class="results-container">
                <div class="results-container-header">
                    <h4><i class="fas fa-list me-2"></i>Exam Results</h4>
                </div>

                <?php if(mysqli_num_rows($results_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Exam Type</th>
                                <th>Date</th>
                                <th>Marks Obtained</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while($row = mysqli_fetch_assoc($results_result)) {
                                // Calculate or get percentage
                                if ($resultsPercentageExists) {
                                    $percentage = floatval($row['percentage'] ?? 0);
                                } else if ($resultsMarksColumns) {
                                    $percentage = ($row['total_marks'] > 0) 
                                        ? floatval($row['marks_obtained']) / floatval($row['total_marks']) * 100 
                                        : 0;
                                } else {
                                    $percentage = null;
                                }
                                
                                $grade = $row['grade'] ?? 'N/A';
                                
                                // Determine percentage badge color
                                if($percentage !== null && $percentage >= 80) {
                                    $pct_class = 'high';
                                } elseif($percentage !== null && $percentage >= 60) {
                                    $pct_class = 'medium';
                                } else {
                                    $pct_class = 'low';
                                }
                                
                                // Determine grade color
                                $grade_class = 'grade-' . strtolower($grade);
                            ?>
                            <tr>
                                <td><span class="subject-name"><?php echo htmlspecialchars($row['subject_name'] ?? 'N/A'); ?></span></td>
                                <td><span class="exam-type"><?php echo htmlspecialchars($row['exam_type'] ?? 'Regular'); ?></span></td>
                                <td><?php echo isset($row['exam_date']) ? date('d M, Y', strtotime($row['exam_date'])) : 'N/A'; ?></td>
                                <td class="marks-badge">
                                    <?php 
                                        if ($resultsMarksColumns && isset($row['marks_obtained']) && isset($row['total_marks'])) {
                                            echo htmlspecialchars($row['marks_obtained'] . "/" . $row['total_marks']);
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="percentage-badge <?php echo $pct_class; ?>">
                                        <?php echo $percentage !== null ? number_format($percentage, 2) : 'N/A'; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="grade-badge <?php echo $grade_class; ?>">
                                        <?php echo htmlspecialchars($grade); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <p>No exam results found yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
