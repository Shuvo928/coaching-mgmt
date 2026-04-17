<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin']);

// Handle Delete Request
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $teacher_id = $_GET['delete'];
    
    // Get teacher photo to delete
    $photo_query = "SELECT photo FROM teachers WHERE id = $teacher_id";
    $photo_result = mysqli_query($conn, $photo_query);
    $teacher = mysqli_fetch_assoc($photo_result);
    
    if($teacher['photo'] && file_exists("../uploads/teacher-photos/".$teacher['photo'])) {
        unlink("../uploads/teacher-photos/".$teacher['photo']);
    }
    
    // Delete teacher
    $query = "DELETE FROM teachers WHERE id = $teacher_id";
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Teacher deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting teacher!";
    }
    
    header("Location: teacher-management.php");
    exit();
}

// Get all teachers
$query = "SELECT t.*, 
          (SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = t.id) as subject_count 
          FROM teachers t 
          ORDER BY t.id DESC";
$teachers = mysqli_query($conn, $query);

// Get subjects for assignment
$subjects = mysqli_query($conn, "SELECT s.* FROM subjects s ORDER BY s.subject_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - CoachingPro</title>
    
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

        .teacher-photo {
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
            display: inline-block;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            color: white;
        }

        .btn-view { background: #2a5298; }
        .btn-edit { background: #f39c12; }
        .btn-delete { background: #e74c3c; }
        .btn-toggle { background: #27ae60; }
        .btn-assign { background: #9b59b6; }
        .btn-id-card { background: #3498db; }

        .subject-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 11px;
            margin: 2px;
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

        /* Select2 Customization */
        .select2-container--default .select2-selection--multiple {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 5px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 3px 10px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
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
            width: 100%;
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
                <a href="teacher-management.php" class="menu-item active">
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
                    <h4>Teacher Management</h4>
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
                    <h5><i class="fas fa-chalkboard-teacher me-2"></i>All Teachers</h5>
                    <div>
                        <button class="btn btn-add me-2" onclick="openAddModal()">
                            <i class="fas fa-plus me-2"></i>Add New Teacher
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportTableToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export
                        </button>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search teachers by name, ID, phone...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Teachers Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="teachersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Teacher ID</th>
                                <th>Qualification</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sno = 1;
                            while($row = mysqli_fetch_assoc($teachers)): 
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td>
                                    <?php if($row['photo']): ?>
                                        <img src="../uploads/teacher-photos/<?php echo $row['photo']; ?>" 
                                             class="teacher-photo" alt="Photo">
                                    <?php else: ?>
                                        <img src="https://ui-avatars.com/api/?name=<?php echo $row['first_name'].'+'.$row['last_name']; ?>&background=2a5298&color=fff" 
                                             class="teacher-photo" alt="Photo">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo $row['teacher_id']; ?></span></td>
                                <td><?php echo substr($row['qualification'], 0, 30) . '...'; ?></td>
                                <td><?php echo $row['phone']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="javascript:void(0)" onclick="viewTeacher(<?php echo $row['id']; ?>)" 
                                       class="action-btn btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="editTeacher(<?php echo $row['id']; ?>)" 
                                       class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this teacher?')">
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

    <!-- Add/Edit Teacher Modal -->
    <div class="modal fade" id="teacherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="teacherForm" method="POST" action="teacher-process.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="teacher_id" id="teacher_id">
                        
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
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" id="phone" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Qualification *</label>
                            <input type="text" class="form-control" name="qualification" id="qualification" 
                                   placeholder="e.g., M.Sc in Mathematics, B.Ed" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Preferred Subjects *</label>
                            <textarea class="form-control" name="assigned_subjects" id="assigned_subjects" 
                                      rows="3" placeholder="e.g., Mathematics, Physics, Chemistry, English" required></textarea>
                            <small class="text-muted">List the subjects this teacher prefers or is qualified to teach.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Joining Date *</label>
                            <input type="date" class="form-control" name="joining_date" id="joining_date" required>
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
                        <button type="submit" name="submit" class="btn btn-save">Save Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

   
    <!-- View Teacher Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Teacher Details</h5>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            var table = $('#teachersTable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false,
                "language": {
                    "search": "Search:",
                    "emptyTable": "No teachers found"
                }
            });

            // Custom search
            $('#searchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                var status = this.value;
                if(status === '1') {
                    table.column(6).search('Active').draw();
                } else if(status === '0') {
                    table.column(6).search('Inactive').draw();
                } else {
                    table.column(6).search('').draw();
                }
            });

            // Initialize Select2
            $('.select2-multiple').select2({
                placeholder: "Select subjects",
                allowClear: true
            });
        });

        // Open Add Modal
        function openAddModal() {
            document.getElementById('teacherForm').reset();
            document.getElementById('teacher_id').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Teacher';
            document.getElementById('password').required = true;
            document.getElementById('photo_preview').innerHTML = '';
            new bootstrap.Modal(document.getElementById('teacherModal')).show();
        }

        // Edit Teacher
        function editTeacher(id) {
            $.ajax({
                url: 'get-teacher.php',
                type: 'POST',
                data: {id: id},
                dataType: 'json',
                success: function(data) {
                    document.getElementById('teacher_id').value = data.id;
                    document.getElementById('first_name').value = data.first_name;
                    document.getElementById('last_name').value = data.last_name;
                    document.getElementById('email').value = data.email;
                    document.getElementById('phone').value = data.phone;
                    document.getElementById('qualification').value = data.qualification;
                    document.getElementById('assigned_subjects').value = data.assigned_subjects || '';
                    document.getElementById('joining_date').value = data.joining_date;
                    document.getElementById('address').value = data.address;
                    document.getElementById('username').value = data.username;
                    document.getElementById('password').required = false;
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Teacher';
                    
                    if(data.photo) {
                        document.getElementById('photo_preview').innerHTML = 
                            '<img src="../uploads/teacher-photos/' + data.photo + '" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
                    }
                    
                    new bootstrap.Modal(document.getElementById('teacherModal')).show();
                }
            });
        }

        // View Teacher
        function viewTeacher(id) {
            $.ajax({
                url: 'view-teacher.php',
                type: 'POST',
                data: {id: id},
                success: function(data) {
                    document.getElementById('viewDetails').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewModal')).show();
                }
            });
        }

        // Assign Subjects
        function assignSubjects(id) {
            $.ajax({
                url: 'get-teacher.php',
                type: 'POST',
                data: {id: id},
                dataType: 'json',
                success: function(data) {
                    document.getElementById('assign_teacher_id').value = data.id;
                    document.getElementById('teacher_name').value = data.first_name + ' ' + data.last_name;
                    
                    // Get assigned subjects
                    $.ajax({
                        url: 'get-assigned-subjects.php',
                        type: 'POST',
                        data: {teacher_id: id},
                        dataType: 'json',
                        success: function(subjects) {
                            $('.select2-multiple').val(subjects).trigger('change');
                        }
                    });
                    
                    new bootstrap.Modal(document.getElementById('assignModal')).show();
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
            var table = document.getElementById('teachersTable');
            var html = table.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + escape(html);
            var link = document.createElement('a');
            link.download = 'teachers_list.xls';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>