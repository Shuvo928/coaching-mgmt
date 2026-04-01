<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();

// Only admin can access
checkRole(['admin']);

// Handle Delete Request
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = $_GET['delete'];
    
    // Get student photo to delete
    $photo_query = "SELECT photo FROM students WHERE id = $student_id";
    $photo_result = mysqli_query($conn, $photo_query);
    $student = mysqli_fetch_assoc($photo_result);
    
    if($student['photo'] && file_exists("../uploads/student-photos/".$student['photo'])) {
        unlink("../uploads/student-photos/".$student['photo']);
    }
    
    // Delete student (soft delete or hard delete)
    $query = "DELETE FROM students WHERE id = $student_id";
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Student deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting student!";
    }
    
    header("Location: student-management.php");
    exit();
}

// Handle Status Toggle
if(isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $student_id = $_GET['toggle'];
    $query = "UPDATE students SET status = NOT status WHERE id = $student_id";
    mysqli_query($conn, $query);
    header("Location: student-management.php");
    exit();
}

// Get all students with class info
$query = "SELECT s.*, c.class_name 
          FROM students s 
          LEFT JOIN classes c ON s.class_id = c.id 
          ORDER BY s.id DESC";
$students = mysqli_query($conn, $query);

// Get classes for filter
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - CoachingPro</title>
    
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

        /* Sidebar Styles (same as dashboard) */
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
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
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

        .student-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            margin: 0 3px;
            color: white;
            font-size: 12px;
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            color: white;
        }

        .btn-view { background: #2a5298; }
        .btn-edit { background: #f39c12; }
        .btn-delete { background: #e74c3c; }
        .btn-toggle { background: #27ae60; }
        .btn-id-card { background: #9b59b6; }
        .btn-admit-card { background: #3498db; }

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

        /* Alert Messages */
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-box input {
            padding-left: 45px;
            height: 50px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            width: 300px;
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
                <a href="student-management.php" class="menu-item active">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                </a>
                <a href="teacher-management.php" class="menu-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
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
                    <h4>Student Management</h4>
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

            <!-- Content Card -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>All Students</h5>
                    <div>
                        <button class="btn btn-add me-2" onclick="openAddModal()">
                            <i class="fas fa-plus me-2"></i>Add New Student
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportTableToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export
                        </button>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search students...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="classFilter">
                            <option value="">All Classes</option>
                            <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                <option value="<?php echo $class['class_name']. '-' . $class['section']; ?>">
                                    <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Student ID</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sno = 1;
                            while($row = mysqli_fetch_assoc($students)): 
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td>
                                    <?php if($row['photo']): ?>
                                        <img src="../uploads/student-photos/<?php echo $row['photo']; ?>" 
                                             class="student-photo" alt="Photo">
                                    <?php else: ?>
                                        <img src="https://ui-avatars.com/api/?name=<?php echo $row['first_name'].'+'.$row['last_name']; ?>&background=2a5298&color=fff" 
                                             class="student-photo" alt="Photo">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo $row['student_id']; ?></span></td>
                                <td><?php echo $row['class_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['section'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['phone'] ?? 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="javascript:void(0)" onclick="viewStudent(<?php echo $row['id']; ?>)" 
                                       class="action-btn btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="editStudent(<?php echo $row['id']; ?>)" 
                                       class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                  
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this student?')">
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

    <!-- Add/Edit Student Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="studentForm" method="POST" action="student-process.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="student_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" id="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" id="last_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" id="father_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" class="form-control" name="mother_name" id="mother_name">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" id="phone" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" id="dob" min="1990-01-01" max="2015-12-31">
                                <small class="text-muted">Year range: 1990 - 2015</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" id="gender">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" id="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php 
                                    $classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
                                    while($class = mysqli_fetch_assoc($classes)): 
                                    ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-control" name="roll_number" id="roll_number">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Batch No</label>
                                <input type="text" class="form-control" name="batch_no" id="batch_no">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Admission Date</label>
                                <input type="date" class="form-control" name="admission_date" id="admission_date" min="2015-01-01" max="2026-12-31">
                                <small class="text-muted">Year range: 2015 - 2026</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Photo</label>
                            <input type="file" class="form-control" name="photo" id="photo" accept="image/*">
                            <small class="text-muted">Allowed: JPG, PNG, GIF (Max 2MB)</small>
                            <div id="photo_preview" class="mt-2"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" id="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" id="password">
                                <small class="text-muted">Leave blank to keep current password (when editing)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit" class="btn btn-save">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewDetails">
                    <!-- Load via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            var table = $('#studentsTable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false,
                "language": {
                    "search": "Search:",
                    "emptyTable": "No students found"
                }
            });

            // Custom search
            $('#searchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Class filter
            $('#classFilter').on('change', function() {
                table.column(4).search(this.value).draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                var status = this.value;
                if(status === '1') {
                    table.column(7).search('Active').draw();
                } else if(status === '0') {
                    table.column(7).search('Inactive').draw();
                } else {
                    table.column(7).search('').draw();
                }
            });
        });

        // Open Add Modal
        function openAddModal() {
            document.getElementById('studentForm').reset();
            document.getElementById('student_id').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Student';
            document.getElementById('password').required = true;
            document.getElementById('photo_preview').innerHTML = '';
            new bootstrap.Modal(document.getElementById('studentModal')).show();
        }

        // Edit Student
        function editStudent(id) {
            $.ajax({
                url: 'get-student.php',
                type: 'POST',
                data: {id: id},
                dataType: 'json',
                success: function(data) {
                    document.getElementById('student_id').value = data.id;
                    document.getElementById('first_name').value = data.first_name;
                    document.getElementById('last_name').value = data.last_name;
                    document.getElementById('father_name').value = data.father_name;
                    document.getElementById('mother_name').value = data.mother_name;
                    document.getElementById('email').value = data.email;
                    document.getElementById('phone').value = data.phone;
                    document.getElementById('dob').value = data.dob;
                    document.getElementById('gender').value = data.gender;
                    document.getElementById('class_id').value = data.class_id;
                    document.getElementById('roll_number').value = data.roll_number;
                    document.getElementById('batch_no').value = data.batch_no;
                    document.getElementById('admission_date').value = data.admission_date;
                    document.getElementById('address').value = data.address;
                    document.getElementById('username').value = data.username;
                    document.getElementById('password').required = false;
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Student';
                    
                    if(data.photo) {
                        document.getElementById('photo_preview').innerHTML = 
                            '<img src="../uploads/student-photos/' + data.photo + '" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
                    }
                    
                    new bootstrap.Modal(document.getElementById('studentModal')).show();
                }
            });
        }

        // View Student
        function viewStudent(id) {
            $.ajax({
                url: 'view-student.php',
                type: 'POST',
                data: {id: id},
                success: function(data) {
                    document.getElementById('viewDetails').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewModal')).show();
                }
            });
        }

        // Photo Preview
        document.getElementById('photo').addEventListener('change', function(e) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photo_preview').innerHTML = 
                    '<img src="' + e.target.result + '" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
            }
            reader.readAsDataURL(this.files[0]);
        });

        // Export to Excel
        function exportTableToExcel() {
            var table = document.getElementById('studentsTable');
            var html = table.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + escape(html);
            var link = document.createElement('a');
            link.download = 'students_list.xls';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>