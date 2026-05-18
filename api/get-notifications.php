<?php
ob_start();
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
ob_end_clean();

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $user_id = intval($_SESSION['user_id']);
    $role = $_SESSION['role'] ?? 'unknown';

    // Fetch unread count
    $count_query = "SELECT COUNT(*) as unread_count FROM notifications 
                    WHERE recipient_id = $user_id AND recipient_role = '$role' AND is_read = FALSE
                    AND (expires_at IS NULL OR expires_at > NOW())";
    
    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $unread_count = intval($count_row['unread_count'] ?? 0);

    // Fetch recent notifications (limit 10)
    $notifications_query = "SELECT * FROM notifications 
                           WHERE recipient_id = $user_id AND recipient_role = '$role'
                           AND (expires_at IS NULL OR expires_at > NOW())
                           ORDER BY created_at DESC
                           LIMIT 10";
    
    $notifications_result = mysqli_query($conn, $notifications_query);
    $notifications = [];
    
    while ($row = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = $row;
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>
