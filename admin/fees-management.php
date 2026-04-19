<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin']);

// Get current month/year
$current_month = date('m');
$current_year = date('Y');

// Get summary statistics
$summary = [];

// Total collection this month
$monthly_query = "SELECT SUM(paid_amount) as total FROM fee_collections 
                  WHERE MONTH(payment_date) = $current_month 
                  AND YEAR(payment_date) = $current_year 
                  AND payment_status = 'paid'";
$monthly_result = mysqli_query($conn, $monthly_query);
$summary['monthly_collection'] = mysqli_fetch_assoc($monthly_result)['total'] ?? 0;

// Total pending fees
$pending_query = "SELECT SUM(expected_amount - paid_amount) as total FROM fee_collections WHERE payment_status != 'paid'";
$pending_result = mysqli_query($conn, $pending_query);
$summary['pending_fees'] = mysqli_fetch_assoc($pending_result)['total'] ?? 0;

// Total students with pending fees
$pending_students = mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) as total 
                                          FROM fee_collections WHERE payment_status != 'paid'");
$summary['pending_students'] = mysqli_fetch_assoc($pending_students)['total'] ?? 0;

// Get all fee heads (fallback to current fees table)
$fee_heads = mysqli_query($conn, "SELECT * FROM fees ORDER BY id");

// Get classes
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");

// Get recent collections with class-wise fees
$recent_collections = mysqli_query($conn, "SELECT fc.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.id AS student_code, 
                                            s.class_id, c.class_name, fc.payment_status
                                           FROM fee_collections fc
                                           LEFT JOIN students s ON fc.student_id = s.id
                                           LEFT JOIN classes c ON s.class_id = c.id
                                           ORDER BY fc.created_at DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Management - CoachingPro</title>
    
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
    <!-- DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
    
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
            font-size: 32px;
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
        .stat-icon.green { background: #e8f5e9; color: #2e7d32; }
        .stat-icon.orange { background: #fff3e0; color: #f57c00; }
        .stat-icon.red { background: #ffebee; color: #c62828; }

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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            background: #f8f9fa;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .action-btn i {
            color: #667eea;
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

        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.paid {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.partial {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge.unpaid {
            background: #ffebee;
            color: #c62828;
        }

        .amount {
            font-weight: 600;
            color: #2a5298;
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
            padding: 12px 15px;
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
            color: white;
        }

        /* Fee Due Info */
        .due-info {
            background: #fff3e0;
            border-left: 4px solid #f57c00;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .due-amount {
            font-size: 24px;
            font-weight: 700;
            color: #f57c00;
        }

        /* Receipt */
        .receipt {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-header h4 {
            color: #2a5298;
            font-weight: 700;
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
                <a href="fees-management.php" class="menu-item active">
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
                    <h4>Fees Management</h4>
                </div>
                <div class="user-info">
                    <span class="badge bg-success me-3"><?php echo date('F Y'); ?></span>
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
                        <h3>৳<?php echo number_format($summary['monthly_collection'], 2); ?></h3>
                        <p>Monthly Collection</p>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>৳<?php echo number_format($summary['pending_fees'], 2); ?></h3>
                        <p>Total Pending</p>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $summary['pending_students']; ?></h3>
                        <p>Students with Dues</p>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo mysqli_num_rows($fee_heads); ?></h3>
                        <p>Fee Types</p>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            

            <!-- Tabs -->
            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-collections-tab" data-bs-toggle="pill" data-bs-target="#pills-collections" type="button">
                        <i class="fas fa-history me-2"></i>Recent Collections
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-fee-heads-tab" data-bs-toggle="pill" data-bs-target="#pills-fee-heads" type="button">
                        <i class="fas fa-tags me-2"></i>monthly fee heads
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-class-fees-tab" data-bs-toggle="pill" data-bs-target="#pills-class-fees" type="button">
                        <i class="fas fa-school me-2"></i>Class-wise Fees
                    </button>
                </li>
                
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <!-- Recent Collections Tab -->
                <div class="tab-pane fade show active" id="pills-collections" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Fee Collections</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportCollections()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Date</th>
                                        <th>Student Name</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Monthly Fees</th>
                                        <th>Month Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($recent_collections) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($recent_collections)): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?php echo $row['receipt_no']; ?></span></td>
                                            <td><?php echo date('d-m-Y', strtotime($row['payment_date'] ?? date('Y-m-d'))); ?></td>
                                            <td><?php echo $row['student_name'] ?? '<span class="text-warning">Pending Enrollment</span>'; ?></td>
                                            <td><?php echo ucfirst($row['payment_method'] ?? 'payment'); ?></td>
                                            <td class="amount">৳<?php echo number_format($row['expected_amount'] ?? 0, 2); ?></td>
                                            <td class="amount">৳<?php echo number_format($row['paid_amount'] ?? 0, 2); ?></td>
                                            <td><?php echo date('F Y', strtotime($row['payment_date'] ?? date('Y-m-d'))); ?></td>
                                            <td>
                                                <a href="javascript:void(0)" onclick="printReceipt(<?php echo $row['id']; ?>)" 
                                                   class="btn btn-sm btn-outline-info" title="Print Receipt">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="sendReminder(<?php echo $row['student_id']; ?>)" 
                                                   class="btn btn-sm btn-outline-warning" title="Send Reminder">
                                                    <i class="fas fa-sms"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                                <p>No fee collections yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Monthly Fee Collections Tab -->
                <div class="tab-pane fade" id="pills-fee-heads" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-alt me-2"></i>Monthly Fee Collections</h5>
                            <div>
                                <select class="form-select d-inline-block" style="width: auto;" id="month_filter">
                                    <option value="">All Months</option>
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="exportMonthlyFees()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="monthlyFeesTable">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Fee Type</th>
                                        <th>Month</th>
                                        <th>Total Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Due Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $monthly_fees = mysqli_query($conn, "SELECT fc.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.id AS student_code, c.class_name
                                                                          FROM fee_collections fc
                                                                          LEFT JOIN students s ON fc.student_id = s.id
                                                                          LEFT JOIN classes c ON s.class_id = c.id
                                                                          WHERE fc.student_id IS NOT NULL
                                                                          ORDER BY fc.payment_date DESC");
                                    
                                    if(mysqli_num_rows($monthly_fees) > 0):
                                        while($fee = mysqli_fetch_assoc($monthly_fees)):
                                            $payment_month = $fee['payment_date'] ? date('M Y', strtotime($fee['payment_date'])) : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?php echo $fee['student_id'] ?? '-'; ?></td>
                                        <td><?php echo $fee['student_name'] ?? '-'; ?></td>
                                        <td><?php echo $fee['class_name'] ?? '-'; ?></td>
                                        <td><?php echo $fee['payment_method'] ?? '-'; ?></td>
                                        <td><?php echo $payment_month; ?></td>
                                        <td class="amount">৳<?php echo number_format($fee['expected_amount'], 2); ?></td>
                                        <td class="amount">৳<?php echo number_format($fee['paid_amount'], 2); ?></td>
                                        <td class="amount">৳<?php echo number_format(($fee['expected_amount'] ?? 0) - ($fee['paid_amount'] ?? 0), 2); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($fee['payment_status'] ?? 'unpaid'); ?>">
                                                <?php echo ucfirst($fee['payment_status'] ?? 'unpaid'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0)" onclick="printReceipt(<?php echo $fee['id']; ?>)" 
                                               class="btn btn-sm btn-outline-info" title="Print">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="editPayment(<?php echo $fee['id']; ?>)" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <p>No monthly fee collections yet</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Class-wise Fees Tab -->
                <div class="tab-pane fade" id="pills-class-fees" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-school me-2"></i>Class-wise Fee Setup</h5>
                            <button class="btn btn-sm btn-primary" onclick="openClassFeeModal()">
                                <i class="fas fa-plus me-2"></i>Set Class Fee
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $class_fees = mysqli_query($conn, "SELECT cf.*, c.class_name
                                                                        FROM class_fees cf
                                                                        JOIN classes c ON cf.class_id = c.id
                                                                        ORDER BY c.class_name");
                                    while($fee = mysqli_fetch_assoc($class_fees)):
                                    ?>
                                    <tr>
                                        <td><?php echo $fee['class_name'] ?? '-'; ?></td>
                                        <td>-</td>
                                        <td>Monthly Fee</td>
                                        <td class="amount">৳<?php echo number_format($fee['monthly_fee'] ?? 0, 2); ?></td>
                                        <td>Not set</td>
                                        <td>
                                            <a href="javascript:void(0)" onclick="editClassFee(<?php echo $fee['id']; ?>)" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete_class_fee=<?php echo $fee['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete this fee setup?')">
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

                <!-- Due List Tab -->
                <div class="tab-pane fade" id="pills-due-list" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-exclamation-circle me-2"></i>Due List</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="sendDueReminders()">
                                    <i class="fas fa-sms me-2"></i>Send Reminders
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="exportDueList()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </div>

                        <div class="filter-section mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <select class="form-select" id="due_class_filter">
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
                                    <select class="form-select" id="due_status_filter">
                                        <option value="">All Status</option>
                                        <option value="Partial">Partial</option>
                                        <option value="Unpaid">Unpaid</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" id="due_search" placeholder="Search student...">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="dueTable">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Fee Type</th>
                                        <th>Total Amount</th>
                                        <th>Paid</th>
                                        <th>Due</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $due_list = mysqli_query($conn, "SELECT fc.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.id AS student_code, c.class_name
                                                                      FROM fee_collections fc
                                                                      JOIN students s ON fc.student_id = s.id
                                                                      JOIN classes c ON s.class_id = c.id
                                                                      WHERE fc.payment_status != 'paid'
                                                                      ORDER BY fc.payment_date ASC");
                                    while($due = mysqli_fetch_assoc($due_list)):
                                    ?>
                                    <tr>
                                        <td><?php echo $due['student_code'] ?? '-'; ?></td>
                                        <td><?php echo $due['student_name'] ?? '-'; ?></td>
                                        <td><?php echo $due['class_name'] ?? '-'; ?></td>
                                        <td><?php echo $due['payment_method'] ?? '-'; ?></td>
                                        <td class="amount">৳<?php echo number_format($due['expected_amount'], 2); ?></td>
                                        <td class="amount">৳<?php echo number_format($due['paid_amount'], 2); ?></td>
                                        <td class="amount text-danger">৳<?php echo number_format(($due['expected_amount'] ?? 0) - ($due['paid_amount'] ?? 0), 2); ?></td>
                                        <td><?php echo $due['payment_date'] ? date('d-m-Y', strtotime($due['payment_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($due['payment_status'] ?? 'unpaid'); ?>">
                                                <?php echo ucfirst($due['payment_status'] ?? 'unpaid'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0)" onclick="collectDue(<?php echo $due['id']; ?>)" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-hand-holding-usd"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="sendReminder(<?php echo $due['student_id']; ?>)" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-bell"></i>
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
        </div>
    </div>

    <!-- Collect Fee Modal -->
    <div class="modal fade" id="collectFeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Collect Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="collectFeeForm" method="POST" action="process-fee-collection.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Student</label>
                                <select class="form-select select2" name="student_id" id="fee_student" required>
                                    <option value="">Search Student</option>
                                    <?php 
                                    $all_students = mysqli_query($conn, "SELECT s.*, c.class_name 
                                                                          FROM students s
                                                                          JOIN classes c ON s.class_id = c.id
                                                                          WHERE s.status = 1
                                                                          ORDER BY CONCAT(s.first_name, ' ', s.last_name)");
                                    while($student = mysqli_fetch_assoc($all_students)): 
                                    ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['first_name'] . ' ' . $student['last_name']; ?> 
                                            (<?php echo $student['class_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fee Type</label>
                                <select class="form-select" name="fee_head_id" id="fee_head" required>
                                    <option value="">Select Fee Type</option>
                                    <?php 
                                    mysqli_data_seek($fee_heads, 0);
                                    while($head = mysqli_fetch_assoc($fee_heads)): 
                                    ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo $head['fee_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div id="fee_details" class="due-info" style="display: none;">
                            <h6>Fee Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Total Amount: <span id="total_amount" class="amount">৳0.00</span></p>
                                    <p>Already Paid: <span id="paid_amount" class="amount">৳0.00</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p>Due Amount: <span id="due_amount_display" class="due-amount">৳0.00</span></p>
                                    <p>Due Date: <span id="due_date_display"></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paying</label>
                                <input type="number" class="form-control" name="paying_amount" id="paying_amount" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Online">Online Payment</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date</label>
                                <input type="text" class="form-control datepicker" name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Remarks</label>
                                <input type="text" class="form-control" name="remarks" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="collect_fee" class="btn btn-save">
                            <i class="fas fa-save me-2"></i>Collect Fee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fee Head Modal -->
    <div class="modal fade" id="feeHeadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Fee Head</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process-fee-head.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Fee Name *</label>
                            <input type="text" class="form-control" name="fee_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" checked>
                                <label class="form-check-label">Mandatory Fee</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_fee_head" class="btn btn-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Class Fee Modal -->
    <div class="modal fade" id="classFeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Class-wise Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process-class-fee.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">Choose Class</option>
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
                        <div class="mb-3">
                            <label class="form-label">Fee Type</label>
                            <select class="form-select" name="fee_head_id" required>
                                <option value="">Select Fee Type</option>
                                <?php 
                                mysqli_data_seek($fee_heads, 0);
                                while($head = mysqli_fetch_assoc($fee_heads)): 
                                ?>
                                    <option value="<?php echo $head['id']; ?>">
                                        <?php echo $head['month'] ?? ('Fee ' . $head['id']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (৳)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date (Optional)</label>
                            <input type="text" class="form-control datepicker" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_class_fee" class="btn btn-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent">
                    <!-- Receipt will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
    
    <script>
        // Initialize components
        $(document).ready(function() {
            $('.select2').select2({
                dropdownParent: $('#collectFeeModal')
            });
            
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
            
            $('#dueTable').DataTable({
                "pageLength": 25,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false
            });
        });

        // Load fee details when student and fee type selected
        $('#fee_student, #fee_head').change(function() {
            var student_id = $('#fee_student').val();
            var fee_head_id = $('#fee_head').val();
            
            if(student_id && fee_head_id) {
                $.ajax({
                    url: 'get-fee-details.php',
                    type: 'POST',
                    data: {
                        student_id: student_id,
                        fee_head_id: fee_head_id
                    },
                    dataType: 'json',
                    success: function(data) {
                        $('#fee_details').show();
                        $('#total_amount').text('৳' + data.amount.toFixed(2));
                        $('#paid_amount').text('৳' + data.paid.toFixed(2));
                        $('#due_amount_display').text('৳' + data.due.toFixed(2));
                        $('#due_date_display').text(data.due_date || 'Not set');
                        $('#paying_amount').attr('max', data.due);
                    }
                });
            }
        });

        // Open Collect Fee Modal
        function openCollectFeeModal() {
            $('#collectFeeForm')[0].reset();
            $('#fee_details').hide();
            new bootstrap.Modal(document.getElementById('collectFeeModal')).show();
        }

        // Open Fee Head Modal
        function openFeeHeadModal() {
            new bootstrap.Modal(document.getElementById('feeHeadModal')).show();
        }

        // Open Class Fee Modal
        function openClassFeeModal() {
            new bootstrap.Modal(document.getElementById('classFeeModal')).show();
        }

        // Print Receipt
        function printReceipt(collection_id) {
            $.ajax({
                url: 'get-receipt.php',
                type: 'POST',
                data: {id: collection_id},
                success: function(data) {
                    $('#receiptContent').html(data);
                    new bootstrap.Modal(document.getElementById('receiptModal')).show();
                }
            });
        }

        // Send Reminder
        function sendReminder(student_id) {
            if(confirm('Send fee reminder SMS to this student?')) {
                $.ajax({
                    url: 'send-fee-reminder.php',
                    type: 'POST',
                    data: {student_id: student_id},
                    success: function(response) {
                        alert('Reminder sent successfully!');
                    }
                });
            }
        }

        // Send Due Reminders to all
        function sendDueReminders() {
            if(confirm('Send SMS reminders to all students with pending fees?')) {
                $.ajax({
                    url: 'send-bulk-reminders.php',
                    type: 'POST',
                    success: function(response) {
                        alert('Reminders sent to all due students!');
                    }
                });
            }
        }

        // Export functions
        function exportCollections() {
            window.location.href = 'export-collections.php';
        }

        function exportDueList() {
            window.location.href = 'export-due-list.php';
        }

        // Filter monthly fees by month
        function filterMonthlyFees() {
            try {
                const monthSelect = document.getElementById('month_filter');
                const monthlyTable = document.getElementById('monthlyFeesTable');
                
                if (!monthlyTable) {
                    console.log('Monthly fees table not found');
                    return;
                }
                
                const month = monthSelect ? monthSelect.value : '';
                const rows = monthlyTable.getElementsByTagName('tbody')[0];
                
                if (!rows) return;
                
                const tableRows = rows.getElementsByTagName('tr');
                let visibleCount = 0;
                
                for (let i = 0; i < tableRows.length; i++) {
                    const row = tableRows[i];
                    const monthCell = row.cells[4]; // Month column
                    
                    if (!monthCell) continue;
                    
                    const monthText = monthCell.textContent.trim();
                    
                    if (!month || monthText.includes(getMonthName(month))) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
                
                if (visibleCount === 0 && tableRows.length > 0) {
                    console.log('No records found for selected month');
                }
            } catch (e) {
                console.error('Error filtering monthly fees:', e);
            }
        }
        
        function getMonthName(monthNum) {
            const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
            return months[parseInt(monthNum)] || '';
        }

        // Export monthly fees
        function exportMonthlyFees() {
            const month = document.getElementById('month_filter').value;
            const url = month ? 'export-monthly-fees.php?month=' + month : 'export-monthly-fees.php';
            window.location.href = url;
        }

        // Edit payment record
        function editPayment(fee_collection_id) {
            // Open modal to edit payment
            $.ajax({
                url: 'get-payment-details.php',
                type: 'POST',
                data: {id: fee_collection_id},
                success: function(data) {
                    const payment = JSON.parse(data);
                    // Pre-fill the collect fee modal
                    document.getElementById('payment_id').value = payment.id;
                    document.getElementById('student_id').value = payment.student_id;
                    document.getElementById('paid_amount').value = payment.paid_amount;
                    new bootstrap.Modal(document.getElementById('collectFeeModal')).show();
                },
                error: function() {
                    alert('Error loading payment details');
                }
            });
        }

        // Collect due
        function collectDue(fee_collection_id) {
            // Open collect fee modal with this due pre-selected
            openCollectFeeModal();
            // Additional logic to pre-fill based on fee_collection_id
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const monthFilter = document.getElementById('month_filter');
            if (monthFilter) {
                monthFilter.addEventListener('change', filterMonthlyFees);
            }
        });
    </script>
</body>
</html>