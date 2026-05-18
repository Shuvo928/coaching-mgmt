<?php
/**
 * Notification Helper Functions
 * Automatically creates notifications when important events happen
 */

/**
 * Create a new notification
 * 
 * @param mysqli $conn Database connection
 * @param int $recipient_id User/Admin ID
 * @param string $recipient_role Role (admin, teacher, student, parent)
 * @param string $type Notification type (approval, routine, result, fees, etc)
 * @param string $title Notification title/subject
 * @param string|null $message Notification message body
 * @param int|null $related_id Related entity ID (class_id, result_id, fee_id, etc)
 * @param string|null $action_url Link to take action
 * @param int $retention_days Days before auto-delete (default 7)
 * 
 * @return int|false Insert ID or false on failure
 */
function createNotification($conn, $recipient_id, $recipient_role, $type, $title, $message = null, $related_id = null, $action_url = null, $retention_days = 7) {
    // Sanitize inputs
    $recipient_id = intval($recipient_id);
    $recipient_role = mysqli_real_escape_string($conn, $recipient_role);
    $type = mysqli_real_escape_string($conn, $type);
    $title = mysqli_real_escape_string($conn, substr($title, 0, 255));
    $message = !empty($message) ? mysqli_real_escape_string($conn, substr($message, 0, 5000)) : '';
    $related_id = !empty($related_id) ? intval($related_id) : 'NULL';
    $action_url = !empty($action_url) ? "'" . mysqli_real_escape_string($conn, substr($action_url, 0, 255)) . "'" : 'NULL';
    $expires_at = date('Y-m-d H:i:s', strtotime("+$retention_days days"));

    $insert_sql = "INSERT INTO notifications 
                   (recipient_id, recipient_role, type, title, message, related_id, action_url, expires_at)
                   VALUES ($recipient_id, '$recipient_role', '$type', '$title', '$message', $related_id, $action_url, '$expires_at')";

    if (mysqli_query($conn, $insert_sql)) {
        return mysqli_insert_id($conn);
    }
    return false;
}

/**
 * Notify all students in a class about new result entry
 */
function notifyStudentsNewResult($conn, $class_id, $subject_id, $test_name) {
    $class_id = intval($class_id);
    $subject_id = intval($subject_id);
    
    // Get subject name
    $subject_query = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id = $subject_id");
    $subject_row = mysqli_fetch_assoc($subject_query);
    $subject_name = $subject_row['subject_name'] ?? 'Unknown Subject';

    // Get all students in this class
    $students_query = "SELECT user_id FROM students WHERE class_id = $class_id";
    $students_result = mysqli_query($conn, $students_query);

    $created_count = 0;
    while ($student_row = mysqli_fetch_assoc($students_result)) {
        $student_user_id = intval($student_row['user_id']);
        $title = "New Result Added: $subject_name - $test_name";
        $message = "Your result for $subject_name ($test_name) has been entered. Check it now!";
        $action_url = "student/dashboard.php";

        if (createNotification($conn, $student_user_id, 'student', 'result', $title, $message, $subject_id, $action_url)) {
            $created_count++;
        }
    }

    return $created_count;
}

/**
 * Notify admin about pending admission applications
 */
function notifyAdminPendingAdmissions($conn, $admin_id = null) {
    // Count pending admissions
    $pending_query = "SELECT COUNT(*) as pending_count FROM admission_applications WHERE status = 'Pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    $pending_row = mysqli_fetch_assoc($pending_result);
    $pending_count = intval($pending_row['pending_count'] ?? 0);

    if ($pending_count > 0) {
        $title = "Pending Admissions Review";
        $message = "You have $pending_count pending admission application(s) awaiting approval.";
        $action_url = "admin/admission-management.php";

        // If admin_id provided, notify specific admin; otherwise notify all admins
        if ($admin_id) {
            createNotification($conn, $admin_id, 'admin', 'approval', $title, $message, null, $action_url);
        } else {
            // Notify all admin users
            $admins_query = "SELECT DISTINCT u.id FROM users u WHERE u.role = 'admin'";
            $admins_result = mysqli_query($conn, $admins_query);
            while ($admin_row = mysqli_fetch_assoc($admins_result)) {
                createNotification($conn, intval($admin_row['id']), 'admin', 'approval', $title, $message, null, $action_url);
            }
        }
    }
}

/**
 * Notify parents about upcoming/due fees
 */
function notifyParentFeeDue($conn, $student_id, $fee_month, $due_amount) {
    $student_id = intval($student_id);
    
    // Get student info
    $student_query = "SELECT parent_id, first_name FROM students WHERE id = $student_id";
    $student_result = mysqli_query($conn, $student_query);
    $student_row = mysqli_fetch_assoc($student_result);
    $parent_id = intval($student_row['parent_id'] ?? 0);
    $student_name = $student_row['first_name'] ?? 'Your child';

    if ($parent_id > 0) {
        $title = "Fee Payment Due - $fee_month";
        $message = "Fee payment for $student_name is due for $fee_month. Amount: ৳" . number_format($due_amount, 2);
        $action_url = "parent/fees.php";

        createNotification($conn, $parent_id, 'parent', 'fees', $title, $message, $student_id, $action_url);
    }
}

/**
 * Notify teacher when routine is set/updated for their class
 */
function notifyTeacherRoutineUpdated($conn, $teacher_id, $class_group, $class_name) {
    $teacher_id = intval($teacher_id);
    $title = "Class Routine Available";
    $message = "Your class routine for $class_group ($class_name) has been set. Check it now!";
    $action_url = "admin/teacher-dashboard.php#routine-section";

    createNotification($conn, $teacher_id, 'teacher', 'routine', $title, $message, null, $action_url);
}

/**
 * Clear expired notifications (call periodically)
 */
function clearExpiredNotifications($conn) {
    $delete_query = "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()";
    return mysqli_query($conn, $delete_query);
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($conn, $user_id, $role) {
    $user_id = intval($user_id);
    $role = mysqli_real_escape_string($conn, $role);
    
    $query = "SELECT COUNT(*) as unread FROM notifications 
              WHERE recipient_id = $user_id AND recipient_role = '$role' AND is_read = FALSE
              AND (expires_at IS NULL OR expires_at > NOW())";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return intval($row['unread'] ?? 0);
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($conn, $user_id, $role) {
    $user_id = intval($user_id);
    $role = mysqli_real_escape_string($conn, $role);
    
    $update_query = "UPDATE notifications SET is_read = TRUE 
                     WHERE recipient_id = $user_id AND recipient_role = '$role' AND is_read = FALSE";
    
    return mysqli_query($conn, $update_query);
}

?>
