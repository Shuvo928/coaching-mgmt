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

// Handle monthly payment submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_monthly_payment'])) {
    $monthly_fee_id = intval($_POST['monthly_fee_id']);
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id']);
    
    // Get current monthly fee record
    $fee_check = "SELECT id, tuition_fee, paid_amount, due_amount, status FROM monthly_fees WHERE id = $monthly_fee_id AND student_id = $student_id";
    $fee_result = mysqli_query($conn, $fee_check);
    $fee = mysqli_fetch_assoc($fee_result);
    
    if($fee && $payment_amount > 0 && $payment_amount <= $fee['due_amount']) {
        // Generate receipt
        $receipt_no = 'RCP' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calculate new totals
        $new_paid = $fee['paid_amount'] + $payment_amount;
        $new_due = $fee['due_amount'] - $payment_amount;
        $new_status = ($new_due <= 0) ? 'Paid' : 'Partial';
        
        // Update monthly fee
        $update_fee = "UPDATE monthly_fees SET 
                       paid_amount = $new_paid, 
                       due_amount = $new_due, 
                       status = '$new_status',
                       payment_method = '$payment_method',
                       payment_date = CURDATE(),
                       receipt_no = '$receipt_no',
                       transaction_id = '$transaction_id'
                       WHERE id = $monthly_fee_id";
        
        if(mysqli_query($conn, $update_fee)) {
            // Log transaction
            $log_insert = "INSERT INTO sms_logs (mobile_number, message, type, status) 
                          VALUES ('$student_mobile', 'Monthly fee payment of ৳$payment_amount received via $payment_method. Receipt: $receipt_no', 'Student', 'Sent')";
            mysqli_query($conn, $log_insert);
            
            $_SESSION['success'] = "Payment of ৳$payment_amount received successfully! Receipt No: $receipt_no";
            header("Refresh: 0");
            exit();
        } else {
            $_SESSION['error'] = "Payment processing failed. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Invalid payment amount.";
    }
}

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

        /* Payment Modal */
        .payment-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .payment-method.selected {
            border-color: #667eea;
            background: #e8eef7;
        }

        .payment-method i {
            font-size: 32px;
            color: #667eea;
            display: block;
            margin-bottom: 10px;
        }

        .payment-method label {
            font-weight: 600;
            color: #333;
            display: block;
            cursor: pointer;
            margin: 0;
        }

        .alert {
            margin-bottom: 20px;
            border-radius: 10px;
            border: none;
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
            <!-- Alerts -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

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

            <!-- Monthly Fees Status Section -->
            <?php 
            if($student_id > 0) {
                // Get last 3 months + current month + next month fees
                $months_query = "SELECT 
                                    id,
                                    month,
                                    year,
                                    tuition_fee,
                                    paid_amount,
                                    due_amount,
                                    status,
                                    payment_date,
                                    payment_method,
                                    receipt_no
                                FROM monthly_fees
                                WHERE student_id = $student_id
                                ORDER BY year DESC, month DESC
                                LIMIT 6";
                $months_result = mysqli_query($conn, $months_query);
                $months_count = $months_result ? mysqli_num_rows($months_result) : 0;
                
                // Get current month
                $current_month = date('F');
                $current_year = date('Y');
            } else {
                $months_count = 0;
                $months_result = null;
            }
            ?>

            <?php if($months_count > 0): ?>
            <div class="fees-container" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe8cc 100%); border: 3px solid #ff9800; margin-bottom: 30px;">
                <div class="fees-container-header" style="background: linear-gradient(135deg, #ff9800, #f57c00); color: white; border-bottom: 3px solid #e65100;">
                    <h4 style="color: white; margin: 0;"><i class="fas fa-calendar-alt me-2"></i>Monthly Fees Status</h4>
                </div>

                <div style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php 
                        if($months_result) {
                            $count = 0;
                            while($count < 6 && $month_fee = mysqli_fetch_assoc($months_result)): 
                                $month_display = $month_fee['month'];
                                $year_display = $month_fee['year'];
                                $is_current = ($month_display == $current_month && $year_display == $current_year);
                                
                                $status_class = '';
                                $status_color = '';
                                $status_text = '';
                                $border_color = '';
                                
                                if($month_fee['status'] == 'Paid') {
                                    $status_class = 'paid';
                                    $status_color = '#4caf50';
                                    $status_text = '✓ Fully Paid';
                                    $border_color = '#4caf50';
                                } elseif($month_fee['status'] == 'Partial') {
                                    $status_class = 'partial';
                                    $status_color = '#ff9800';
                                    $status_text = '⚠ Partially Paid';
                                    $border_color = '#ff9800';
                                } else {
                                    $status_class = 'unpaid';
                                    $status_color = '#f44336';
                                    $status_text = '✗ Not Paid';
                                    $border_color = '#f44336';
                                }
                        ?>
                        <div style="background: white; padding: 20px; border-radius: 10px; border-left: 5px solid <?php echo $border_color; ?>; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative;">
                            <?php if($is_current): ?>
                            <div style="position: absolute; top: -12px; right: 15px; background: #f44336; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">CURRENT MONTH</div>
                            <?php endif; ?>
                            
                            <div style="margin-bottom: 15px; margin-top: <?php echo $is_current ? '15px' : '0'; ?>;">
                                <h6 style="margin: 0 0 5px 0; color: #333; font-weight: 600; font-size: 18px;">
                                    <?php echo htmlspecialchars($month_display) . ' ' . $year_display; ?>
                                </h6>
                                <small style="color: <?php echo $status_color; ?>; display: block; font-weight: 600;">
                                    <?php echo $status_text; ?>
                                </small>
                            </div>

                            <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                                <div style="font-size: 24px; font-weight: 700; color: <?php echo $status_color; ?>;">৳<?php echo number_format($month_fee['due_amount'], 2); ?></div>
                                <small style="color: #999;">Amount Due</small>
                                
                                <?php if($month_fee['paid_amount'] > 0): ?>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                    <small style="color: #666;">Paid: <strong>৳<?php echo number_format($month_fee['paid_amount'], 2); ?></strong></small>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if($month_fee['status'] == 'Paid' && $month_fee['receipt_no']): ?>
                            <div style="background: #e8f5e9; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 12px;">
                                <strong style="color: #2e7d32;">Receipt:</strong> <?php echo htmlspecialchars($month_fee['receipt_no']); ?>
                                <br><strong style="color: #2e7d32;">Date:</strong> <?php echo date('d-M-Y', strtotime($month_fee['payment_date'])); ?>
                            </div>
                            <?php endif; ?>

                            <?php if($month_fee['status'] != 'Paid'): ?>
                            <button type="button" style="width: 100%; background: linear-gradient(135deg, #f44336, #d32f2f); color: white; border: none; padding: 12px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px;" 
                                    data-bs-toggle="modal" data-bs-target="#paymentModal" 
                                    onclick="initializeMonthlyPayment(<?php echo $month_fee['id']; ?>, <?php echo $month_fee['due_amount']; ?>, '<?php echo htmlspecialchars($month_display); ?>')">
                                <i class="fas fa-money-bill-wave" style="margin-right: 8px;"></i>Pay Now
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php 
                                $count++;
                            endwhile;
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert" style="margin-bottom: 30px;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Info:</strong> Monthly fee records not found. Please contact admin to set up monthly fees.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Fee Collection Table -->
            <div class="fees-container">
                <div class="fees-container-header">
                    <h4><i class="fas fa-receipt me-2"></i>Payment History</h4>
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
                                <td><span class="fee-head-name"><?php echo htmlspecialchars($row['fee_name'] ?? 'Unknown Fee'); ?></span></td>
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

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #f44336, #d32f2f); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Pay Monthly Fee
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm" method="POST">
                        <input type="hidden" name="process_monthly_payment" value="1">
                        <input type="hidden" name="monthly_fee_id" id="monthly_fee_id">
                        
                        <!-- Fee Month Display -->
                        <div class="mb-4">
                            <label class="form-label fw-600">Monthly Fee for</label>
                            <input type="text" class="form-control" id="fee_name" readonly style="background: #f0f4ff; font-weight: 600; color: #333;">
                        </div>

                        <!-- Due Amount Display -->
                        <div class="mb-4">
                            <label class="form-label fw-600">Amount Due</label>
                            <div style="font-size: 32px; font-weight: 700; color: #f44336; margin: 15px 0;">
                                ৳<span id="due_amount">0.00</span>
                            </div>
                        </div>

                        <!-- Payment Amount Input -->
                        <div class="mb-4">
                            <label class="form-label fw-600">Amount to Pay <span style="color: #f44336;">*</span></label>
                            <input type="number" name="payment_amount" id="payment_amount" class="form-control" 
                                   placeholder="Enter payment amount" step="0.01" min="1" required>
                            <small class="text-danger" id="amount_error"></small>
                        </div>

                        <!-- Payment Methods -->
                        <div class="mb-4">
                            <label class="form-label fw-600">Select Payment Method <span style="color: #f44336;">*</span></label>
                            <div class="payment-methods">
                                <div class="payment-method" onclick="selectPaymentMethod(this, 'bKash')">
                                    <i class="fas fa-mobile-alt"></i>
                                    <input type="radio" name="payment_method" value="bKash" style="display: none;">
                                    <label>bKash</label>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod(this, 'Nagad')">
                                    <i class="fas fa-wallet"></i>
                                    <input type="radio" name="payment_method" value="Nagad" style="display: none;">
                                    <label>Nagad</label>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod(this, 'Rocket')">
                                    <i class="fas fa-rocket"></i>
                                    <input type="radio" name="payment_method" value="Rocket" style="display: none;">
                                    <label>Rocket</label>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod(this, 'Cash')">
                                    <i class="fas fa-money-bill"></i>
                                    <input type="radio" name="payment_method" value="Cash" style="display: none;">
                                    <label>Cash at Office</label>
                                </div>
                            </div>
                            <small class="text-danger" id="method_error"></small>
                        </div>

                        <!-- Transaction ID -->
                        <div class="mb-4">
                            <label class="form-label fw-600">Transaction ID / Reference <span style="color: #f44336;">*</span></label>
                            <input type="text" name="transaction_id" class="form-control" 
                                   placeholder="e.g., Your bKash PIN or receipt number" required>
                            <small class="text-muted">For mobile banking: your transaction confirmation number</small>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-lg" style="background: linear-gradient(135deg, #f44336, #d32f2f); color: white; font-weight: 600;">
                                <i class="fas fa-check me-2"></i>Confirm Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let selectedPaymentMethod = null;
        let currentDueAmount = 0;

        function initializeMonthlyPayment(monthlyFeeId, dueAmount, monthName) {
            document.getElementById('monthly_fee_id').value = monthlyFeeId;
            document.getElementById('due_amount').textContent = dueAmount.toFixed(2);
            document.getElementById('fee_name').value = monthName;
            document.getElementById('payment_amount').value = dueAmount.toFixed(2);
            document.getElementById('payment_amount').max = dueAmount;
            currentDueAmount = dueAmount;
            
            // Reset form
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('input[name="payment_method"]').forEach(el => el.checked = false);
            selectedPaymentMethod = null;
            document.getElementById('transaction_id').value = '';
        }

        function selectPaymentMethod(element, method) {
            // Deselect all
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            
            // Select this one
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
            selectedPaymentMethod = method;
            document.getElementById('method_error').textContent = '';
        }

        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(document.getElementById('payment_amount').value);
            
            // Validation
            if(!selectedPaymentMethod) {
                document.getElementById('method_error').textContent = 'Please select a payment method';
                return false;
            }
            
            if(amount <= 0 || amount > currentDueAmount) {
                document.getElementById('amount_error').textContent = 'Invalid amount. Please enter amount between 1 and ' + currentDueAmount;
                return false;
            }
            
            // Submit form
            this.submit();
        });

        document.getElementById('payment_amount').addEventListener('change', function() {
            document.getElementById('amount_error').textContent = '';
        });
    </script>
</body>
</html>
