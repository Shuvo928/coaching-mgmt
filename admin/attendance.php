<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin', 'teacher']);

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// Get all classes for filter
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");

// Get today's attendance summary
$summary_query = "SELECT 
                    COUNT(DISTINCT student_id) as total_present,
                    (SELECT COUNT(*) FROM students WHERE status = 1) as total_students
                  FROM attendance 
                  WHERE date = '$date'";
$summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_query));

// Get recent attendance records
$recent_query = "SELECT a.*, s.name AS student_name, c.class_name 
                 FROM attendance a
                 JOIN students s ON a.student_id = s.id
                 JOIN classes c ON s.class_id = c.id
                 WHERE a.date = '$date'
                 ORDER BY a.id DESC
                 LIMIT 10";
$recent = mysqli_query($conn, $recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
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

        /* Sidebar Styles */
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

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.present { background: #e8f5e9; color: #2e7d32; }
        .stat-icon.absent { background: #ffebee; color: #c62828; }
        .stat-icon.percentage { background: #e3f2fd; color: #1565c0; }

        /* Filter Section */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Attendance Table */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: #f8f9fa;
            padding: 12px;
            font-weight: 600;
            color: #333;
        }

        .attendance-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-photo {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .attendance-status {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status-option {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .status-option input[type="radio"] {
            display: none;
        }

        .status-option span {
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .status-option.present span {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-option.absent span {
            background: #ffebee;
            color: #c62828;
        }

        .status-option.late span {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-option input[type="radio"]:checked + span {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.present { background: #e8f5e9; color: #2e7d32; }
        .status-badge.absent { background: #ffebee; color: #c62828; }
        .status-badge.late { background: #fff3e0; color: #ef6c00; }

        .btn-save-attendance {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-save-attendance:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* QR Code Scanner */
        .qr-scanner {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            background: white;
            border: 3px dashed #667eea;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .qr-placeholder i {
            font-size: 50px;
            color: #667eea;
            opacity: 0.5;
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
                <a href="dashboard.php" class="menu-item">
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
               
            <a href="admission-management.php" class="menu-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Admissions</span>
                </a>
                <a href="attendance.php" class="menu-item active">
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
                    <h4>Attendance Management</h4>
                </div>
                <div class="user-info">
                    <i class="fas fa-bell text-muted"></i>
                    <i class="fas fa-envelope text-muted"></i>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['display_name']; ?>&background=2a5298&color=fff" alt="User" style="width: 35px; height: 35px; border-radius: 50%;">
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $summary['total_students'] ?? 0; ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="stat-icon present">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $summary['total_present'] ?? 0; ?></h3>
                        <p>Present Today</p>
                    </div>
                    <div class="stat-icon present">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <?php 
                        $absent = ($summary['total_students'] ?? 0) - ($summary['total_present'] ?? 0);
                        ?>
                        <h3><?php echo max(0, $absent); ?></h3>
                        <p>Absent Today</p>
                    </div>
                    <div class="stat-icon absent">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <?php 
                        $percentage = ($summary['total_students'] ?? 0) > 0 
                            ? round(($summary['total_present'] / $summary['total_students']) * 100, 1)
                            : 0;
                        ?>
                        <h3><?php echo $percentage; ?>%</h3>
                        <p>Attendance %</p>
                    </div>
                    <div class="stat-icon percentage">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pills-manual-tab" data-bs-toggle="pill" data-bs-target="#pills-manual" type="button">
                                <i class="fas fa-pen me-2"></i>Manual Attendance
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-qr-tab" data-bs-toggle="pill" data-bs-target="#pills-qr" type="button">
                                <i class="fas fa-qrcode me-2"></i>QR Scanner
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-reports-tab" data-bs-toggle="pill" data-bs-target="#pills-reports" type="button">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="tab-content" id="pills-tabContent">
                <!-- Manual Attendance Tab -->
                <div class="tab-pane fade show active" id="pills-manual" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-pen me-2"></i>Mark Attendance</h5>
                        </div>

                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Select Date</label>
                                    <input type="text" class="form-control datepicker" name="date" value="<?php echo $date; ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Select Class</label>
                                    <select class="form-select" name="class_id" onchange="this.form.submit()">
                                        <option value="">All Classes</option>
                                        <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['class_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-primary w-100" onclick="loadStudents()">
                                        <i class="fas fa-search me-2"></i>Load Students
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-success w-100" onclick="markAllPresent()">
                                        <i class="fas fa-check-circle me-2"></i>Mark All Present
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Students List for Attendance -->
                        <div id="students-attendance-list">
                            <!-- Load via AJAX -->
                            <div class="text-center py-5">
                                <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Select class and click "Load Students" to mark attendance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Scanner Tab -->
                <div class="tab-pane fade" id="pills-qr" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-qrcode me-2"></i>QR Code Attendance Scanner</h5>
                        </div>
                        
                        <div class="qr-scanner">
                            <div class="qr-placeholder">
                                <i class="fas fa-camera"></i>
                            </div>
                            <h5>Scan Student QR Code</h5>
                            <p class="text-muted mb-3">Place the QR code in front of the camera to mark attendance</p>
                            <button class="btn btn-primary" onclick="startScanner()">
                                <i class="fas fa-play me-2"></i>Start Camera
                            </button>
                            <button class="btn btn-secondary" onclick="stopScanner()">
                                <i class="fas fa-stop me-2"></i>Stop Camera
                            </button>
                            
                            <div class="mt-4" id="scan-result"></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="scan-history">
                                    <!-- Scan history will appear here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Reports Tab -->
                <div class="tab-pane fade" id="pills-reports" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Attendance Reports</h5>
                            <div>
                                <button class="btn btn-outline-primary me-2" onclick="exportReport()">
                                    <i class="fas fa-file-export me-2"></i>Export
                                </button>
                                <button class="btn btn-outline-success" onclick="printReport()">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        </div>

                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="text" class="form-control datepicker" id="from_date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="text" class="form-control datepicker" id="to_date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Class</label>
                                    <select class="form-select" id="report_class">
                                        <option value="">All Classes</option>
                                        <?php 
                                        mysqli_data_seek($classes, 0);
                                        while($class = mysqli_fetch_assoc($classes)): 
                                        ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo $class['class_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-primary w-100" onclick="generateReport()">
                                        <i class="fas fa-chart-line me-2"></i>Generate Report
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="report-container">
                            <!-- Report will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Attendance (<?php echo date('d-m-Y', strtotime($date)); ?>)</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($recent) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($recent)): ?>
                                <tr>
                                    <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo $row['student_id']; ?></td>
                                    <td><?php echo $row['student_name']; ?></td>
                                    <td><?php echo $row['class_name']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                        <p>No attendance records for today</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    
    <script>
        // Initialize DatePicker
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });

        // Load Students for Attendance
        function loadStudents() {
            var class_id = $('select[name="class_id"]').val();
            var date = $('input[name="date"]').val();
            
            if(!class_id) {
                alert('Please select a class');
                return;
            }
            
            $.ajax({
                url: 'get-students-attendance.php',
                type: 'POST',
                data: {class_id: class_id, date: date},
                success: function(data) {
                    $('#students-attendance-list').html(data);
                }
            });
        }

        // Save Attendance
        function saveAttendance() {
            var attendance = [];
            var date = $('input[name="date"]').val();
            
            $('.student-attendance-row').each(function() {
                var student_id = $(this).data('student-id');
                var status = $(this).find('input[type="radio"]:checked').val();
                
                if(status) {
                    attendance.push({
                        student_id: student_id,
                        status: status
                    });
                }
            });
            
            if(attendance.length === 0) {
                alert('Please mark attendance for at least one student');
                return;
            }
            
            $.ajax({
                url: 'save-attendance.php',
                type: 'POST',
                data: {
                    date: date,
                    attendance: attendance
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('Attendance saved successfully!');
                        location.reload();
                    } else {
                        alert('Error saving attendance: ' + response.message);
                    }
                }
            });
        }

        // Mark All Present
        function markAllPresent() {
            $('input[value="Present"]').prop('checked', true);
        }

        // QR Scanner Functions
        function startScanner() {
            // This would integrate with a QR scanner library like Instascan
            alert('QR Scanner would start here. In production, integrate with Instascan or similar library.');
        }

        function stopScanner() {
            alert('Scanner stopped');
        }

        // Report Functions
        function generateReport() {
            var from_date = $('#from_date').val();
            var to_date = $('#to_date').val();
            var class_id = $('#report_class').val();
            
            if(!from_date || !to_date) {
                alert('Please select date range');
                return;
            }
            
            $.ajax({
                url: 'attendance-report.php',
                type: 'POST',
                data: {
                    from_date: from_date,
                    to_date: to_date,
                    class_id: class_id
                },
                success: function(data) {
                    $('#report-container').html(data);
                }
            });
        }

        function exportReport() {
            var from_date = $('#from_date').val();
            var to_date = $('#to_date').val();
            var class_id = $('#report_class').val();
            
            window.location.href = 'export-attendance.php?from=' + from_date + '&to=' + to_date + '&class=' + class_id;
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>