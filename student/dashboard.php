<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notification_helpers.php';

/** @var \mysqli $conn */

checkAuth();
checkRole(['student']);

$user = getCurrentUser($conn);

// Check if classes table has section column
$classesSectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
$classesHasSection = ($classesSectionColumn && mysqli_num_rows($classesSectionColumn) > 0);

// Check what phone column name exists in admission_applications
$admissionPhoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
$admissionHasMobile = ($admissionPhoneColumn && mysqli_num_rows($admissionPhoneColumn) > 0);
$admissionPhoneField = $admissionHasMobile ? 'mobile' : 'phone';

// Check if exam_types table exists
$examTypesTableExists = false;
$examTypesCheck = mysqli_query($conn, "SHOW TABLES LIKE 'exam_types'");
if ($examTypesCheck && mysqli_num_rows($examTypesCheck) > 0) {
    $examTypesTableExists = true;
}

// Check if results table has percentage column
$resultsPercentageExists = false;
$resultsPercentageCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'percentage'");
if ($resultsPercentageCheck && mysqli_num_rows($resultsPercentageCheck) > 0) {
    $resultsPercentageExists = true;
}

// Check if results table has marks_obtained and total_marks columns
$resultsMarksColumns = false;
$marksObtainedCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'marks_obtained'");
$totalMarksCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'total_marks'");
$resultsMarksColumns = ($marksObtainedCheck && mysqli_num_rows($marksObtainedCheck) > 0) && 
                        ($totalMarksCheck && mysqli_num_rows($totalMarksCheck) > 0);

$student = null;
$branch_name = 'Not assigned';
$class_time = '2:20 PM - 6:45 PM (Saturday-Thursday)';
$class_routine = [];
$results = [];
$overall_stats = [
    'avg_percentage' => null,
    'total_results' => 0
];

function getBranchName(string $address): string {
    $address = strtolower($address);
    if (strpos($address, 'dhanmondi') !== false) {
        return 'Dhanmondi Branch';
    }
    if (strpos($address, 'mirpur') !== false) {
        return 'Mirpur Branch';
    }
    if (strpos($address, 'uttara') !== false) {
        return 'Uttara Branch';
    }
    if (strpos($address, 'banani') !== false || strpos($address, 'gulshan') !== false || strpos($address, 'baridhara') !== false) {
        return 'Banani / Gulshan Branch';
    }
    return 'Nearest Branch';
}

function getPerformanceComment(?float $percentage): string {
    if ($percentage === null) {
        return 'No result yet';
    }
    if ($percentage >= 80) {
        return 'Excellent';
    }
    if ($percentage >= 70) {
        return 'Good';
    }
    if ($percentage >= 50) {
        return 'Average';
    }
    if ($percentage >= 40) {
        return 'Needs Improvement';
    }
    return 'Poor';
}

function formatTimeRange(?string $start, ?string $end): string {
    if (empty($start) || empty($end)) {
        return 'Not assigned yet';
    }
    return date('g:i A', strtotime($start)) . ' – ' . date('g:i A', strtotime($end));
}

function formatRoutineCell(mixed $value): string {
    $value = trim((string)($value ?? ''));
    return $value === '' ? 'Not assigned yet' : htmlspecialchars($value);
}

function getTeacherDisplayName(\mysqli $conn, string $teacherName): string {
    $teacherName = trim((string)$teacherName);
    if ($teacherName === '') {
        return 'Not assigned yet';
    }

    $sql = "SELECT 1 FROM teachers WHERE status = 1 AND LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = LOWER(?) LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 'Not assigned yet';
    }
    mysqli_stmt_bind_param($stmt, 's', $teacherName);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $exists ? htmlspecialchars($teacherName) : 'Not assigned yet';
}

if (!empty($user['id'])) {
    // Build dynamic SELECT for class section if column exists
    $sectionSelect = $classesHasSection ? 'c.section' : "'' AS section";
    
    $student_query = "SELECT s.*, c.class_name, $sectionSelect, COALESCE(g.group_name, aa.`group`, 'Unassigned') AS group_name, aa.program, aa.monthly_fee, aa.transaction_id, u.username, u.created_at AS account_created 
                      FROM students s 
                      LEFT JOIN classes c ON s.class_id = c.id
                      LEFT JOIN groups g ON s.group_id = g.id
                      LEFT JOIN admission_applications aa ON s.phone = aa.$admissionPhoneField 
                      LEFT JOIN users u ON s.user_id = u.id
                      WHERE s.user_id = " . intval($user['id']) . " LIMIT 1";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);

    if ($student) {
        $branch_name = getBranchName($student['address'] ?? '');

        // Build class_group name from student's class and group
        // Extract class number and group from the student record
        $class_number = '';
        $group_name = $student['group_name'] ?? '';
        
        // Parse class name to get class number (e.g., "Class 9", "Class 10", "SSC Batch")
        if (!empty($student['class_name'])) {
            if (strpos($student['class_name'], 'SSC') !== false || strpos($student['class_name'], 'ssc') !== false) {
                $class_number = 'SSC Batch';
            } else {
                preg_match('/Class\s+(\d+)/', $student['class_name'], $matches);
                if (!empty($matches[1])) {
                    $class_number = 'Class ' . $matches[1];
                }
            }
        }
        
        // Build the class_group string to match with routine table
        if (!empty($class_number) && !empty($group_name)) {
            // Normalize group names to title case (Science Group, Commerce Group, Humanities Group)
            $group_display = ucfirst(strtolower($group_name));
            if (stripos($group_display, 'group') === false) {
                $group_display .= ' Group';
            }
            $expected_class_group = $class_number . ' — ' . $group_display;
        }

        // Query routine from the new routine table
        if (!empty($expected_class_group)) {
            $routine_query = "SELECT * FROM `routine` 
                              WHERE class_group = '" . mysqli_real_escape_string($conn, $expected_class_group) . "' 
                              ORDER BY FIELD(day, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'), start_time";
        } else {
            // Fallback if class_group cannot be determined
            $routine_query = "SELECT * FROM `routine` LIMIT 0";
        }

        $routine_result = mysqli_query($conn, $routine_query);
        while ($routine = mysqli_fetch_assoc($routine_result)) {
            $class_routine[] = $routine;
        }

        // Build results query to include weekly and monthly tests
        $results_query = "SELECT r.*, 
                          CASE 
                            WHEN r.test_type = 'weekly_test' THEN 'Weekly Test'
                            WHEN r.test_type = 'monthly_test' THEN 'Monthly Test'
                            WHEN r.test_type = 'exam' THEN 'Exam'
                            ELSE 'Test'
                          END AS test_name,
                          sub.subject_name, r.exam_date AS exam_date
                          FROM results r 
                          LEFT JOIN subjects sub ON r.subject_id = sub.id 
                          WHERE r.student_id = " . intval($student['id']) . " 
                          ORDER BY r.created_at DESC LIMIT 10";
        $results_result = mysqli_query($conn, $results_query);
        while ($result = mysqli_fetch_assoc($results_result)) {
            $results[] = $result;
        }

        // Build stats query with conditional percentage and marks columns
        if ($resultsPercentageExists) {
            $stats_query = "SELECT 
                                AVG(percentage) as avg_percentage, 
                                COUNT(*) as total_results 
                            FROM results 
                            WHERE student_id = " . intval($student['id']);
        } else if ($resultsMarksColumns) {
            $stats_query = "SELECT 
                                AVG(CASE WHEN total_marks > 0 THEN (marks_obtained / total_marks * 100) ELSE 0 END) as avg_percentage, 
                                COUNT(*) as total_results 
                            FROM results 
                            WHERE student_id = " . intval($student['id']);
        } else {
            $stats_query = "SELECT 
                                NULL as avg_percentage, 
                                COUNT(*) as total_results 
                            FROM results 
                            WHERE student_id = " . intval($student['id']);
        }
        $stats_result = mysqli_query($conn, $stats_query);
        $overall_stats = mysqli_fetch_assoc($stats_result);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7fc; }
        .dashboard-container { max-width: 1100px; margin: 40px auto; padding: 20px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar h1 { margin: 0; font-size: 28px; }
        .card { border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .profile-card { padding: 30px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 20px; }
        .info-list dt { font-weight: 600; }
        
        /* Notifications Bell */
        .notifications-bell { position: relative; cursor: pointer; font-size: 1.3rem; margin-right: 15px; color: #333; }
        .notifications-bell:hover { color: #667eea; transform: scale(1.1); transition: all 0.2s ease; }
        .notification-badge { position: absolute; top: -8px; right: -8px; background: #ff4757; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; border: 2px solid white; }
        .notification-badge.hidden { display: none; }
        .notifications-popup { position: absolute; top: 50px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); width: 380px; max-height: 500px; overflow-y: auto; z-index: 1050; display: none; }
        .notifications-popup.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notification-header { padding: 15px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; background: #f8f9fa; }
        .notification-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s ease; position: relative; }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread { background: #f0f7ff; }
        .notification-item.unread::before { content: ''; position: absolute; left: 0; width: 4px; height: 100%; background: #667eea; }
        .notification-item.unread { padding-left: 20px; }
        .notification-title { font-weight: 600; color: #2c3e66; margin-bottom: 4px; font-size: 0.95rem; }
        .notification-message { color: #666; font-size: 0.85rem; margin-bottom: 6px; line-height: 1.4; }
        .notification-time { font-size: 0.75rem; color: #999; }
        .notification-actions { display: flex; gap: 8px; margin-top: 8px; }
        .notification-actions button { padding: 4px 8px; font-size: 0.75rem; border: none; border-radius: 4px; cursor: pointer; }
        .notification-actions .btn-view { background: #667eea; color: white; }
        .notification-actions .btn-view:hover { background: #5568d3; }
        .notification-actions .btn-close { background: #e0e0e0; color: #666; }
        .notification-actions .btn-close:hover { background: #d0d0d0; }
        .notification-empty { padding: 30px 15px; text-align: center; color: #999; font-size: 0.9rem; }
        .notification-footer { padding: 12px 15px; text-align: center; border-top: 1px solid #e0e0e0; font-size: 0.85rem; }
        .notification-footer a { color: #667eea; text-decoration: none; font-weight: 600; cursor: pointer; }
        .notification-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="topbar">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($user['first_name'] ?? 'Student'); ?></h1>
                <p class="text-muted">This is your student dashboard.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="notifications-bell" id="notificationsBell" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                    <div class="notifications-popup" id="notificationsPopup">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <button style="background: none; border: none; color: #666; cursor: pointer; font-size: 1.2rem;" onclick="closeNotificationsPopup()">&times;</button>
                        </div>
                        <div id="notificationsContainer">
                            <div class="notification-empty">Loading notifications...</div>
                        </div>
                        <div class="notification-footer">
                            <a onclick="markAllAsRead(); return false;">Mark all as read</a>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
        </div>

        <div class="card mb-4 p-4 text-dark" style="background: #ffffff; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
            <p class="mb-0" style="font-size: 1rem; line-height: 1.8;">
                "Every number you see here tells a story — your attendance, your results, your progress. These are not just records; they are reflections of your effort, your discipline, and your growth. Every class you attend is a step forward. Every improvement, no matter how small, is a victory."
            </p>
        </div>

        <!-- Announcements Section -->
        <div class="card mb-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2 text-warning"></i>📢 Latest Announcements</h5>
                <span class="badge bg-secondary" id="announcementCount">Loading...</span>
            </div>
            <div id="announcementsContainer" style="max-height: 400px; overflow-y: auto;">
                <p class="text-muted text-center" id="loadingMsg">
                    <i class="fas fa-spinner fa-spin me-2"></i>Loading announcements...
                </p>
            </div>
        </div>

        <script>
        // Fetch announcements on page load
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
                        <div class="border rounded p-3 mb-3" style="background: #f8f9fa;">
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

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card profile-card text-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? '', 0, 1)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-0">Username: <?php echo htmlspecialchars($user['username']); ?></p>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card p-4">
                    <h5 class="mb-3">Enrollment Details</h5>
                    <?php if($student): ?>
                        <dl class="row info-list">
                            
                            <dt class="col-sm-4">Class</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Group</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Assigned Date</dt>
                            <dd class="col-sm-8"><?php echo !empty($student['account_created']) ? date('d-m-Y h:i A', strtotime($student['account_created'])) : 'N/A'; ?></dd>
                            
                            
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted">Student enrollment details are not yet available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-3">
            <div class="col-lg-6">
        <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">📚 Your Class Routine</h5>
                        <a href="view-routine.php" class="btn btn-sm btn-primary" style="background: #667eea; border: none;">
                            <i class="fas fa-calendar-alt me-1"></i> View Full Schedule
                        </a>
                    </div>
                    <?php if (!empty($class_routine)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 12%;">📅 Day</th>
                                        <th style="width: 20%;">📖 Subject</th>
                                        <th style="width: 18%;">👨‍🏫 Teacher</th>
                                        <th style="width: 10%;">🚪 Room</th>
                                        <th style="width: 20%;">⏰ Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_routine as $routine_item): ?>
                                        <tr>
                                            <td><strong><?php echo formatRoutineCell($routine_item['day'] ?? ''); ?></strong></td>
                                            <td><?php echo formatRoutineCell($routine_item['subject'] ?? ''); ?></td>
                                            <td><?php echo getTeacherDisplayName($conn, $routine_item['teacher'] ?? ''); ?></td>
                                            <td><span class="badge bg-info"><?php echo formatRoutineCell($routine_item['room'] ?? ''); ?></span></td>
                                            <td>
                                                <?php 
                                                    echo formatTimeRange($routine_item['start_time'] ?? '', $routine_item['end_time'] ?? '');
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>No class routine available yet.</strong> Your routine will appear here once it's set up for your class and group.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-4">
                    <h5 class="mb-3">Recent Results</h5>
                    <?php if (!empty($results)): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary me-2">Exams</span>
                            <span class="badge bg-secondary">Total: <?php echo intval($overall_stats['total_results']); ?></span>
                            <?php if ($overall_stats['avg_percentage'] !== null): ?>
                                <span class="badge bg-success">Avg: <?php echo number_format($overall_stats['avg_percentage'], 1); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Exam Date</th>
                                        <th>Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <?php 
                                            // Calculate percentage if columns exist
                                            if ($resultsPercentageExists) {
                                                $percent = $result['percentage'] !== null ? floatval($result['percentage']) : null;
                                            } else if ($resultsMarksColumns) {
                                                $percent = (isset($result['total_marks']) && $result['total_marks'] > 0) 
                                                    ? floatval($result['marks_obtained']) / floatval($result['total_marks']) * 100 
                                                    : null;
                                            } else {
                                                $percent = null;
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['test_name'] ?? 'Test'); ?></td>
                                            <td><?php echo htmlspecialchars($result['subject_name'] ?? 'N/A'); ?></td>
                                            <td><?php 
                                                if (isset($result['marks_obtained']) && $result['marks_obtained'] !== null) {
                                                    if (isset($result['total_marks']) && $result['total_marks'] !== null && $result['total_marks'] > 0) {
                                                        echo htmlspecialchars($result['marks_obtained'] . '/' . $result['total_marks']);
                                                    } else {
                                                        echo htmlspecialchars($result['marks_obtained'] . '/100');
                                                    }
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?></td>
                                            <td><?php echo isset($result['exam_date']) && $result['exam_date'] ? date('d-m-Y', strtotime($result['exam_date'])) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars(getPerformanceComment($percent)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No exam results have been added for you yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications JavaScript -->
    <script>
    // Close popup when clicking outside
    document.addEventListener('click', (e) => {
        const popup = document.getElementById('notificationsPopup');
        const bell = document.getElementById('notificationsBell');
        if (popup && bell && !popup.contains(e.target) && !bell.contains(e.target)) {
            closeNotificationsPopup();
        }
    });

    function closeNotificationsPopup() {
        const popup = document.getElementById('notificationsPopup');
        if (popup) popup.classList.remove('show');
    }

    function toggleNotificationsPopup() {
        const popup = document.getElementById('notificationsPopup');
        if (popup) popup.classList.toggle('show');
    }

    document.getElementById('notificationsBell').addEventListener('click', () => {
        toggleNotificationsPopup();
        if (document.getElementById('notificationsPopup').classList.contains('show')) {
            loadNotifications();
        }
    });

    function loadNotifications() {
        fetch('../api/get-notifications.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    displayNotifications(data.notifications);
                }
            })
            .catch(err => console.error('Error loading notifications:', err));
    }

    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function displayNotifications(notifications) {
        const container = document.getElementById('notificationsContainer');
        
        if (notifications.length === 0) {
            container.innerHTML = '<div class="notification-empty"><i class="fas fa-check-circle me-2"></i>All caught up! No new notifications.</div>';
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const isUnread = notif.is_read == 0;
            const createdDate = new Date(notif.created_at);
            const now = new Date();
            const diffMs = now - createdDate;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            let timeStr = '';
            if (diffMins < 1) timeStr = 'Just now';
            else if (diffMins < 60) timeStr = diffMins + 'm ago';
            else if (diffHours < 24) timeStr = diffHours + 'h ago';
            else if (diffDays < 7) timeStr = diffDays + 'd ago';
            else timeStr = createdDate.toLocaleDateString();

            html += `
                <div class="notification-item ${isUnread ? 'unread' : ''}">
                    <div class="notification-title">${escapeHtml(notif.title)}</div>
                    <div class="notification-message">${escapeHtml(notif.message || '')}</div>
                    <div class="notification-time">${timeStr}</div>
                    <div class="notification-actions">
                        ${notif.action_url ? `<button class="btn-view" onclick="goToAction('${escapeHtml(notif.action_url)}')"><i class="fas fa-arrow-right me-1"></i>View</button>` : ''}
                        <button class="btn-close" onclick="deleteNotification(${notif.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function deleteNotification(notifId) {
        if (confirm('Delete this notification?')) {
            fetch('../api/delete-notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notifId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) loadNotifications();
            });
        }
    }

    function goToAction(url) {
        closeNotificationsPopup();
        window.location.href = url;
    }

    function markAllAsRead() {
        const unreadNotifs = document.querySelectorAll('.notification-item.unread');
        let count = 0;
        unreadNotifs.forEach(notif => {
            const button = notif.querySelector('.btn-close');
            const onclick = button.getAttribute('onclick');
            const notifId = onclick.match(/\d+/)[0];
            fetch('../api/mark-notification-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notifId })
            });
            count++;
        });
        if (count > 0) setTimeout(() => loadNotifications(), 500);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initial load and auto-refresh
    loadNotifications();
    setInterval(loadNotifications, 30000);
    </script>
</body>
</html>
