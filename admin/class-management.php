<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin']);

// Handle Delete Class
if(isset($_GET['delete_class']) && is_numeric($_GET['delete_class'])) {
    $class_id = $_GET['delete_class'];
    
    // Check if class has students
    $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE class_id = $class_id");
    $students = mysqli_fetch_assoc($check);
    
    if($students['total'] > 0) {
        $_SESSION['error'] = "Cannot delete class: $students[total] students are enrolled in this class!";
    } else {
        $query = "DELETE FROM classes WHERE id = $class_id";
        if(mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Class deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting class!";
        }
    }
    header("Location: class-management.php");
    exit();
}

// Handle Delete Subject
if(isset($_GET['delete_subject']) && is_numeric($_GET['delete_subject'])) {
    $subject_id = $_GET['delete_subject'];
    
    $query = "DELETE FROM subjects WHERE id = $subject_id";
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Subject deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting subject!";
    }
    header("Location: class-management.php");
    exit();
}

// Get all classes
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name, section");

// Get all subjects with class info
$subjects = mysqli_query($conn, "SELECT s.*, c.class_name 
                                 FROM subjects s 
                                 JOIN classes c ON s.class_id = c.id 
                                 ORDER BY c.class_name, s.subject_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class & Subjects Management - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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

        .btn-add {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e0e0e0;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .table tbody td {
            vertical-align: middle;
            color: #666;
            font-size: 14px;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            margin: 0 3px;
            color: white;
            font-size: 12px;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            color: white;
        }

        .btn-edit { background: #f39c12; }
        .btn-delete { background: #e74c3c; }

        .class-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 13px;
            display: inline-block;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 30px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-control, .form-select {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: none;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* Stats Cards */
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .stats-card h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
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
                <a href="class-management.php" class="menu-item active">
                    <i class="fas fa-school"></i>
                    <span>Class & Subjects</span>
                </a>
                <a href="class-routine.php" class="menu-item">
                    <i class="fas fa-calendar-week"></i>
                    <span>Class Routine</span>
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
                    <h4>Class & Subjects Management</h4>
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

            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h2><?php echo mysqli_num_rows($classes); ?></h2>
                        <p>Total Classes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <h2><?php echo mysqli_num_rows($subjects); ?></h2>
                        <p>Total Subjects</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #27ae60, #229954);">
                        <?php 
                        $avg_subjects = mysqli_query($conn, "SELECT AVG(subject_count) as avg FROM 
                                                              (SELECT class_id, COUNT(*) as subject_count 
                                                               FROM subjects GROUP BY class_id) as t");
                        $avg = mysqli_fetch_assoc($avg_subjects);
                        ?>
                        <h2><?php echo round($avg['avg'] ?? 0); ?></h2>
                        <p>Avg Subjects/Class</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                        <?php 
                        $total_sections = mysqli_query($conn, "SELECT COUNT(DISTINCT CONCAT(class_name, section)) as total FROM classes");
                        $sections = mysqli_fetch_assoc($total_sections);
                        ?>
                        <h2><?php echo $sections['total']; ?></h2>
                        <p>Total Sections</p>
                    </div>
                </div>
            </div>

            <!-- Classes Management Card -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-school me-2"></i>Manage Classes</h5>
                    <button class="btn btn-add" onclick="openClassModal()">
                        <i class="fas fa-plus me-2"></i>Add New Class
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="classesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Class Name</th>
                                <th>Section</th>
                                <th>Total Students</th>
                                <th>Total Subjects</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            $sno = 1;
                            while($class = mysqli_fetch_assoc($classes)): 
                                // Get student count
                                $student_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE class_id = {$class['id']}");
                                $students = mysqli_fetch_assoc($student_count);
                                
                                // Get subject count
                                $subject_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM subjects WHERE class_id = {$class['id']}");
                                $subjects_count = mysqli_fetch_assoc($subject_count);
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td><span class="class-badge"><?php echo $class['class_name']; ?></span></td>
                                <td>Section <?php echo $class['section']; ?></td>
                                <td><?php echo $students['total']; ?></td>
                                <td><?php echo $subjects_count['total']; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($class['created_at'])); ?></td>
                                <td>
                                    <a href="javascript:void(0)" onclick="editClass(<?php echo $class['id']; ?>, '<?php echo $class['class_name']; ?>', '<?php echo $class['section']; ?>')" 
                                       class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete_class=<?php echo $class['id']; ?>" 
                                       class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this class?\nThis will affect all related data!')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subjects Management Card -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-book me-2"></i>Manage Subjects</h5>
                    <button class="btn btn-add" onclick="openSubjectModal()">
                        <i class="fas fa-plus me-2"></i>Add New Subject
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="subjectsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject Name</th>
                                <th>Subject Code</th>
                                <th>Class</th>
                                <th>Teachers Assigned</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($subjects, 0);
                            $sno = 1;
                            while($subject = mysqli_fetch_assoc($subjects)): 
                                // Get teacher count for this subject
                                $teacher_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM teacher_subjects WHERE subject_id = {$subject['id']}");
                                $teachers = mysqli_fetch_assoc($teacher_count);
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td><?php echo $subject['subject_name']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $subject['subject_code']; ?></span></td>
                                <td><span class="class-badge"><?php echo $subject['class_name']; ?></span></td>
                                <td><?php echo $teachers['total']; ?> Teacher(s)</td>
                                <td><?php echo date('d-m-Y', strtotime($subject['created_at'])); ?></td>
                                <td>
                                    <a href="javascript:void(0)" onclick="editSubject(<?php echo $subject['id']; ?>)" 
                                       class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete_subject=<?php echo $subject['id']; ?>" 
                                       class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this subject?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Class Modal -->
    <div class="modal fade" id="classModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="classModalTitle">Add New Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="classForm" method="POST" action="class-process.php">
                    <div class="modal-body">
                        <input type="hidden" name="class_id" id="class_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Class Name *</label>
                            <input type="text" class="form-control" name="class_name" id="class_name" 
                                   placeholder="e.g., Class 9, Class 10" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Section *</label>
                            <select class="form-select" name="section" id="section" required>
                                <option value="">Select Section</option>
                                <option value="A">Section A</option>
                                <option value="B">Section B</option>
                                <option value="C">Section C</option>
                                <option value="D">Section D</option>
                                <option value="E">Section E</option>
                                <option value="Science">Science</option>
                                <option value="Commerce">Commerce</option>
                                <option value="Arts">Arts</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_class" class="btn btn-save">Save Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Subject Modal -->
    <div class="modal fade" id="subjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectModalTitle">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="subjectForm" method="POST" action="class-process.php">
                    <div class="modal-body">
                        <input type="hidden" name="subject_id" id="subject_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Subject Name *</label>
                            <input type="text" class="form-control" name="subject_name" id="subject_name" 
                                   placeholder="e.g., Mathematics, Physics" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject Code *</label>
                            <input type="text" class="form-control" name="subject_code" id="subject_code" 
                                   placeholder="e.g., MATH101, PHY101" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Class *</label>
                            <select class="form-select" name="class_id" id="subject_class_id" required>
                                <option value="">Select Class</option>
                                <?php 
                                mysqli_data_seek($classes, 0);
                                while($class = mysqli_fetch_assoc($classes)): 
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo $class['class_name'] . ' - Section ' . $class['section']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_subject" class="btn btn-save">Save Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#classesTable').DataTable({
                "pageLength": 5,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false
            });
            
            $('#subjectsTable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false
            });
        });

        // Class Modal Functions
        function openClassModal() {
            document.getElementById('classForm').reset();
            document.getElementById('class_id').value = '';
            document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Class';
            new bootstrap.Modal(document.getElementById('classModal')).show();
        }

        function editClass(id, name, section) {
            document.getElementById('class_id').value = id;
            document.getElementById('class_name').value = name;
            document.getElementById('section').value = section;
            document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Class';
            new bootstrap.Modal(document.getElementById('classModal')).show();
        }

        // Subject Modal Functions
        function openSubjectModal() {
            document.getElementById('subjectForm').reset();
            document.getElementById('subject_id').value = '';
            document.getElementById('subjectModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Subject';
            new bootstrap.Modal(document.getElementById('subjectModal')).show();
        }

        function editSubject(id) {
            $.ajax({
                url: 'get-subject.php',
                type: 'POST',
                data: {id: id},
                dataType: 'json',
                success: function(data) {
                    document.getElementById('subject_id').value = data.id;
                    document.getElementById('subject_name').value = data.subject_name;
                    document.getElementById('subject_code').value = data.subject_code;
                    document.getElementById('subject_class_id').value = data.class_id;
                    document.getElementById('subjectModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Subject';
                    new bootstrap.Modal(document.getElementById('subjectModal')).show();
                }
            });
        }
    </script>
</body>
</html>