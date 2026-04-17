<?php
session_start();
require_once 'includes/db.php';

function ensureSenderNumberColumn($conn) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'sender_number'");
    if($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE admission_applications ADD COLUMN sender_number VARCHAR(20) NULL AFTER payment_method");
    }
}

// Handle form submission
if(isset($_POST['submit_admission'])) {
    ensureSenderNumberColumn($conn);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $program = mysqli_real_escape_string($conn, $_POST['program']);
    $group = mysqli_real_escape_string($conn, $_POST['group']);

    $checkEmail = mysqli_query($conn, "SELECT id FROM admission_applications WHERE email = '$email' LIMIT 1");
    if($checkEmail && mysqli_num_rows($checkEmail) > 0) {
        $error = 'This email is already registered. Please use a different email.';
    } else {
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $program = mysqli_real_escape_string($conn, $_POST['program']);
        $group = mysqli_real_escape_string($conn, $_POST['group']);
        
        // Parent Information
        $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
        $parent_email = mysqli_real_escape_string($conn, $_POST['parent_email']);
        $parent_phone = mysqli_real_escape_string($conn, $_POST['parent_phone']);
        $sender_number = mysqli_real_escape_string($conn, $_POST['sender_number']);
        
        // Get monthly fee based on program
        $monthly_fee = 0;
        if($program == 'Class 9') {
            $monthly_fee = 825;
        } elseif($program == 'Class 10') {
            $monthly_fee = 978;
        } elseif($program == 'SSC Batch') {
            $monthly_fee = 1150;
        }
        
        $transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $sender_number = mysqli_real_escape_string($conn, $_POST['sender_number']);
        $application_fee = 500;
        
        // Generate unique receipt number
        $receipt_no = 'RCP' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert into database
        $query = "INSERT INTO admission_applications (full_name, gender, mobile, email, address, program, `group`, parent_name, parent_email, parent_phone, monthly_fee, transaction_id, payment_method, sender_number, application_fee, receipt_no, status, created_at) 
                  VALUES ('$full_name', '$gender', '$mobile', '$email', '$address', '$program', '$group', '$parent_name', '$parent_email', '$parent_phone', $monthly_fee, '$transaction_id', '$payment_method', '$sender_number', $application_fee, '$receipt_no', 'Pending', NOW())";
        
        if(mysqli_query($conn, $query)) {
            $application_id = mysqli_insert_id($conn);
            
            // Store receipt data in session for display
            $_SESSION['receipt_data'] = [
                'application_id' => $application_id,
                'receipt_no' => $receipt_no,
                'full_name' => $full_name,
                'mobile' => $mobile,
                'email' => $email,
                'program' => $program,
                'group' => $group,
                'monthly_fee' => $monthly_fee,
                'application_fee' => $application_fee,
                'payment_method' => $payment_method,
                'transaction_id' => $transaction_id,
                'sender_number' => $sender_number,
                'submission_date' => date('Y-m-d H:i:s')
            ];
            
        } else {
            $error = "Error submitting application: " . mysqli_error($conn);
        }
    }
}

// Application fee
$application_fee = 500;

// Fee structure
$fees = [
    'Class 9' => 825,
    'Class 10' => 978,
    'SSC Batch' => 1150
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Admission Portal - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --navy: #0F172A;
            --cyan: #06B6D4;
            --light-cyan: #38BDF8;
            --light-bg: #F8FAFC;
            --text-dark: #111827;
            --white: #FFFFFF;
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--white);
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--navy) !important;
        }

        .navbar-brand span {
            color: var(--cyan);
        }

        .admission-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 20px;
        }

        /* Program Selection Cards */
        .program-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .program-card {
            background: var(--white);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 3px solid transparent;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .program-card:hover {
            transform: translateY(-5px);
        }

        .program-card.selected {
            border-color: var(--cyan);
            background: linear-gradient(135deg, #ffffff, #ecfeff);
        }

        .program-icon {
            width: 80px;
            height: 80px;
            background: var(--light-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: var(--cyan);
        }

        .program-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 10px;
        }

        .program-fee {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--cyan);
            margin-bottom: 5px;
        }

        .program-fee small {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: normal;
        }

        .program-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--cyan);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Group Selection */
        .group-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .group-btn {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .group-btn:hover {
            border-color: var(--cyan);
        }

        .group-btn.selected {
            background: var(--cyan);
            color: white;
            border-color: var(--cyan);
        }

        .group-btn.selected i {
            color: white;
        }

        .group-btn i {
            font-size: 24px;
            color: var(--cyan);
            margin-bottom: 5px;
        }

        .group-btn.science i { color: #2563eb; }
        .group-btn.humanities i { color: #9333ea; }
        .group-btn.commerce i { color: #16a34a; }

        .group-btn.selected.science i,
        .group-btn.selected.humanities i,
        .group-btn.selected.commerce i {
            color: white;
        }

        /* Fee Info Card */
        .fee-info-card {
            background: linear-gradient(135deg, var(--navy), #1e293b);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
        }

        .fee-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .fee-item {
            text-align: center;
        }

        .fee-item .label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .fee-item .amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--cyan);
        }

        .admission-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            animation: slideUp 0.5s ease;
            margin-top: 30px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admission-header {
            background: linear-gradient(135deg, var(--navy), #1e293b);
            color: var(--white);
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .admission-header::before {
            content: '🎓';
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 100px;
            opacity: 0.1;
        }

        .admission-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .fee-badge {
            background: var(--cyan);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            font-weight: 600;
            margin-top: 20px;
        }

        .admission-body {
            padding: 40px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--navy);
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--cyan);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--cyan);
            box-shadow: none;
        }

        .input-group {
            border-radius: 10px;
            overflow: hidden;
        }

        .input-group .btn {
            padding: 12px 20px;
            font-weight: 500;
        }

        .otp-section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }

        .otp-verified {
            background: #d1fae5;
            border-color: #10b981;
        }

        .otp-verified .verified-badge {
            color: #10b981;
            font-weight: 600;
        }

        .payment-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .payment-methods {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .payment-method {
            flex: 1;
            min-width: 150px;
        }

        .payment-method-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-method-card:hover {
            border-color: var(--cyan);
            transform: translateY(-2px);
        }

        .payment-method-card.selected {
            border-color: var(--cyan);
            background: #ecfeff;
        }

        .payment-method-card img {
            height: 40px;
            margin-bottom: 10px;
        }

        .transaction-field {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .declaration {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }

        .section-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--cyan), var(--light-cyan));
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: var(--transition);
            margin-bottom: 15px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(6, 182, 212, 0.4);
        }

        .btn-reset {
            background: #64748b;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: var(--transition);
        }

        .btn-reset:hover {
            background: #475569;
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .selected-fee {
            background: #ecfeff;
            border: 2px solid var(--cyan);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .selected-fee .amount {
            font-size: 2rem;
            font-weight: 800;
            color: var(--cyan);
        }

        @media (max-width: 768px) {
            .program-cards,
            .group-buttons,
            .fee-details {
                grid-template-columns: 1fr;
            }
            
            .admission-body {
                padding: 20px;
            }
            
            .payment-methods {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                Coaching<span>Pro</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                   
                </ul>
            </div>
        </div>
    </nav>

    <div class="admission-container" data-aos="fade-up">
        <!-- Receipt Display (After Successful Submission) -->
        <?php if(isset($_SESSION['receipt_data'])): ?>
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="receipt-title">
                        <i class="fas fa-check-circle" style="color: #4caf50; font-size: 48px;"></i>
                        <h2>Application Submitted Successfully!</h2>
                    </div>
                </div>

                <div class="receipt-content">
                    <div class="receipt-section">
                        <h4 class="section-title"><i class="fas fa-receipt me-2"></i>Receipt Details</h4>
                        <div class="receipt-row">
                            <span class="label">Receipt Number:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['receipt_no']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Application ID:</span>
                            <span class="value">APP<?php echo str_pad($_SESSION['receipt_data']['application_id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Submission Date:</span>
                            <span class="value"><?php echo date('d M, Y H:i A', strtotime($_SESSION['receipt_data']['submission_date'])); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Status:</span>
                            <span class="value badge bg-warning">Pending Approval</span>
                        </div>
                    </div>

                    <div class="receipt-section">
                        <h4 class="section-title"><i class="fas fa-user me-2"></i>Student Information</h4>
                        <div class="receipt-row">
                            <span class="label">Full Name:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['full_name']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Mobile:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['mobile']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Email:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['email']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Program:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['program'] . ' - ' . $_SESSION['receipt_data']['group']; ?></span>
                        </div>
                    </div>

                    <div class="receipt-section">
                        <h4 class="section-title"><i class="fas fa-money-bill me-2"></i>Payment Details</h4>
                        <div class="receipt-row">
                            <span class="label">Application Fee:</span>
                            <span class="value">৳<?php echo number_format($_SESSION['receipt_data']['application_fee'], 2); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Monthly Fee:</span>
                            <span class="value">৳<?php echo number_format($_SESSION['receipt_data']['monthly_fee'], 2); ?></span>
                        </div>
                        <div class="receipt-row" style="border-top: 2px solid #ddd; padding-top: 10px; margin-top: 10px;">
                            <span class="label" style="font-weight: 700;">Total Payment:</span>
                            <span class="value" style="font-weight: 700; color: #4caf50; font-size: 18px;">৳<?php echo number_format($_SESSION['receipt_data']['application_fee'], 2); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Payment Method:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['payment_method']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Transaction ID:</span>
                            <span class="value"><?php echo $_SESSION['receipt_data']['transaction_id']; ?></span>
                        </div>
                    </div>

                    <div class="receipt-info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <p><strong>Next Steps:</strong> Your application is under review. You will receive an SMS and email confirmation once your application is approved by the admin. You can also check the status anytime by logging into your parent portal.</p>
                    </div>
                </div>

                <div class="receipt-actions">
                    <button class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print me-2"></i>Print Receipt
                    </button>
                    <button class="btn btn-secondary" onclick="downloadReceipt()">
                        <i class="fas fa-download me-2"></i>Download PDF
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            </div>

            <style>
                .receipt-container {
                    background: white;
                    border-radius: 15px;
                    padding: 40px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                    margin-bottom: 30px;
                }

                .receipt-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 30px;
                    border-bottom: 3px solid #f0f0f0;
                }

                .receipt-title {
                    text-align: center;
                }

                .receipt-title h2 {
                    margin-top: 15px;
                    color: #333;
                    font-weight: 700;
                }

                .receipt-content {
                    margin-bottom: 30px;
                }

                .receipt-section {
                    margin-bottom: 25px;
                    padding: 20px;
                    background: #f9f9f9;
                    border-radius: 10px;
                    border-left: 4px solid #06B6D4;
                }

                .section-title {
                    color: #333;
                    font-weight: 700;
                    margin-bottom: 15px;
                    font-size: 16px;
                }

                .receipt-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #e0e0e0;
                }

                .receipt-row:last-child {
                    border-bottom: none;
                }

                .receipt-row .label {
                    color: #666;
                    font-weight: 500;
                }

                .receipt-row .value {
                    color: #333;
                    font-weight: 600;
                }

                .receipt-info-box {
                    background: #e8f5e9;
                    border: 2px solid #4caf50;
                    border-radius: 10px;
                    padding: 15px;
                    margin-bottom: 20px;
                    color: #2e7d32;
                }

                .receipt-info-box p {
                    margin: 0;
                    font-size: 14px;
                }

                .receipt-actions {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .receipt-actions .btn {
                    padding: 12px 25px;
                    border-radius: 8px;
                    font-weight: 600;
                }

                @media print {
                    .receipt-actions, .navbar, .alert, .program-cards {
                        display: none;
                    }
                    body {
                        background: white;
                    }
                    .receipt-container {
                        box-shadow: none;
                        padding: 0;
                    }
                }
            </style>

            <script>
                function printReceipt() {
                    window.print();
                }

                function downloadReceipt() {
                    // Simple download as HTML (enhanced can use jsPDF)
                    alert('Receipt download feature coming soon! Currently you can use Print and save as PDF.');
                }
            </script>

            <?php 
                // Clear receipt data after display
                unset($_SESSION['receipt_data']);
            ?>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Program Selection Cards -->
        <div class="program-cards" data-aos="fade-up">
            <!-- Class 9 Card -->
            <div class="program-card" onclick="selectProgram('Class 9', 825)">
                <div class="program-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3 class="program-title">Class 9</h3>
                <div class="program-fee">৳825 <small>/month</small></div>
                <p class="text-muted">Science • Humanities • Commerce</p>
                <div class="program-badge">Popular</div>
            </div>

            <!-- Class 10 Card -->
            <div class="program-card" onclick="selectProgram('Class 10', 978)">
                <div class="program-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3 class="program-title">Class 10</h3>
                <div class="program-fee">৳978 <small>/month</small></div>
                <p class="text-muted">Science • Humanities • Commerce</p>
            </div>

            <!-- SSC Batch Card -->
            <div class="program-card" onclick="selectProgram('SSC Batch', 1150)">
                <div class="program-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="program-title">SSC Batch 2026</h3>
                <div class="program-fee">৳1150 <small>/month</small></div>
                <p class="text-muted">Science • Humanities • Commerce</p>
                <div class="program-badge">Special</div>
            </div>
        </div>

        <!-- Group Selection Buttons (Initially Hidden) -->
        <div id="groupSelection" style="display: none;" data-aos="fade-up">
            <h3 class="section-title"><i class="fas fa-users me-2"></i>Select Your Group</h3>
            <div class="group-buttons">
                <div class="group-btn science" onclick="selectGroup('Science')">
                    <i class="fas fa-flask fa-2x"></i>
                    <h5>Science</h5>
                    <small>Physics, Chemistry, Biology, Higher Math</small>
                </div>
                <div class="group-btn humanities" onclick="selectGroup('Humanities')">
                    <i class="fas fa-landmark fa-2x"></i>
                    <h5>Humanities</h5>
                    <small>History, Geography, Civics, Economics</small>
                </div>
                <div class="group-btn commerce" onclick="selectGroup('Commerce')">
                    <i class="fas fa-chart-line fa-2x"></i>
                    <h5>Commerce</h5>
                    <small>Accounting, Business Studies, Finance</small>
                </div>
            </div>
        </div>

        <!-- Selected Fee Display -->
        <div id="selectedFeeDisplay" class="selected-fee" style="display: none;">
            <p class="mb-0">Selected Program: <span id="selectedProgramName"></span></p>
            <p class="mb-0">Monthly Fee: <span class="amount" id="selectedFeeAmount">৳0</span></p>
            <small class="text-muted">Application fee: ৳500 (one time)</small>
        </div>

        <!-- Admission Form -->
        <div class="admission-card" id="admissionFormCard" style="display: none;">
            <div class="admission-header">
                <h2><i class="fas fa-door-open me-2"></i>Complete Your Admission</h2>
                <p>Fill the form below to secure your seat</p>
                <div class="fee-badge">
                    <i class="fas fa-tag me-2"></i>Application Fee: ৳<?php echo $application_fee; ?>
                </div>
            </div>

            <div class="admission-body">
                <form method="POST" action="" id="admissionForm" onsubmit="return validateForm()">
                    <input type="hidden" name="program" id="selectedProgram">
                    <input type="hidden" name="group" id="selectedGroup">
                    <input type="hidden" name="monthly_fee" id="monthlyFee">

                    <!-- Personal Information Section -->
                    <h3 class="section-title"><i class="fas fa-user me-2"></i>Personal Information</h3>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6" data-aos="fade-right">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        
                        <div class="col-md-6" data-aos="fade-left">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6" data-aos="fade-right">
                            <label class="form-label">Mobile Number *</label>
                            <div class="input-group">
                                <span class="input-group-text">+88</span>
                                <input type="tel" class="form-control" id="mobile" name="mobile" 
                                       pattern="01[3-9][0-9]{8}" placeholder="01XXXXXXXXX" required>
                            </div>
                        </div>

                        <div class="col-md-6" data-aos="fade-left">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                            <small class="text-muted">Your email is used for communication only.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12" data-aos="fade-left">
                            <label class="form-label">Address *</label>
                            <textarea class="form-control" name="address" rows="1" required></textarea>
                        </div>
                    </div>

                    <!-- Parent Information Section -->
                    <h3 class="section-title"><i class="fas fa-users me-2"></i>Parent Information</h3>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-12" data-aos="fade-right">
                            <label class="form-label">Parent/Guardian Name *</label>
                            <input type="text" class="form-control" name="parent_name" required>
                            <small class="text-muted">Full name of parent or guardian</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6" data-aos="fade-right">
                            <label class="form-label">Parent Email Address * <span class="badge bg-warning text-dark">For Login</span></label>
                            <input type="email" class="form-control" name="parent_email" required>
                            <small class="text-muted">This email will be used for parent account login</small>
                        </div>
                        
                        <div class="col-md-6" data-aos="fade-left">
                            <label class="form-label">Parent Phone Number *</label>
                            <input type="tel" class="form-control" name="parent_phone" pattern="[0-9+\-\s]+" placeholder="+880XXXXXXXXXX" required>
                            <small class="text-muted">Mobile or landline number</small>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <h3 class="section-title"><i class="fas fa-credit-card me-2"></i>Payment Information</h3>
                    
                    <div class="payment-info" data-aos="zoom-in">
                        <h4><i class="fas fa-info-circle me-2"></i>Fee Summary</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-2">Monthly Fee:</p>
                                <p class="mb-2">Application Fee:</p>
                                <p class="mb-2">VAT (5%):</p>
                                <p class="mb-2"><strong>Total Payable:</strong></p>
                            </div>
                            <div class="col-md-8 text-end">
                                <p class="mb-2" id="displayMonthlyFee">৳ 0</p>
                                <p class="mb-2">৳ <?php echo number_format($application_fee, 2); ?></p>
                                <p class="mb-2" id="displayVat">৳ <?php echo number_format($application_fee * 0.05, 2); ?></p>
                                <p class="mb-2"><strong id="displayTotal">৳ <?php echo number_format($application_fee + ($application_fee * 0.05), 2); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <label class="form-label">Select Payment Method *</label>
                    <div class="payment-methods">
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="100">
                            <div class="payment-method-card" onclick="selectPaymentMethod('bkash')">
                                <img src="uploads/download (1).png" alt="bKash">
                                <div class="method-name">bKash</div>
                            </div>
                        </div>
                        
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="200">
                            <div class="payment-method-card" onclick="selectPaymentMethod('nagad')">
                                <img src="uploads/download (2).png" alt="Nagad">
                                <div class="method-name">Nagad</div>
                            </div>
                        </div>
                        
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="300">
                            <div class="payment-method-card" onclick="selectPaymentMethod('rocket')">
                                <img src="uploads/download (3).png" alt="Rocket">
                                <div class="method-name">Rocket</div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="payment_method" id="payment_method" required>

                    <!-- Transaction Details -->
                    <div class="transaction-field" id="transactionField" style="display: none;" data-aos="fade-up">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Send payment to:</strong></p>
                                <p id="paymentNumber">bKash: 01305421948</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Reference:</strong> Use your mobile number</p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Transaction ID *</label>
                            <input type="text" class="form-control" name="transaction_id" id="transaction_id" 
                                   placeholder="Enter transaction ID from your mobile banking app" required>
                            <small class="text-muted">Example: BK1234567890, NG987654321</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sender Number *</label>
                            <input type="tel" class="form-control" name="sender_number" id="sender_number" 
                                   pattern="01[3-9][0-9]{8}" placeholder="01XXXXXXXXX" required>
                            <small class="text-muted">Enter the mobile number used to send payment.</small>
                        </div>
                    </div>

                    <!-- Declaration -->
                    <div class="declaration" data-aos="fade-up">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="declaration" id="declaration" required>
                            <label class="form-check-label" for="declaration">
                                <strong>I confirm that all information provided is accurate and complete.</strong> I understand that any false information may lead to cancellation of admission.
                            </label>
                        </div>
                    </div>

                    <!-- Submit & Reset Buttons -->
                    <div class="row">
                        <div class="col-md-6" data-aos="fade-right">
                            <button type="submit" name="submit_admission" class="btn-submit">
                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                        </div>
                        <div class="col-md-6" data-aos="fade-left">
                            <button type="button" class="btn-reset" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Selected program and fee
        let selectedProgram = '';
        let selectedFee = 0;
        let selectedGroup = '';

        // Select Program
        function selectProgram(program, fee) {
            // Remove selection from all cards
            document.querySelectorAll('.program-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            event.currentTarget.classList.add('selected');
            
            // Store selected values
            selectedProgram = program;
            selectedFee = fee;
            
            // Show group selection
            document.getElementById('groupSelection').style.display = 'block';
            
            // Scroll to group selection
            document.getElementById('groupSelection').scrollIntoView({ behavior: 'smooth' });
            
            // Update hidden inputs
            document.getElementById('selectedProgram').value = program;
            document.getElementById('monthlyFee').value = fee;
            
            // Update fee display
            document.getElementById('selectedProgramName').textContent = program;
            document.getElementById('selectedFeeAmount').textContent = '৳' + fee;
            document.getElementById('selectedFeeDisplay').style.display = 'block';
            
            // Update payment info
            updatePaymentInfo(fee);
        }

        // Select Group
        function selectGroup(group) {
            // Remove selection from all group buttons
            document.querySelectorAll('.group-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Add selection to clicked button
            event.currentTarget.classList.add('selected');
            
            // Store selected group
            selectedGroup = group;
            document.getElementById('selectedGroup').value = group;
            
            // Show admission form
            document.getElementById('admissionFormCard').style.display = 'block';
            
            // Scroll to form
            document.getElementById('admissionFormCard').scrollIntoView({ behavior: 'smooth' });
        }

        // Update payment info based on selected program
        function updatePaymentInfo(fee) {
            const monthlyFee = fee;
            const appFee = <?php echo $application_fee; ?>;
            const vat = appFee * 0.05;
            const total = appFee + vat;
            
            document.getElementById('displayMonthlyFee').textContent = '৳ ' + monthlyFee.toFixed(2);
            document.getElementById('displayVat').textContent = '৳ ' + vat.toFixed(2);
            document.getElementById('displayTotal').innerHTML = '<strong>৳ ' + (monthlyFee + total).toFixed(2) + '</strong>';
        }

        // Select payment method
        function selectPaymentMethod(method) {
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.getElementById('payment_method').value = method;
            
            const paymentNumber = document.getElementById('paymentNumber');
            if(method === 'bkash') {
                paymentNumber.innerHTML = 'bKash: 01305421948';
            } else if(method === 'nagad') {
                paymentNumber.innerHTML = 'Nagad: 01305421948';
            } else if(method === 'rocket') {
                paymentNumber.innerHTML = 'Rocket: 01305421948';
            }
            
            document.getElementById('transactionField').style.display = 'block';
        }

        // Form validation
        function validateForm() {
            if(!selectedProgram) {
                alert('Please select a program first');
                return false;
            }
            
            if(!selectedGroup) {
                alert('Please select your group');
                return false;
            }
            
            if(!document.getElementById('payment_method').value) {
                alert('Please select a payment method');
                return false;
            }
            
            if(!document.getElementById('transaction_id').value) {
                alert('Please enter transaction ID');
                return false;
            }
            
            if(!document.getElementById('declaration').checked) {
                alert('Please confirm that all information is accurate');
                return false;
            }
            
            return true;
        }

        // Reset form
        function resetForm() {
            if(confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                location.reload();
            }
        }
    </script>
</body>
</html>