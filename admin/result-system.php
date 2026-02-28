<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin', 'teacher']);

// Get classes (Class 6 to 10 only)
$classes = mysqli_query($conn, "SELECT * FROM classes WHERE class_name IN ('Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10') ORDER BY class_name, section");

// Get exam types
$exam_types = mysqli_query($conn, "SELECT * FROM exam_types ORDER BY exam_name");

// Get current term/session
$current_year = date('Y');
$session = $current_year . '-' . ($current_year + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result System - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        /* Bangladesh Grading System Card */
        .grade-card {
            background: linear-gradient(135deg, #006a4e, #f42a41);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .grade-card::before {
            content: '🇧🇩';
            position: absolute;
            right: 20px;
            bottom: 10px;
            font-size: 60px;
            opacity: 0.2;
        }

        .grade-table {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .grade-table table {
            margin-bottom: 0;
            color: white;
        }

        .grade-table td {
            border-color: rgba(255,255,255,0.2);
        }

        /* Filter Section */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Marks Entry Table */
        .marks-table {
            width: 100%;
            border-collapse: collapse;
        }

        .marks-table th {
            background: #f8f9fa;
            padding: 12px;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }

        .marks-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .marks-input {
            width: 100px;
            padding: 5px 10px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            text-align: center;
        }

        .marks-input:focus {
            border-color: #667eea;
            outline: none;
        }

        .total-marks {
            font-weight: 600;
            color: #2a5298;
        }

        .gpa-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        .gpa-aplus { background: #006a4e; color: white; }
        .gpa-a { background: #27ae60; color: white; }
        .gpa-aminus { background: #f39c12; color: white; }
        .gpa-b { background: #e67e22; color: white; }
        .gpa-c { background: #e74c3c; color: white; }
        .gpa-d { background: #c0392b; color: white; }
        .gpa-f { background: #7f8c8d; color: white; }

        /* Result Card */
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }

        .result-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .result-header h3 {
            color: #006a4e;
            font-weight: 700;
        }

        .result-header h4 {
            color: #f42a41;
        }

        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .grade-point {
            font-size: 24px;
            font-weight: 700;
            color: #2a5298;
        }

        /* Buttons */
        .btn-action {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-success {
            background: #006a4e;
            border: none;
        }

        .btn-danger {
            background: #f42a41;
            border: none;
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
                <a href="class-management.php" class="menu-item">
                    <i class="fas fa-school"></i>
                    <span>Class & Subjects</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="result-system.php" class="menu-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Result System</span>
                </a>
                <a href="fees-management.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Fees Management</span>
                </a>
                <a href="sms-system.php" class="menu-item">
                    <i class="fas fa-sms"></i>
                    <span>SMS System</span>
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
                    <h4>Result Management System</h4>
                </div>
                <div class="user-info">
                    <span class="badge bg-success me-3">Session: <?php echo $session; ?></span>
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

            <!-- Bangladesh Grading System Card -->
            <div class="grade-card">
                <h5><i class="fas fa-star me-2"></i>Bangladesh National Curriculum Grading System (Class 6-10)</h5>
                <div class="grade-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Marks Range</th>
                                <th>Letter Grade</th>
                                <th>Grade Point</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>80-100</td><td><strong>A+</strong></td><td>5.00</td><td>Excellent</td></tr>
                            <tr><td>70-79</td><td><strong>A</strong></td><td>4.00</td><td>Good</td></tr>
                            <tr><td>60-69</td><td><strong>A-</strong></td><td>3.50</td><td>Satisfactory</td></tr>
                            <tr><td>50-59</td><td><strong>B</strong></td><td>3.00</td><td>Average</td></tr>
                            <tr><td>40-49</td><td><strong>C</strong></td><td>2.00</td><td>Pass</td></tr>
                            <tr><td>33-39</td><td><strong>D</strong></td><td>1.00</td><td>Marginal</td></tr>
                            <tr><td>0-32</td><td><strong>F</strong></td><td>0.00</td><td>Fail</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pills-entry-tab" data-bs-toggle="pill" data-bs-target="#pills-entry" type="button">
                                <i class="fas fa-pen me-2"></i>Marks Entry
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-results-tab" data-bs-toggle="pill" data-bs-target="#pills-results" type="button">
                                <i class="fas fa-file-alt me-2"></i>View Results
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-grade-sheet-tab" data-bs-toggle="pill" data-bs-target="#pills-grade-sheet" type="button">
                                <i class="fas fa-table me-2"></i>Grade Sheet
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-tabulation-tab" data-bs-toggle="pill" data-bs-target="#pills-tabulation" type="button">
                                <i class="fas fa-chart-line me-2"></i>Tabulation Sheet
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="tab-content" id="pills-tabContent">
                <!-- Marks Entry Tab -->
                <div class="tab-pane fade show active" id="pills-entry" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-pen me-2"></i>Marks Entry Form</h5>
                        </div>

                        <!-- Selection Form -->
                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Select Class</label>
                                    <select class="form-select" id="entry_class" required>
                                        <option value="">Choose Class</option>
                                        <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Exam Type</label>
                                    <select class="form-select" id="entry_exam" required>
                                        <option value="">Choose Exam</option>
                                        <?php 
                                        mysqli_data_seek($exam_types, 0);
                                        while($exam = mysqli_fetch_assoc($exam_types)): 
                                        ?>
                                            <option value="<?php echo $exam['id']; ?>">
                                                <?php echo $exam['exam_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select" id="entry_subject" required>
                                        <option value="">First select class</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-action w-100" onclick="loadMarksEntry()">
                                        <i class="fas fa-search me-2"></i>Load Students
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Marks Entry Table -->
                        <div id="marks-entry-container">
                            <div class="text-center py-5">
                                <i class="fas fa-arrow-up fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Select class, exam and subject to enter marks</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- View Results Tab -->
                <div class="tab-pane fade" id="pills-results" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-file-alt me-2"></i>Student Results</h5>
                            <button class="btn btn-success btn-sm" onclick="printResult()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                        </div>

                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Search Student</label>
                                    <select class="form-select select2" id="result_student">
                                        <option value="">Select Student</option>
                                        <?php 
                                        $students = mysqli_query($conn, "SELECT s.*, c.class_name 
                                                                         FROM students s 
                                                                         JOIN classes c ON s.class_id = c.id 
                                                                         WHERE s.status = 1 
                                                                         ORDER BY s.first_name");
                                        while($student = mysqli_fetch_assoc($students)): 
                                        ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo $student['first_name'] . ' ' . $student['last_name']; ?> 
                                                (<?php echo $student['class_name']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Exam Type</label>
                                    <select class="form-select" id="result_exam">
                                        <option value="">All Exams</option>
                                        <?php 
                                        mysqli_data_seek($exam_types, 0);
                                        while($exam = mysqli_fetch_assoc($exam_types)): 
                                        ?>
                                            <option value="<?php echo $exam['id']; ?>">
                                                <?php echo $exam['exam_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-action w-100" onclick="loadStudentResult()">
                                        <i class="fas fa-search me-2"></i>View Result
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="result-container">
                            <!-- Result will be displayed here -->
                        </div>
                    </div>
                </div>

                <!-- Grade Sheet Tab -->
                <div class="tab-pane fade" id="pills-grade-sheet" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-table me-2"></i>Class Grade Sheet</h5>
                        </div>

                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Select Class</label>
                                    <select class="form-select" id="grade_class">
                                        <option value="">Choose Class</option>
                                        <?php 
                                        mysqli_data_seek($classes, 0);
                                        while($class = mysqli_fetch_assoc($classes)): 
                                        ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Exam Type</label>
                                    <select class="form-select" id="grade_exam">
                                        <option value="">Choose Exam</option>
                                        <?php 
                                        mysqli_data_seek($exam_types, 0);
                                        while($exam = mysqli_fetch_assoc($exam_types)): 
                                        ?>
                                            <option value="<?php echo $exam['id']; ?>">
                                                <?php echo $exam['exam_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-action w-100" onclick="loadGradeSheet()">
                                        <i class="fas fa-table me-2"></i>Generate Grade Sheet
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="grade-sheet-container">
                            <!-- Grade sheet will be displayed here -->
                        </div>
                    </div>
                </div>

                <!-- Tabulation Sheet Tab -->
                <div class="tab-pane fade" id="pills-tabulation" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line me-2"></i>Tabulation Sheet</h5>
                            <div>
                                <button class="btn btn-outline-primary btn-sm me-2" onclick="exportTabulation()">
                                    <i class="fas fa-file-excel me-2"></i>Export
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="printTabulation()">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        </div>

                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Select Class</label>
                                    <select class="form-select" id="tab_class">
                                        <option value="">Choose Class</option>
                                        <?php 
                                        mysqli_data_seek($classes, 0);
                                        while($class = mysqli_fetch_assoc($classes)): 
                                        ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Exam Type</label>
                                    <select class="form-select" id="tab_exam">
                                        <option value="">Choose Exam</option>
                                        <?php 
                                        mysqli_data_seek($exam_types, 0);
                                        while($exam = mysqli_fetch_assoc($exam_types)): 
                                        ?>
                                            <option value="<?php echo $exam['id']; ?>">
                                                <?php echo $exam['exam_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-action w-100" onclick="loadTabulationSheet()">
                                        <i class="fas fa-chart-line me-2"></i>Generate Tabulation
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="tabulation-container">
                            <!-- Tabulation sheet will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Search student...",
                allowClear: true
            });
        });

        // Load subjects based on class selection
        $('#entry_class').change(function() {
            var class_id = $(this).val();
            if(class_id) {
                $.ajax({
                    url: 'get-subjects-by-class.php',
                    type: 'POST',
                    data: {class_id: class_id},
                    success: function(data) {
                        $('#entry_subject').html(data);
                    }
                });
            }
        });

        // Load marks entry form
        function loadMarksEntry() {
            var class_id = $('#entry_class').val();
            var exam_id = $('#entry_exam').val();
            var subject_id = $('#entry_subject').val();
            
            if(!class_id || !exam_id || !subject_id) {
                alert('Please select all fields');
                return;
            }
            
            $.ajax({
                url: 'get-marks-entry.php',
                type: 'POST',
                data: {
                    class_id: class_id,
                    exam_id: exam_id,
                    subject_id: subject_id
                },
                success: function(data) {
                    $('#marks-entry-container').html(data);
                }
            });
        }

        // Save marks
        function saveMarks() {
            var marks = [];
            var exam_id = $('#entry_exam').val();
            var subject_id = $('#entry_subject').val();
            
            $('.marks-row').each(function() {
                var student_id = $(this).data('student-id');
                var marks_obtained = $(this).find('.marks-input').val();
                
                if(marks_obtained !== '') {
                    marks.push({
                        student_id: student_id,
                        marks: marks_obtained
                    });
                }
            });
            
            if(marks.length === 0) {
                alert('Please enter marks for at least one student');
                return;
            }
            
            $.ajax({
                url: 'save-marks.php',
                type: 'POST',
                data: {
                    exam_id: exam_id,
                    subject_id: subject_id,
                    marks: marks
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('Marks saved successfully!');
                        loadMarksEntry(); // Reload to show updated data
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }

        // Calculate GPA from marks
        function calculateGPA(marks) {
            if(marks >= 80) return {grade: 'A+', point: 5.00};
            else if(marks >= 70) return {grade: 'A', point: 4.00};
            else if(marks >= 60) return {grade: 'A-', point: 3.50};
            else if(marks >= 50) return {grade: 'B', point: 3.00};
            else if(marks >= 40) return {grade: 'C', point: 2.00};
            else if(marks >= 33) return {grade: 'D', point: 1.00};
            else return {grade: 'F', point: 0.00};
        }

        // Load student result
        function loadStudentResult() {
            var student_id = $('#result_student').val();
            var exam_id = $('#result_exam').val();
            
            if(!student_id) {
                alert('Please select a student');
                return;
            }
            
            $.ajax({
                url: 'view-student-result.php',
                type: 'POST',
                data: {
                    student_id: student_id,
                    exam_id: exam_id
                },
                success: function(data) {
                    $('#result-container').html(data);
                }
            });
        }

        // Load grade sheet
        function loadGradeSheet() {
            var class_id = $('#grade_class').val();
            var exam_id = $('#grade_exam').val();
            
            if(!class_id || !exam_id) {
                alert('Please select class and exam');
                return;
            }
            
            $.ajax({
                url: 'generate-grade-sheet.php',
                type: 'POST',
                data: {
                    class_id: class_id,
                    exam_id: exam_id
                },
                success: function(data) {
                    $('#grade-sheet-container').html(data);
                }
            });
        }

        // Load tabulation sheet
        function loadTabulationSheet() {
            var class_id = $('#tab_class').val();
            var exam_id = $('#tab_exam').val();
            
            if(!class_id || !exam_id) {
                alert('Please select class and exam');
                return;
            }
            
            $.ajax({
                url: 'generate-tabulation.php',
                type: 'POST',
                data: {
                    class_id: class_id,
                    exam_id: exam_id
                },
                success: function(data) {
                    $('#tabulation-container').html(data);
                }
            });
        }

        // Print functions
        function printResult() {
            var printContent = document.getElementById('result-container').innerHTML;
            var originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }

        function printTabulation() {
            var printContent = document.getElementById('tabulation-container').innerHTML;
            var originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }

        function exportTabulation() {
            // This would export to Excel
            alert('Export to Excel functionality would be implemented here');
        }
    </script>
</body>
</html>