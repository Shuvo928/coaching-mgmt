<?php
session_start();
require_once '../includes/db.php';

// Check if parent is logged in
if(!isset($_SESSION['parent_id'])) {
    header("Location: ../parent-login.php");
    exit();
}

$parent_id = $_SESSION['parent_id'];
$student_name = $_SESSION['student_name'];
$student_mobile = $_SESSION['student_mobile'];

// Get student info to find student ID
$admission_query = "SELECT monthly_fee FROM admission_applications WHERE id = $parent_id LIMIT 1";
$admission_result = mysqli_query($conn, $admission_query);
$admission_data = mysqli_fetch_assoc($admission_result);
$monthly_fee = $admission_data['monthly_fee'] ?? 0;

// Get student ID from students table
$student_query = "SELECT id FROM students WHERE phone = '$student_mobile' LIMIT 1";
$student_result = mysqli_query($conn, $student_query);
$student_data = mysqli_fetch_assoc($student_result);
$student_id = $student_data['id'] ?? 0;

// Get fee collections for this student
$fees_query = "SELECT 
                    fc.id,
                    fh.fee_name,
                    fc.amount,
                    fc.paid_amount,
                    fc.due_amount,
                    fc.status,
                    fc.payment_date,
                    fc.created_at
                FROM fee_collections fc
                LEFT JOIN fees_head fh ON fc.fee_head_id = fh.id
                WHERE fc.student_id = $student_id
                ORDER BY fc.created_at DESC";

$fees_result = mysqli_query($conn, $fees_query);

// Calculate totals
$totals_query = "SELECT 
                    SUM(amount) as total_amount,
                    SUM(paid_amount) as total_paid,
                    SUM(due_amount) as total_due
                FROM fee_collections
                WHERE student_id = $student_id";

$totals_result = mysqli_query($conn, $totals_query);
$totals = mysqli_fetch_assoc($totals_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees & Payments - Parent Portal</title>
    
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

        /* Fee Summary Cards */
        .fee-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .fee-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .fee-card.total {
            border-left: 5px solid #2196f3;
        }

        .fee-card.paid {
            border-left: 5px solid #4caf50;
        }

        .fee-card.due {
            border-left: 5px solid #f44336;
        }

        .fee-card.monthly {
            border-left: 5px solid #ff9800;
        }

        .fee-amount {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            display: block;
        }

        .fee-label {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

        /* Fee Table */
        .fees-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .fees-container-header {
            padding: 25px;
            border-bottom: 2px solid #e2e8f0;
        }

        .fees-container h4 {
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

        .fee-head-name {
            font-weight: 600;
            color: #333;
        }

        .status-paid {
            display: inline-block;
            background: #c8e6c9;
            color: #2e7d32;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            display: inline-block;
            background: #ffcccc;
            color: #c62828;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-partial {
            display: inline-block;
            background: #fff9c4;
            color: #f57f17;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .amount-text {
            font-weight: 600;
            color: #333;
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

            .fee-summary {
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
                    <a href="results.php">
                        <i class="fas fa-chart-bar"></i>
                        Results & Grades
                    </a>
                </li>
                <li>
                    <a href="fees.php" class="active">
                        <i class="fas fa-money-bill"></i>
                        Fees & Payments
                    </a>
                </li>
                <li>
                    <a href="progress.php">
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
                <h2><i class="fas fa-money-bill me-3" style="color: #667eea;"></i>Fees & Payments</h2>
                <p>View <?php echo htmlspecialchars($student_name); ?>'s fee details and payment history</p>
            </div>

            <!-- Fee Summary Cards -->
            <div class="fee-summary">
                <div class="fee-card total">
                    <span class="fee-amount">৳<?php echo number_format($totals['total_amount'] ?? 0, 2); ?></span>
                    <div class="fee-label">Total Fees Amount</div>
                </div>
                <div class="fee-card paid">
                    <span class="fee-amount">৳<?php echo number_format($totals['total_paid'] ?? 0, 2); ?></span>
                    <div class="fee-label">Amount Paid</div>
                </div>
                <div class="fee-card due">
                    <span class="fee-amount">৳<?php echo number_format($totals['total_due'] ?? 0, 2); ?></span>
                    <div class="fee-label">Amount Due</div>
                </div>
                <div class="fee-card monthly">
                    <span class="fee-amount">৳<?php echo number_format($monthly_fee, 2); ?></span>
                    <div class="fee-label">Monthly Fee</div>
                </div>
            </div>

            <!-- Fee Collection Table -->
            <div class="fees-container">
                <div class="fees-container-header">
                    <h4><i class="fas fa-receipt me-2"></i>Fee Payment History</h4>
                </div>

                <?php if(mysqli_num_rows($fees_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fee Head</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while($row = mysqli_fetch_assoc($fees_result)) {
                                $status = $row['status'];
                                
                                if($status == 'Paid') {
                                    $status_class = 'status-paid';
                                    $status_icon = '✓';
                                } elseif($status == 'Pending') {
                                    $status_class = 'status-pending';
                                    $status_icon = '⏳';
                                } else {
                                    $status_class = 'status-partial';
                                    $status_icon = '⚠';
                                }
                            ?>
                            <tr>
                                <td><span class="fee-head-name"><?php echo htmlspecialchars($row['fee_head']); ?></span></td>
                                <td><span class="amount-text">৳<?php echo number_format($row['amount'], 2); ?></span></td>
                                <td><span class="amount-text">৳<?php echo number_format($row['paid_amount'], 2); ?></span></td>
                                <td><span class="amount-text">৳<?php echo number_format($row['due_amount'], 2); ?></span></td>
                                <td>
                                    <span class="<?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['payment_date'] ? date('d M, Y', strtotime($row['payment_date'])) : '--'; ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-money-bill"></i>
                    <p>No fee records found yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
