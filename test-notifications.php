<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/notification_helpers.php';

$page_title = "Notification System Tester";

// Only allow admin to test
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin login required.');
}

$test_results = [];

// Test 1: Check if notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
$test_results['table_exists'] = mysqli_num_rows($table_check) > 0;

// Test 2: Check table columns
$columns_check = mysqli_query($conn, "SHOW COLUMNS FROM notifications");
$columns = [];
while ($col = mysqli_fetch_assoc($columns_check)) {
    $columns[] = $col['Field'];
}
$required_columns = ['id', 'recipient_id', 'recipient_role', 'type', 'title', 'message', 'is_read', 'created_at', 'expires_at'];
$test_results['columns_exist'] = count(array_intersect($required_columns, $columns)) === count($required_columns);

// Test 3: Try to create a test notification
$test_create = createNotification(
    $conn,
    1,
    'student',
    'test',
    'Test Notification',
    'This is a test notification to verify the system is working',
    null,
    null
);
$test_results['create_notification'] = $test_create !== false;

// Test 4: Check if notification was inserted
if ($test_create) {
    $verify_query = "SELECT * FROM notifications WHERE id = " . intval($test_create) . " LIMIT 1";
    $verify_result = mysqli_query($conn, $verify_query);
    $test_results['verify_insert'] = mysqli_num_rows($verify_result) > 0;
}

// Test 5: Test get unread count
$count = getUnreadNotificationCount($conn, 1, 'student');
$test_results['get_unread_count'] = is_numeric($count) && $count >= 0;

// Test 6: Test mark as read
if ($test_create) {
    $mark_read = mysqli_query($conn, "UPDATE notifications SET is_read = TRUE WHERE id = " . intval($test_create));
    $test_results['mark_as_read'] = $mark_read !== false;
}

// Test 7: Check API file exists
$test_results['api_get_exists'] = file_exists('api/get-notifications.php');
$test_results['api_mark_exists'] = file_exists('api/mark-notification-read.php');
$test_results['api_delete_exists'] = file_exists('api/delete-notification.php');

// Test 8: Check helper functions exist
$test_results['helper_functions'] = function_exists('createNotification') && 
                                     function_exists('notifyStudentsNewResult') && 
                                     function_exists('getUnreadNotificationCount');

// Get summary stats
$total_notifs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications");
$total_row = mysqli_fetch_assoc($total_notifs);
$total_count = $total_row['cnt'] ?? 0;

$unread_notifs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE is_read = FALSE");
$unread_row = mysqli_fetch_assoc($unread_notifs);
$unread_count = $unread_row['cnt'] ?? 0;

$expired_notifs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()");
$expired_row = mysqli_fetch_assoc($expired_notifs);
$expired_count = $expired_row['cnt'] ?? 0;

// Count pass/fail
$passed = count(array_filter($test_results, fn($v) => $v === true));
$failed = count(array_filter($test_results, fn($v) => $v === false));
$total = count($test_results);
$pass_rate = round(($passed / $total) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px; }
        .container { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 800px; }
        h1 { color: #667eea; margin-bottom: 10px; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: 600; margin-bottom: 20px; }
        .status-success { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }
        .test-item { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .test-item:last-child { border-bottom: none; }
        .test-name { font-weight: 500; }
        .test-result { font-weight: 600; }
        .test-pass { color: #28a745; }
        .test-fail { color: #dc3545; }
        .stat-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 15px 0; }
        .stat-value { font-size: 24px; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
        .action-buttons { margin-top: 30px; display: flex; gap: 10px; }
        button { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
        .btn-primary { background: #667eea; color: white; border: none; }
        .btn-primary:hover { background: #5568d3; color: white; }
        .btn-danger { background: #dc3545; color: white; border: none; }
        .btn-danger:hover { background: #c82333; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-flask me-2"></i>Notification System Tester</h1>
        <p class="text-muted">Testing notification system configuration and functionality</p>

        <!-- Status Summary -->
        <?php if ($pass_rate === 100): ?>
            <span class="status-badge status-success"><i class="fas fa-check-circle me-2"></i>All Tests Passed!</span>
        <?php elseif ($pass_rate >= 75): ?>
            <span class="status-badge status-warning"><i class="fas fa-exclamation-circle me-2"></i><?= $pass_rate ?>% Tests Passed</span>
        <?php else: ?>
            <span class="status-badge status-danger"><i class="fas fa-times-circle me-2"></i>Tests Failed - <?= $failed ?> issues</span>
        <?php endif; ?>

        <!-- Test Results -->
        <div style="background: #f8f9fa; border-radius: 10px; margin-bottom: 30px;">
            <div style="padding: 20px; border-bottom: 2px solid #e0e0e0; font-weight: 600;">System Tests (<?= $passed ?>/<?= $total ?> Passed)</div>
            
            <div class="test-item">
                <span class="test-name"><i class="fas fa-database me-2"></i>Notifications Table Exists</span>
                <span class="test-result <?= $test_results['table_exists'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['table_exists'] ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-columns me-2"></i>Required Columns Present</span>
                <span class="test-result <?= $test_results['columns_exist'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['columns_exist'] ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-plus-circle me-2"></i>Create Notification Function</span>
                <span class="test-result <?= $test_results['create_notification'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['create_notification'] ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-check me-2"></i>Insert Verification</span>
                <span class="test-result <?= $test_results['verify_insert'] ?? false ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['verify_insert'] ?? false ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-calculator me-2"></i>Get Unread Count Function</span>
                <span class="test-result <?= $test_results['get_unread_count'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['get_unread_count'] ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-eye-slash me-2"></i>Mark as Read Function</span>
                <span class="test-result <?= $test_results['mark_as_read'] ?? false ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['mark_as_read'] ?? false ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-cube me-2"></i>API: get-notifications.php</span>
                <span class="test-result <?= $test_results['api_get_exists'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['api_get_exists'] ? '✓ EXISTS' : '✗ MISSING' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-cube me-2"></i>API: mark-notification-read.php</span>
                <span class="test-result <?= $test_results['api_mark_exists'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['api_mark_exists'] ? '✓ EXISTS' : '✗ MISSING' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-cube me-2"></i>API: delete-notification.php</span>
                <span class="test-result <?= $test_results['api_delete_exists'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['api_delete_exists'] ? '✓ EXISTS' : '✗ MISSING' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-name"><i class="fas fa-function me-2"></i>Helper Functions Defined</span>
                <span class="test-result <?= $test_results['helper_functions'] ? 'test-pass' : 'test-fail' ?>">
                    <?= $test_results['helper_functions'] ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>
        </div>

        <!-- Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div class="stat-box">
                <div class="stat-value" style="color: #667eea;"><?= $total_count ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #ffc107;"><?= $unread_count ?></div>
                <div class="stat-label">Unread Notifications</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #dc3545;"><?= $expired_count ?></div>
                <div class="stat-label">Expired (>7 days)</div>
            </div>
        </div>

        <!-- Information -->
        <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong><i class="fas fa-info-circle me-2"></i>System Information</strong>
            <ul style="margin-bottom: 0; margin-top: 10px; padding-left: 20px;">
                <li>Notification retention period: <strong>7 days</strong></li>
                <li>Auto-refresh interval: <strong>30 seconds</strong></li>
                <li>Max notifications in popup: <strong>10</strong></li>
                <li>Delivery method: <strong>In-app only</strong></li>
                <li>Actions: <strong>View only</strong></li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <form method="POST" style="display: inline;">
                <button type="submit" name="cleanup_expired" class="btn-danger" onclick="return confirm('Delete notifications older than 7 days?');">
                    <i class="fas fa-trash me-2"></i>Clean Expired Notifications
                </button>
            </form>
            <a href="student/dashboard.php" class="btn-primary" style="text-decoration: none; display: inline-block;">
                <i class="fas fa-home me-2"></i>View Student Dashboard
            </a>
        </div>

        <!-- Next Steps -->
        <div style="background: #f0f4f9; padding: 20px; border-radius: 10px; margin-top: 30px;">
            <strong><i class="fas fa-tasks me-2"></i>Next Steps</strong>
            <ol style="margin-bottom: 0; margin-top: 10px;">
                <li>✓ Run this test page to verify setup</li>
                <li>Add bell icon to <strong>admin/dashboard.php</strong></li>
                <li>Add bell icon to <strong>parent/dashboard.php</strong></li>
                <li>Add bell icon to <strong>admin/teacher-dashboard.php</strong></li>
                <li>Integrate notification triggers in business logic files</li>
                <li>Test all notification types</li>
            </ol>
            <p style="margin-top: 15px; margin-bottom: 0; font-size: 0.9rem;">
                📖 See <strong>NOTIFICATION_SYSTEM_SUMMARY.md</strong> and <strong>NOTIFICATIONS_INTEGRATION_POINTS.md</strong> for detailed instructions.
            </p>
        </div>
    </div>

    <?php
    // Handle cleanup request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_expired'])) {
        $deleted = clearExpiredNotifications($conn);
        if ($deleted) {
            echo '<script>alert("Expired notifications deleted"); location.reload();</script>';
        }
    }
    ?>
</body>
</html>
