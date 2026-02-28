<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();
checkRole(['admin']);

// Get SMS balance (you can store this in settings table)
$sms_balance = 1500; // Example balance

// Get SMS history
$sms_history = mysqli_query($conn, "SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 50");

// Get templates
$templates = [
    'fee_reminder' => 'Dear [NAME], your fee of [AMOUNT] Tk is due on [DATE]. Please pay soon to avoid late fee. - Coaching Center',
    'exam_schedule' => 'Dear [NAME], your [EXAM_NAME] exam will be held on [DATE] at [TIME]. Subject: [SUBJECT]. - Coaching Center',
    'result_published' => 'Dear [NAME], your [EXAM_NAME] results have been published. Your GPA: [GPA]. Check details online. - Coaching Center',
    'attendance_alert' => 'Dear Parent, your child [STUDENT_NAME] was [STATUS] today. Please ensure regular attendance. - Coaching Center',
    'holiday_notice' => 'Dear Students/Parents, the center will remain closed on [DATE] due to [REASON]. - Coaching Center',
    'emergency' => 'URGENT: [MESSAGE]. Please take necessary action. - Coaching Center'
];

// Get classes for bulk SMS
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name, section");

// Get students for individual SMS
$students = mysqli_query($conn, "SELECT s.*, c.class_name 
                                  FROM students s 
                                  JOIN classes c ON s.class_id = c.id 
                                  WHERE s.status = 1 
                                  ORDER BY s.first_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS System - CoachingPro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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

        /* SMS Balance Card */
        .balance-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .balance-card::after {
            content: '📱';
            position: absolute;
            right: 20px;
            bottom: 10px;
            font-size: 60px;
            opacity: 0.2;
        }

        .balance-amount {
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0;
        }

        .balance-label {
            font-size: 16px;
            opacity: 0.9;
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

        /* SMS Type Tabs */
        .sms-type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .sms-type-tab {
            padding: 12px 25px;
            border-radius: 10px;
            background: #f8f9fa;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .sms-type-tab:hover {
            background: #e9ecef;
        }

        .sms-type-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #fff;
        }

        .sms-type-tab i {
            margin-right: 8px;
        }

        /* Form Styles */
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

        .form-control[readonly] {
            background-color: #f8f9fa;
        }

        /* Template Buttons */
        .template-btn {
            padding: 8px 15px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            font-size: 13px;
            transition: all 0.3s;
            cursor: pointer;
            margin: 0 5px 5px 0;
        }

        .template-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        /* Character Counter */
        .char-counter {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .char-counter.warning {
            color: #f39c12;
        }

        .char-counter.danger {
            color: #e74c3c;
        }

        /* Recipients List */
        .recipients-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }

        .recipient-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .recipient-item:last-child {
            border-bottom: none;
        }

        .recipient-checkbox {
            margin-right: 10px;
        }

        .recipient-info {
            flex: 1;
        }

        .recipient-name {
            font-weight: 500;
            color: #333;
        }

        .recipient-phone {
            font-size: 12px;
            color: #666;
        }

        /* Send Button */
        .btn-send {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* SMS History Table */
        .sms-history-table {
            font-size: 14px;
        }

        .sms-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .sms-status.sent {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .sms-status.failed {
            background: #ffebee;
            color: #c62828;
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
                <a href="result-system.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Result System</span>
                </a>
                <a href="fees-management.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Fees Management</span>
                </a>
                <a href="sms-system.php" class="menu-item active">
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
                    <h4>SMS Communication System</h4>
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

            <!-- SMS Balance Card -->
            <div class="balance-card">
                <h5><i class="fas fa-wallet me-2"></i>SMS Balance</h5>
                <div class="balance-amount"><?php echo number_format($sms_balance); ?></div>
                <div class="balance-label">Available SMS Credits</div>
                <small class="opacity-75">Last updated: <?php echo date('d-m-Y H:i'); ?></small>
            </div>

            <!-- SMS Type Tabs -->
            <div class="sms-type-tabs">
                <div class="sms-type-tab active" onclick="showSMSType('individual')">
                    <i class="fas fa-user"></i> Individual SMS
                </div>
                <div class="sms-type-tab" onclick="showSMSType('class')">
                    <i class="fas fa-users"></i> Class-wise SMS
                </div>
                <div class="sms-type-tab" onclick="showSMSType('bulk')">
                    <i class="fas fa-globe"></i> Bulk SMS
                </div>
                <div class="sms-type-tab" onclick="showSMSType('custom')">
                    <i class="fas fa-phone-alt"></i> Custom Numbers
                </div>
            </div>

            <!-- SMS Form Container -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-pen me-2"></i>Compose SMS</h5>
                </div>

                <!-- Individual SMS Form -->
                <div id="individual-sms" class="sms-form" style="display: block;">
                    <form id="smsForm" onsubmit="sendSMS(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Student</label>
                                <select class="form-select select2" id="student_select" required>
                                    <option value="">Choose Student</option>
                                    <?php 
                                    mysqli_data_seek($students, 0);
                                    while($student = mysqli_fetch_assoc($students)): 
                                    ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                                data-phone="<?php echo $student['phone']; ?>"
                                                data-name="<?php echo $student['first_name'] . ' ' . $student['last_name']; ?>">
                                            <?php echo $student['first_name'] . ' ' . $student['last_name']; ?> 
                                            (<?php echo $student['class_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" readonly>
                            </div>
                        </div>

                        <!-- Template Buttons -->
                        <div class="mb-3">
                            <label class="form-label">Quick Templates</label>
                            <div>
                                <?php foreach($templates as $key => $template): ?>
                                    <span class="template-btn" onclick="useTemplate('<?php echo $key; ?>')">
                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="message" rows="5" maxlength="1000" 
                                      onkeyup="countCharacters()" required></textarea>
                            <div class="char-counter" id="charCounter">0/1000 characters (0 SMS)</div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="schedule_later">
                                    <label class="form-check-label">Schedule for later</label>
                                </div>
                            </div>
                            <div class="col-md-4" id="schedule_datetime" style="display: none;">
                                <input type="datetime-local" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn-send" id="sendBtn">
                            <i class="fas fa-paper-plane me-2"></i>Send SMS
                        </button>
                    </form>
                </div>

                <!-- Class-wise SMS Form (Initially hidden) -->
                <div id="class-sms" class="sms-form" style="display: none;">
                    <form id="classSMSForm" onsubmit="sendClassSMS(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Class</label>
                                <select class="form-select" id="class_select" required>
                                    <option value="">Choose Class</option>
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Students</label>
                                <input type="text" class="form-control" id="total_students" readonly value="0">
                            </div>
                        </div>

                        <!-- Template Buttons (same as above) -->
                        <div class="mb-3">
                            <label class="form-label">Quick Templates</label>
                            <div>
                                <?php foreach($templates as $key => $template): ?>
                                    <span class="template-btn" onclick="useClassTemplate('<?php echo $key; ?>')">
                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="class_message" rows="5" maxlength="1000" 
                                      onkeyup="countClassCharacters()" required></textarea>
                            <div class="char-counter" id="classCharCounter">0/1000 characters (0 SMS)</div>
                        </div>

                        <button type="submit" class="btn-send">
                            <i class="fas fa-paper-plane me-2"></i>Send to Class
                        </button>
                    </form>
                </div>

                <!-- Bulk SMS Form -->
                <div id="bulk-sms" class="sms-form" style="display: none;">
                    <form id="bulkSMSForm" onsubmit="sendBulkSMS(event)">
                        <div class="mb-3">
                            <label class="form-label">Recipient Groups</label>
                            <div class="recipients-list">
                                <div class="recipient-item">
                                    <input class="form-check-input recipient-checkbox" type="checkbox" value="all_students" id="all_students">
                                    <div class="recipient-info">
                                        <div class="recipient-name">All Students</div>
                                        <div class="recipient-phone">Send to all active students</div>
                                    </div>
                                </div>
                                <div class="recipient-item">
                                    <input class="form-check-input recipient-checkbox" type="checkbox" value="all_teachers" id="all_teachers">
                                    <div class="recipient-info">
                                        <div class="recipient-name">All Teachers</div>
                                        <div class="recipient-phone">Send to all active teachers</div>
                                    </div>
                                </div>
                                <div class="recipient-item">
                                    <input class="form-check-input recipient-checkbox" type="checkbox" value="fee_pending" id="fee_pending">
                                    <div class="recipient-info">
                                        <div class="recipient-name">Students with Pending Fees</div>
                                        <div class="recipient-phone">Send fee reminders</div>
                                    </div>
                                </div>
                                <div class="recipient-item">
                                    <input class="form-check-input recipient-checkbox" type="checkbox" value="exam_soon" id="exam_soon">
                                    <div class="recipient-info">
                                        <div class="recipient-name">Students with Upcoming Exams</div>
                                        <div class="recipient-phone">Send exam reminders</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="bulk_message" rows="5" maxlength="1000" 
                                      onkeyup="countBulkCharacters()" required></textarea>
                            <div class="char-counter" id="bulkCharCounter">0/1000 characters (0 SMS)</div>
                        </div>

                        <button type="submit" class="btn-send">
                            <i class="fas fa-paper-plane me-2"></i>Send Bulk SMS
                        </button>
                    </form>
                </div>

                <!-- Custom Numbers SMS Form -->
                <div id="custom-sms" class="sms-form" style="display: none;">
                    <form id="customSMSForm" onsubmit="sendCustomSMS(event)">
                        <div class="mb-3">
                            <label class="form-label">Phone Numbers (comma separated)</label>
                            <textarea class="form-control" rows="3" placeholder="e.g., 01712345678, 01812345678" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" rows="5" maxlength="1000" required></textarea>
                            <div class="char-counter">0/1000 characters</div>
                        </div>

                        <button type="submit" class="btn-send">
                            <i class="fas fa-paper-plane me-2"></i>Send to Custom Numbers
                        </button>
                    </form>
                </div>
            </div>

            <!-- SMS History -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>SMS History</h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshHistory()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover sms-history-table" id="smsHistory">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Recipient</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($sms_history) > 0): ?>
                                <?php while($log = mysqli_fetch_assoc($sms_history)): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y H:i', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo $log['mobile_number']; ?></td>
                                    <td><?php echo substr($log['message'], 0, 50) . '...'; ?></td>
                                    <td><?php echo $log['type']; ?></td>
                                    <td>
                                        <span class="sms-status <?php echo strtolower($log['status']); ?>">
                                            <?php echo $log['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No SMS history found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Search student...",
                allowClear: true
            });

            // Initialize DataTable
            $('#smsHistory').DataTable({
                "pageLength": 10,
                "ordering": true,
                "info": true,
                "searching": true,
                "lengthChange": false
            });

            // Load student phone number
            $('#student_select').change(function() {
                var phone = $(this).find(':selected').data('phone');
                $('#phone_number').val(phone);
            });
        });

        // Show SMS type forms
        function showSMSType(type) {
            // Update tab styling
            $('.sms-type-tab').removeClass('active');
            event.target.closest('.sms-type-tab').classList.add('active');
            
            // Hide all forms
            $('.sms-form').hide();
            
            // Show selected form
            $('#' + type + '-sms').show();
        }

        // Character counting for individual SMS
        function countCharacters() {
            var message = $('#message').val();
            var length = message.length;
            var smsCount = Math.ceil(length / 160);
            
            $('#charCounter').text(length + '/1000 characters (' + smsCount + ' SMS)');
            
            if(length > 900) {
                $('#charCounter').addClass('danger').removeClass('warning');
            } else if(length > 700) {
                $('#charCounter').addClass('warning').removeClass('danger');
            } else {
                $('#charCounter').removeClass('warning danger');
            }
        }

        // Use template for individual SMS
        function useTemplate(templateKey) {
            var templates = <?php echo json_encode($templates); ?>;
            var message = templates[templateKey];
            
            // Replace placeholders with sample data
            var studentName = $('#student_select').find(':selected').data('name') || '[NAME]';
            message = message.replace('[NAME]', studentName);
            
            $('#message').val(message);
            countCharacters();
        }

        // Send individual SMS
        function sendSMS(event) {
            event.preventDefault();
            
            var studentId = $('#student_select').val();
            var phone = $('#phone_number').val();
            var message = $('#message').val();
            
            if(!studentId || !phone || !message) {
                alert('Please fill all fields');
                return;
            }
            
            // Show loading state
            $('#sendBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            
            // AJAX call to send SMS
            $.ajax({
                url: 'send-sms.php',
                type: 'POST',
                data: {
                    type: 'individual',
                    student_id: studentId,
                    phone: phone,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('SMS sent successfully!');
                        $('#smsForm')[0].reset();
                        refreshHistory();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error sending SMS. Please try again.');
                },
                complete: function() {
                    $('#sendBtn').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send SMS');
                }
            });
        }

        // Load class students count
        $('#class_select').change(function() {
            var classId = $(this).val();
            
            if(classId) {
                $.ajax({
                    url: 'get-class-students-count.php',
                    type: 'POST',
                    data: {class_id: classId},
                    success: function(count) {
                        $('#total_students').val(count + ' students');
                    }
                });
            }
        });

        // Count characters for class SMS
        function countClassCharacters() {
            var message = $('#class_message').val();
            var length = message.length;
            var smsCount = Math.ceil(length / 160);
            $('#classCharCounter').text(length + '/1000 characters (' + smsCount + ' SMS)');
        }

        // Use template for class SMS
        function useClassTemplate(templateKey) {
            var templates = <?php echo json_encode($templates); ?>;
            var message = templates[templateKey];
            
            var className = $('#class_select').find(':selected').text() || '[CLASS]';
            message = message.replace('[NAME]', 'students of ' + className);
            
            $('#class_message').val(message);
            countClassCharacters();
        }

        // Send class SMS
        function sendClassSMS(event) {
            event.preventDefault();
            
            var classId = $('#class_select').val();
            var message = $('#class_message').val();
            
            if(!classId || !message) {
                alert('Please select class and enter message');
                return;
            }
            
            if(!confirm('This will send SMS to all students in this class. Continue?')) {
                return;
            }
            
            $.ajax({
                url: 'send-sms.php',
                type: 'POST',
                data: {
                    type: 'class',
                    class_id: classId,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('SMS sent to ' + response.count + ' students!');
                        refreshHistory();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }

        // Count characters for bulk SMS
        function countBulkCharacters() {
            var message = $('#bulk_message').val();
            var length = message.length;
            var smsCount = Math.ceil(length / 160);
            $('#bulkCharCounter').text(length + '/1000 characters (' + smsCount + ' SMS)');
        }

        // Send bulk SMS
        function sendBulkSMS(event) {
            event.preventDefault();
            
            var groups = [];
            $('.recipient-checkbox:checked').each(function() {
                groups.push($(this).val());
            });
            
            var message = $('#bulk_message').val();
            
            if(groups.length === 0) {
                alert('Please select at least one recipient group');
                return;
            }
            
            if(!message) {
                alert('Please enter message');
                return;
            }
            
            if(!confirm('This will send SMS to all selected groups. Continue?')) {
                return;
            }
            
            $.ajax({
                url: 'send-sms.php',
                type: 'POST',
                data: {
                    type: 'bulk',
                    groups: groups,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('Bulk SMS sent to ' + response.count + ' recipients!');
                        refreshHistory();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }

        // Send custom SMS
        function sendCustomSMS(event) {
            event.preventDefault();
            var numbers = $(event.target).find('textarea').first().val();
            var message = $(event.target).find('textarea').last().val();
            
            if(!numbers || !message) {
                alert('Please enter numbers and message');
                return;
            }
            
            $.ajax({
                url: 'send-sms.php',
                type: 'POST',
                data: {
                    type: 'custom',
                    numbers: numbers,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('SMS sent successfully!');
                        refreshHistory();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }

        // Schedule later toggle
        $('#schedule_later').change(function() {
            if($(this).is(':checked')) {
                $('#schedule_datetime').show();
            } else {
                $('#schedule_datetime').hide();
            }
        });

        // Refresh SMS history
        function refreshHistory() {
            location.reload();
        }
    </script>
</body>
</html>