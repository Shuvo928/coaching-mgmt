<?php
session_start();
require_once 'includes/db.php';

// Handle OTP sending
if(isset($_POST['send_otp'])) {
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    
    // Generate 6-digit OTP
    $otp = rand(100000, 999999);
    
    // Store OTP in session
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_mobile'] = $mobile;
    $_SESSION['otp_time'] = time();
    
    // Here you would integrate SMS API to send OTP
    // For demo, we'll just show the OTP
    $response = ['success' => true, 'message' => 'OTP sent successfully', 'otp' => $otp];
    echo json_encode($response);
    exit();
}

// Verify OTP
if(isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp'];
    
    if(isset($_SESSION['otp']) && $entered_otp == $_SESSION['otp'] && (time() - $_SESSION['otp_time']) < 300) {
        $_SESSION['otp_verified'] = true;
        $response = ['success' => true, 'message' => 'OTP verified successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Invalid or expired OTP'];
    }
    echo json_encode($response);
    exit();
}

// Handle form submission
if(isset($_POST['submit_admission'])) {
    if(!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        $error = "Please verify your mobile number first";
    } else {
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $program = mysqli_real_escape_string($conn, $_POST['program']);
        $group = mysqli_real_escape_string($conn, $_POST['group']);
        $transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        
        // Insert into database
        $query = "INSERT INTO admission_applications (full_name, gender, mobile, email, address, program, `group`, transaction_id, payment_method, application_fee, status, created_at) 
                  VALUES ('$full_name', '$gender', '$mobile', '$email', '$address', '$program', '$group', '$transaction_id', '$payment_method', 500, 'Pending', NOW())";
        
        if(mysqli_query($conn, $query)) {
            $application_id = mysqli_insert_id($conn);
            $_SESSION['success'] = "Application submitted successfully! Your Application ID: APP" . str_pad($application_id, 5, '0', STR_PAD_LEFT);
            unset($_SESSION['otp_verified']);
            unset($_SESSION['otp']);
        } else {
            $error = "Error submitting application: " . mysqli_error($conn);
        }
    }
}

// Application fee
$application_fee = 500;
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
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .admission-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            animation: slideUp 0.5s ease;
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

        .admission-header p {
            opacity: 0.9;
            margin: 0;
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

        .payment-info h4 {
            color: white;
            margin-bottom: 15px;
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

        .payment-method-card .method-name {
            font-weight: 600;
            color: var(--text-dark);
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

        .form-check-input:checked {
            background-color: var(--cyan);
            border-color: var(--cyan);
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

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .timer {
            font-size: 0.9rem;
            color: #ef4444;
        }

        @media (max-width: 768px) {
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
                    <li class="nav-item">
                        <a class="nav-link" href="#programs">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="admission-container" data-aos="fade-up">
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

        <div class="admission-card">
            <div class="admission-header">
                <h2><i class="fas fa-door-open me-2"></i>Online Admission Portal</h2>
                <p>Join CoachingPro for your academic excellence journey</p>
                <div class="fee-badge">
                    <i class="fas fa-tag me-2"></i>Application Fee: ৳<?php echo $application_fee; ?>
                </div>
            </div>

            <div class="admission-body">
                <form method="POST" action="" id="admissionForm" onsubmit="return validateForm()">
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

                    <!-- Mobile with OTP Verification -->
                    <div class="otp-section" id="otpSection" data-aos="fade-up">
                        <label class="form-label">Mobile Number *</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text">+88</span>
                            <input type="tel" class="form-control" id="mobile" name="mobile" 
                                   pattern="01[3-9][0-9]{8}" placeholder="01XXXXXXXXX" required>
                            <button class="btn btn-outline-primary" type="button" id="sendOtpBtn" onclick="sendOTP()">
                                <i class="fas fa-paper-plane me-2"></i>Send OTP
                            </button>
                        </div>
                        
                        <div id="otpVerification" style="display: none;">
                            <div class="input-group">
                                <input type="text" class="form-control" id="otp" placeholder="Enter 6-digit OTP" maxlength="6">
                                <button class="btn btn-success" type="button" onclick="verifyOTP()">
                                    <i class="fas fa-check me-2"></i>Verify
                                </button>
                            </div>
                            <div class="mt-2">
                                <span class="timer" id="timer"></span>
                                <button type="button" class="btn btn-link btn-sm" onclick="resendOTP()">Resend OTP</button>
                            </div>
                        </div>
                        
                        <div id="verifiedBadge" style="display: none;" class="mt-2 verified-badge">
                            <i class="fas fa-check-circle me-2"></i>Mobile number verified
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6" data-aos="fade-right">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="col-md-6" data-aos="fade-left">
                            <label class="form-label">Address *</label>
                            <textarea class="form-control" name="address" rows="1" required></textarea>
                        </div>
                    </div>

                    <!-- Program Selection -->
                    <h3 class="section-title"><i class="fas fa-graduation-cap me-2"></i>Program Selection</h3>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6" data-aos="fade-right">
                            <label class="form-label">Select Program *</label>
                            <select class="form-select" name="program" id="program" required onchange="updateGroups()">
                                <option value="">Choose Program</option>
                                <option value="Class 9">Class 9</option>
                                <option value="Class 10">Class 10</option>
                                <option value="SSC Batch">SSC Batch</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6" data-aos="fade-left">
                            <label class="form-label">Select Group *</label>
                            <select class="form-select" name="group" id="group" required>
                                <option value="">First select program</option>
                            </select>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <h3 class="section-title"><i class="fas fa-credit-card me-2"></i>Payment Information</h3>
                    
                    <div class="payment-info" data-aos="zoom-in">
                        <h4><i class="fas fa-info-circle me-2"></i>Application Fee Details</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">Application Fee:</p>
                                <p class="mb-2">VAT (5%):</p>
                                <p class="mb-2"><strong>Total Amount:</strong></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-2">৳ <?php echo number_format($application_fee - ($application_fee * 0.05), 2); ?></p>
                                <p class="mb-2">৳ <?php echo number_format($application_fee * 0.05, 2); ?></p>
                                <p class="mb-2"><strong>৳ <?php echo number_format($application_fee, 2); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <label class="form-label">Select Payment Method *</label>
                    <div class="payment-methods">
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="100">
                            <div class="payment-method-card" onclick="selectPaymentMethod('bkash')">
                                <img src="https://seeklogo.com/images/B/bkash-logo-4D6434A1D9-seeklogo.com.png" alt="bKash">
                                <div class="method-name">bKash</div>
                            </div>
                        </div>
                        
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="200">
                            <div class="payment-method-card" onclick="selectPaymentMethod('nagad')">
                                <img src="https://seeklogo.com/images/N/nagad-logo-4A9E1C3E2F-seeklogo.com.png" alt="Nagad">
                                <div class="method-name">Nagad</div>
                            </div>
                        </div>
                        
                        <div class="payment-method" data-aos="fade-up" data-aos-delay="300">
                            <div class="payment-method-card" onclick="selectPaymentMethod('rocket')">
                                <img src="https://seeklogo.com/images/R/rocket-dutch-bangla-bank-logo-5B6B7C8D9E-seeklogo.com.png" alt="Rocket">
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
                                <p id="paymentNumber">bKash: 019XXXXXXXX</p>
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
                            <button type="reset" class="btn-reset" onclick="resetForm()">
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

        // OTP Variables
        let otpTimer;
        let timeLeft = 300; // 5 minutes in seconds

        // Send OTP
        function sendOTP() {
            const mobile = document.getElementById('mobile').value;
            const mobilePattern = /^01[3-9][0-9]{8}$/;
            
            if(!mobilePattern.test(mobile)) {
                alert('Please enter a valid Bangladeshi mobile number (e.g., 01712345678)');
                return;
            }
            
            // Disable send button and show loading
            const sendBtn = document.getElementById('sendOtpBtn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    send_otp: true,
                    mobile: mobile
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('OTP sent successfully! For demo, OTP is: ' + response.otp);
                        document.getElementById('otpVerification').style.display = 'block';
                        startTimer(300);
                    } else {
                        alert('Failed to send OTP. Please try again.');
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send OTP';
                    }
                },
                error: function() {
                    alert('Error sending OTP. Please try again.');
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send OTP';
                }
            });
        }

        // Verify OTP
        function verifyOTP() {
            const otp = document.getElementById('otp').value;
            
            if(otp.length !== 6 || isNaN(otp)) {
                alert('Please enter a valid 6-digit OTP');
                return;
            }
            
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    verify_otp: true,
                    otp: otp
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        document.getElementById('otpSection').classList.add('otp-verified');
                        document.getElementById('otpVerification').style.display = 'none';
                        document.getElementById('verifiedBadge').style.display = 'block';
                        document.getElementById('mobile').readOnly = true;
                        clearInterval(otpTimer);
                    } else {
                        alert(response.message);
                    }
                }
            });
        }

        // Resend OTP
        function resendOTP() {
            sendOTP();
        }

        // Timer function
        function startTimer(duration) {
            timeLeft = duration;
            const timerDisplay = document.getElementById('timer');
            
            otpTimer = setInterval(function() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerDisplay.textContent = `OTP expires in: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if(timeLeft <= 0) {
                    clearInterval(otpTimer);
                    timerDisplay.textContent = 'OTP expired. Please request again.';
                    document.getElementById('sendOtpBtn').disabled = false;
                    document.getElementById('sendOtpBtn').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send OTP';
                }
                
                timeLeft--;
            }, 1000);
        }

        // Update groups based on program selection
        function updateGroups() {
            const program = document.getElementById('program').value;
            const groupSelect = document.getElementById('group');
            
            groupSelect.innerHTML = '<option value="">Select Group</option>';
            
            if(program === 'SSC Batch') {
                groupSelect.innerHTML += `
                    <option value="Science">Science</option>
                    <option value="Humanities">Humanities</option>
                    <option value="Commerce">Commerce</option>
                `;
            } else {
                groupSelect.innerHTML += '<option value="General">General</option>';
            }
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
                paymentNumber.innerHTML = 'bKash: 019XXXXXXXX (Merchant)';
            } else if(method === 'nagad') {
                paymentNumber.innerHTML = 'Nagad: 019XXXXXXXX (Merchant)';
            } else if(method === 'rocket') {
                paymentNumber.innerHTML = 'Rocket: 019XXXXXXXX (Merchant)';
            }
            
            document.getElementById('transactionField').style.display = 'block';
        }

        // Form validation
        function validateForm() {
            if(!document.getElementById('otp_verified') && !document.querySelector('.otp-verified')) {
                alert('Please verify your mobile number first');
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
                document.getElementById('admissionForm').reset();
                document.getElementById('otpSection').classList.remove('otp-verified');
                document.getElementById('otpVerification').style.display = 'none';
                document.getElementById('verifiedBadge').style.display = 'none';
                document.getElementById('mobile').readOnly = false;
                document.getElementById('transactionField').style.display = 'none';
                document.querySelectorAll('.payment-method-card').forEach(card => {
                    card.classList.remove('selected');
                });
                clearInterval(otpTimer);
            }
        }
    </script>
</body>
</html>