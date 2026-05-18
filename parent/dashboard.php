<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';
require_once '../includes/payment_helpers.php';

// Check if parent is logged in
if(!isset($_SESSION['parent_id'])) {
    header("Location: ../parent-login.php");
    exit();
}

$parent_id = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];
$student_name = $_SESSION['student_name'] ?? '';
$student_mobile = $_SESSION['student_mobile'] ?? '';

$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$firstStudent = getFirstParentStudent($conn, $parent_id, $student_mobile);
$student_id = $firstStudent['id'] ?? 0;
$student_name = $student_name ?: trim(($firstStudent['first_name'] ?? '') . ' ' . ($firstStudent['last_name'] ?? ''));

// If no student rows were found through the parent mapping, fall back to old admission application logic.
if (empty($student_ids) && !empty($student_mobile)) {
    $student_query = "SELECT id FROM students WHERE phone = '$student_mobile' LIMIT 1";
    $student_result = mysqli_query($conn, $student_query);
    $student_data = mysqli_fetch_assoc($student_result);
    $student_id = $student_data['id'] ?? 0;
}

$student_ids_list = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

$student_class_name = '';
$student_group_name = '';
$expected_class_group = '';
$class_routine = [];

if (!empty($student_id)) {
    $admissionPhoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
    $admissionHasMobile = ($admissionPhoneColumn && mysqli_num_rows($admissionPhoneColumn) > 0);
    $admissionJoin = $admissionHasMobile
        ? "COALESCE(NULLIF(aa.mobile, ''), aa.phone) = s.phone"
        : "aa.phone = s.phone";

    $routine_student_query = "SELECT s.*, c.class_name, COALESCE(aa.`group`, '') AS group_name \n"
        . "FROM students s \n"
        . "LEFT JOIN classes c ON s.class_id = c.id \n"
        . "LEFT JOIN admission_applications aa ON " . $admissionJoin . " \n"
        . "WHERE s.id = " . intval($student_id) . " LIMIT 1";
    $routine_student_result = mysqli_query($conn, $routine_student_query);
    $routine_student = mysqli_fetch_assoc($routine_student_result);

    if ($routine_student) {
        $student_class_name = $routine_student['class_name'] ?? '';
        $student_group_name = $routine_student['group_name'] ?? '';

        $class_number = '';
        if (!empty($student_class_name)) {
            if (stripos($student_class_name, 'SSC') !== false) {
                $class_number = 'SSC Batch';
            } else {
                preg_match('/Class\s+(\d+)/i', $student_class_name, $matches);
                if (!empty($matches[1])) {
                    $class_number = 'Class ' . $matches[1];
                }
            }
        }

        if (!empty($class_number) && !empty($student_group_name)) {
            $group_display = ucfirst(strtolower(trim($student_group_name)));
            if (stripos($group_display, 'group') === false) {
                $group_display .= ' Group';
            }
            $expected_class_group = $class_number . ' — ' . $group_display;
        }
    }
}

// Query routine from the routine table when child class/group is known
if (!empty($expected_class_group)) {
    $routine_query = "SELECT * FROM `routine` WHERE class_group = '" . mysqli_real_escape_string($conn, $expected_class_group) . "' "
        . "ORDER BY FIELD(day, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'), start_time";
    $routine_result = mysqli_query($conn, $routine_query);
    while ($routine = mysqli_fetch_assoc($routine_result)) {
        $class_routine[] = $routine;
    }
}

// Get pending fees for next 2 months only
$upcoming_fees = getUpcomingFeesForStudent($conn, $student_id, 2);

// Calculate total pending fees for next 2 months
$total_pending = 0;
foreach ($upcoming_fees as $fee) {
    $total_pending += (float)$fee['due_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - CoachingPro</title>
    
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
            transition: all 0.3s;
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

        .sidebar-header small {
            opacity: 0.9;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 0;
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
            font-size: 18px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .top-bar-title h2 {
            margin: 0;
            font-weight: 700;
            color: #333;
            font-size: 28px;
        }

        .top-bar-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .user-info small {
            color: #999;
            display: block;
        }

        .logout-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
        .stat-icon.green { background: #e8f5e9; color: #388e3c; }
        .stat-icon.orange { background: #fff3e0; color: #f57c00; }
        .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Student Info Card */
        .student-info-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .student-info-card h4 {
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .info-item strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
        }

        /* Quick Links */
        .quick-links {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .quick-links h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .quick-link {
            background: #f8f9fa;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .quick-link:hover {
            border-color: #667eea;
            background: #f0f4f9;
            color: #667eea;
        }

        .quick-link i {
            display: block;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .quick-link span {
            font-weight: 600;
            font-size: 14px;
            display: block;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="results.php">
                        <i class="fas fa-chart-bar"></i>
                        Check Results 
                    </a>
                </li>
                <li>
                    <a href="fees.php">
                        <i class="fas fa-money-bill"></i>
                        Fees & Payments
                    </a>
                </li>
                <li>
                    <a href="../parent-discontinue.php" onclick="return confirm('Are you sure you want to remove this account permanently?');">
                        <i class="fas fa-sign-out-alt"></i>
                         discontinues enrollment 
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
                <div class="top-bar-title">
                    <h2>Dashboard</h2>
                    <p>Welcome back, <?php echo htmlspecialchars($parent_name); ?>!</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <p><?php echo htmlspecialchars($parent_name); ?></p>
                        <small>Parent</small>
                    </div>
                    <a href="../parent-logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Announcements Section -->
            <div class="card p-4 mb-4" style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0" style="color: #2c3e66;"><i class="fas fa-bullhorn me-2" style="color: #ff9800;"></i>📢 Latest Announcements</h5>
                    <span class="badge bg-secondary" id="announcementCount">Loading...</span>
                </div>
                <div id="announcementsContainer" style="max-height: 400px; overflow-y: auto;">
                    <p class="text-muted text-center" id="loadingMsg">
                        <i class="fas fa-spinner fa-spin me-2"></i>Loading announcements...
                    </p>
                </div>
            </div>

            <script>
            // Fetch announcements for the child's class and group
            fetch('../api/get-announcements.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('announcementsContainer');
                const loadingMsg = document.getElementById('loadingMsg');
                const countBadge = document.getElementById('announcementCount');
                
                if (data.success && data.announcements.length > 0) {
                    countBadge.textContent = data.count + ' Announcement' + (data.count !== 1 ? 's' : '');
                    loadingMsg.remove();
                    
                    let html = '';
                    data.announcements.forEach(ann => {
                        const date = new Date(ann.created_at);
                        const dateStr = date.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                        const timeStr = date.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit' 
                        });
                        
                        html += `
                            <div class="border rounded p-3 mb-3" style="background: #f8f9fa; border-left: 4px solid #ff9800;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div style="flex: 1;">
                                        <h6 style="font-weight: 600; color: #2c3e66; margin-bottom: 0.5rem;">
                                            ${escapeHtml(ann.title)}
                                        </h6>
                                        <p style="color: #495057; margin: 0.5rem 0; font-size: 0.9rem;">
                                            ${escapeHtml(ann.message.substring(0, 100))}${ann.message.length > 100 ? '...' : ''}
                                        </p>
                                        <small style="color: #6c757d;">
                                            <i class="far fa-calendar me-1"></i>${dateStr} at ${timeStr} 
                                            <span style="margin-left: 10px;">
                                                <i class="fas fa-user me-1"></i><strong>${escapeHtml(ann.teacher_name)}</strong>
                                            </span>
                                        </small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-info ms-2 view-announcement-btn" data-title="${escapeHtml(ann.title)}" data-message="${escapeHtml(ann.message)}" data-date="${dateStr}" data-time="${timeStr}">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                    container.querySelectorAll('.view-announcement-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            viewAnnouncement(btn.dataset.title, btn.dataset.message, btn.dataset.date, btn.dataset.time);
                        });
                    });
                } else {
                    countBadge.textContent = '0 Announcements';
                    loadingMsg.innerHTML = '<i class="fas fa-info-circle me-2"></i>No announcements at the moment.';
                }
            })
            .catch(err => {
                console.error('Error fetching announcements:', err);
                document.getElementById('loadingMsg').innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Unable to load announcements.';
            });

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function viewAnnouncement(title, message, date, time) {
                alert('📢 ' + title + '\n\n' + message + '\n\nPublished: ' + date + ' at ' + time);
            }
            </script>


            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-value">৳<?php echo number_format($total_pending, 2); ?></div>
                    <div class="stat-label">Pending Fees</div>
                    <div style="margin-top: 15px; font-size: 12px;">
                        <div style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                            <?php foreach ($upcoming_fees as $fee): ?>
                            <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                                <span style="color: #666;"><?php echo htmlspecialchars($fee['fee_month']); ?></span>
                                <strong style="color: #f44336;">৳<?php echo number_format($fee['due_amount'], 2); ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-top: 10px; text-align: center;">
                        <small style="color: #999;">Next 2 Months</small>
                    </div>
                </div>
            </div>

            <div class="card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">📚 Child's Routine</h5>
                        <?php if (!empty($student_class_name) || !empty($student_group_name)): ?>
                            <small class="text-muted"><?php echo htmlspecialchars(trim($student_class_name . ' / ' . $student_group_name)); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($class_routine)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 12%;">Day</th>
                                    <th style="width: 25%;">Subject</th>
                                    <th style="width: 20%;">Teacher</th>
                                    <th style="width: 13%;">Room</th>
                                    <th style="width: 30%;">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_routine as $routine_item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($routine_item['day'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($routine_item['subject'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($routine_item['teacher'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($routine_item['room'] ?? 'N/A'); ?></span></td>
                                        <td>
                                            <?php 
                                                $start = isset($routine_item['start_time']) ? date('g:i A', strtotime($routine_item['start_time'])) : '';
                                                $end = isset($routine_item['end_time']) ? date('g:i A', strtotime($routine_item['end_time'])) : '';
                                                echo htmlspecialchars(trim($start . ' – ' . $end));
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>No schedule is available yet.</strong> This student’s routine will appear here once it is created for their class and group.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="quick-links">
                <h5><i class="fas fa-star me-2"></i>Quick Access</h5>
                <div class="links-grid">
                    <a href="attendance.php" class="quick-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>Check Attendance</span>
                    </a>
                    <a href="results.php" class="quick-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Results</span>
                    </a>
                    <a href="fees.php" class="quick-link">
                        <i class="fas fa-receipt"></i>
                        <span>Fee Details</span>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
