<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is a teacher
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher_query = "SELECT t.* FROM teachers t WHERE t.user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);

if(mysqli_num_rows($teacher_result) == 0) {
    die("Teacher record not found. Please contact admin.");
}

$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

// Check if assigned_subjects column exists
$assignedSubjectsColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'assigned_subjects'");
$assignedSubjectsColumnExists = ($assignedSubjectsColumnCheck && mysqli_num_rows($assignedSubjectsColumnCheck) > 0);

// Check if class_id column exists in teacher_subjects table
$teacherSubjectsClassIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
$teacherSubjectsClassIdColumnExists = ($teacherSubjectsClassIdColumnCheck && mysqli_num_rows($teacherSubjectsClassIdColumnCheck) > 0);

// Get teacher's class routine (weekly schedule)
$routine_query = "SELECT cr.*, c.class_name, s.subject_name, s.subject_code
                  FROM class_routine cr
                  JOIN classes c ON cr.class_id = c.id
                  JOIN subjects s ON cr.subject_id = s.id
                  WHERE cr.teacher_id = '$teacher_id'
                  ORDER BY FIELD(cr.day,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cr.start_time";
$routine_result = mysqli_query($conn, $routine_query);

// Get teacher's subjects for result filtering
$subjects_query = "SELECT DISTINCT s.id, s.subject_name, s.subject_code, c.class_name
                  FROM teacher_subjects ts
                  JOIN subjects s ON ts.subject_id = s.id
                  JOIN classes c ON ts.class_id = c.id
                  WHERE ts.teacher_id = '$teacher_id'";
$subjects_result = mysqli_query($conn, $subjects_query);

// Get recent exam routines (upcoming exams)
$recent_results_query = "SELECT er.*, c.class_name, s.subject_name
                        FROM exam_routine er
                        JOIN classes c ON er.class_id = c.id
                        JOIN subjects s ON er.subject_id = s.id
                        WHERE er.subject_id IN (SELECT subject_id FROM teacher_subjects WHERE teacher_id = '$teacher_id')
                        ORDER BY er.exam_date DESC LIMIT 10";
$recent_results_result = mysqli_query($conn, $recent_results_query);

// Get students list for result entry
if ($teacherSubjectsClassIdColumnExists) {
    $students_query = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.class_name
                      FROM students s
                      JOIN classes c ON s.class_id = c.id
                      WHERE s.class_id IN (SELECT class_id FROM teacher_subjects WHERE teacher_id = '$teacher_id')
                      ORDER BY c.class_name, s.roll_number";
} else {
    $students_query = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.class_name
                      FROM students s
                      JOIN classes c ON s.class_id = c.id
                      WHERE s.class_id IN (SELECT class_id FROM subjects WHERE id IN (SELECT subject_id FROM teacher_subjects WHERE teacher_id = '$teacher_id'))
                      ORDER BY c.class_name, s.roll_number";
}
$students_result = mysqli_query($conn, $students_query);

// Handle result submission
if(isset($_POST['add_result'])) {
    $student_id = $_POST['student_id'];
    $exam_id = $_POST['exam_id'];
    $subject_id = $_POST['subject_id'];
    $marks_obtained = $_POST['marks_obtained'];
    $grade = $_POST['grade'];
    $status = ($marks_obtained >= 40) ? 'pass' : 'fail';
    $remarks = $_POST['remarks'];
    
    // Check if result already exists
    $check_query = "SELECT id FROM results WHERE student_id = '$student_id' AND exam_id = '$exam_id' AND subject_id = '$subject_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($check_result) > 0) {
        // Update existing result
        $update_query = "UPDATE results SET marks_obtained = '$marks_obtained', grade = '$grade', status = '$status', remarks = '$remarks' 
                         WHERE student_id = '$student_id' AND exam_id = '$exam_id' AND subject_id = '$subject_id'";
        mysqli_query($conn, $update_query);
        $success_msg = "Result updated successfully!";
    } else {
        // Insert new result
        $insert_query = "INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, grade, status, remarks) 
                         VALUES ('$student_id', '$exam_id', '$subject_id', '$marks_obtained', '$grade', '$status', '$remarks')";
        mysqli_query($conn, $insert_query);
        $success_msg = "Result added successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: #1a1c2e;
            color: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #fff;
        }

        .sidebar-header p {
            font-size: 13px;
            color: #a0a3bd;
            margin: 5px 0 0 0;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
        }

        .nav-item i {
            width: 22px;
            font-size: 16px;
            margin-right: 10px;
            color: #a0a3bd;
        }

        .nav-item a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item:hover i, .nav-item.active i {
            color: #fff;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .page-title h2 {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .page-title p {
            color: #666;
            font-size: 13px;
            margin: 5px 0 0 0;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-text {
            text-align: right;
        }

        .profile-text h6 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }

        .profile-text span {
            font-size: 12px;
            color: #666;
        }

        /* Cards */
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h5 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .card-header h5 i {
            color: #3b82f6;
            margin-right: 10px;
        }

        /* Routine Table */
        .routine-table {
            width: 100%;
            border-collapse: collapse;
        }

        .routine-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e5e7eb;
        }

        .routine-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .routine-table tr:hover {
            background: #f8fafc;
        }

        .day-badge {
            background: #e5e7eb;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Form Styles */
        .form-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
            outline: none;
        }

        .btn-primary {
            background: #3b82f6;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            color: white;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-success {
            background: #10b981;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        /* Alert */
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #1a1c2e;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
                z-index: 1000;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 20px;
            }
        }

        .text-muted {
            color: #6b7280;
            font-size: 12px;
        }

        .result-table {
            font-size: 13px;
        }

        .result-table th {
            background: #f8fafc;
        }

        .badge-pass {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }

        .badge-fail {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h3>
            <p><?php echo htmlspecialchars($teacher['email']); ?></p>
        </div>

        <div class="nav-menu">
            <div class="nav-item active">
                <a href="#dashboard"><i class="fas fa-home"></i> Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="#routine"><i class="fas fa-calendar-alt"></i> Class Routine</a>
            </div>
            <div class="nav-item">
                <a href="#results"><i class="fas fa-chart-bar"></i> Result Management</a>
            </div>
            <div class="nav-item">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </div>
            <div class="nav-item">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Teacher Dashboard</h2>
                <p><i class="far fa-calendar-alt me-1"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="profile-info">
                <div class="profile-text">
                    <h6><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h6>
                    <span>Teacher</span>
                </div>
                
            </div>
        </div>

        <!-- Success Message -->
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Preferred Subjects Section -->
        <?php if ($assignedSubjectsColumnExists): ?>
        <div class="card" id="preferred-subjects">
            <div class="card-header">
                <h5><i class="fas fa-book"></i> Preferred Subjects</h5>
            </div>
            <div class="card-body">
                <?php if(!empty(trim($teacher['assigned_subjects']))): ?>
                    <?php
                        $subjects = array_filter(array_map('trim', explode(',', $teacher['assigned_subjects'])));
                    ?>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars(implode(', ', $subjects)); ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-0">No preferred subjects assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Class Routine Section -->
        <div class="card" id="routine">
            <div class="card-header">
                <h5><i class="fas fa-calendar-alt"></i> My Class Routine</h5>
                <span class="badge bg-primary">Weekly Schedule</span>
            </div>
            
            <?php if(mysqli_num_rows($routine_result) > 0): ?>
                <div class="table-responsive">
                    <table class="routine-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_day = '';
                            while($routine = mysqli_fetch_assoc($routine_result)): 
                                $day = htmlspecialchars($routine['day']);
                            ?>
                                <tr>
                                    <td>
                                        <?php if($day != $current_day): ?>
                                            <span class="day-badge"><?php echo $day; ?></span>
                                            <?php $current_day = $day; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($routine['class_name']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($routine['subject_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($routine['subject_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($routine['start_time'])) . ' - ' . date('h:i A', strtotime($routine['end_time']))); ?></td>
                                    <td><?php echo htmlspecialchars($routine['room'] ?? 'TBA'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-4">No class routine found.</p>
            <?php endif; ?>
        </div>

        <!-- Result Management Section -->
        <div class="card" id="results">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> Result Management</h5>
                <button class="btn btn-success btn-sm" onclick="showResultForm()">
                    <i class="fas fa-plus me-1"></i> Add New Result
                </button>
            </div>

            <!-- Add Result Form (Hidden by default) -->
            <div id="resultForm" style="display: none;" class="form-section">
                <h6 class="mb-3">Add/Update Student Result</h6>
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Choose Student</option>
                                <?php 
                                mysqli_data_seek($students_result, 0);
                                while($student = mysqli_fetch_assoc($students_result)): 
                                ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['roll_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['class_name'] . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Exam Type</label>
                            <select name="exam_id" class="form-select" required>
                                <option value="">Select Exam</option>
                                <?php 
                                $exams_query = "SELECT * FROM exams ORDER BY exam_type";
                                $exams_result = mysqli_query($conn, $exams_query);
                                while($exam = mysqli_fetch_assoc($exams_result)): 
                                ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo ucfirst($exam['exam_type']) . ' - ' . $exam['exam_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php 
                                mysqli_data_seek($subjects_result, 0);
                                while($subject = mysqli_fetch_assoc($subjects_result)): 
                                ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo $subject['subject_name'] . ' (' . $subject['class_name'] . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-1 mb-3">
                            <label class="form-label">Marks</label>
                            <input type="number" name="marks_obtained" class="form-control" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-1 mb-3">
                            <label class="form-label">Grade</label>
                            <select name="grade" class="form-select" required>
                                <option value="A+">A+</option>
                                <option value="A">A</option>
                                <option value="A-">A-</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" name="add_result" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Results Table -->
            <h6 class="mb-3">Recent Results</h6>
            <div class="table-responsive">
                <table class="table result-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recent_results_result) > 0): ?>
                            <?php while($result = mysqli_fetch_assoc($recent_results_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $result['first_name'] . ' ' . $result['last_name']; ?></strong>
                                        <br><small class="text-muted">Roll: <?php echo $result['roll_number']; ?></small>
                                    </td>
                                    <td><?php echo $result['class_name']; ?></td>
                                    <td>
                                        <strong><?php echo ucfirst($result['exam_type']); ?></strong>
                                        <br><small><?php echo $result['exam_name']; ?></small>
                                    </td>
                                    <td><?php echo $result['subject_name']; ?></td>
                                    <td><strong><?php echo $result['marks_obtained']; ?></strong></td>
                                    <td><strong><?php echo $result['grade']; ?></strong></td>
                                    <td>
                                        <span class="badge-<?php echo $result['status']; ?>">
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editResult(<?php echo $result['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No results added yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function showResultForm() {
            var form = document.getElementById('resultForm');
            if(form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function editResult(resultId) {
            // Redirect to edit page or show edit form
            window.location.href = 'edit-result.php?id=' + resultId;
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('.nav-item a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                if(this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if(target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                        if(window.innerWidth <= 992) {
                            document.getElementById('sidebar').classList.remove('active');
                        }
                    }
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.result-table').DataTable({
                pageLength: 10,
                ordering: true,
                language: {
                    search: "Search results:"
                }
            });
        });
    </script>
</body>
</html>