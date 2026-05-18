<?php
ob_start();
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
ob_end_clean();

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $user_id = intval($_SESSION['user_id']);
    $role = $_SESSION['role'] ?? 'unknown';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['notification_id'])) {
        throw new Exception('Notification ID is required');
    }

    $notification_id = intval($data['notification_id']);

    // Verify ownership
    $check_query = "SELECT id FROM notifications 
                    WHERE id = $notification_id AND recipient_id = $user_id AND recipient_role = '$role'
                    LIMIT 1";
    
    $check_result = mysqli_query($conn, $check_query);
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        throw new Exception('Notification not found or access denied');
    }

    // Delete notification
    $delete_query = "DELETE FROM notifications WHERE id = $notification_id";
    
    if (mysqli_query($conn, $delete_query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    } else {
        throw new Exception('Error deleting notification: ' . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>
