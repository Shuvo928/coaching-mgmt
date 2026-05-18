<?php
ob_start();
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
ob_end_clean();

try {
    // Authentication check
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
        throw new Exception('Invalid request');
    }

    $announcement_id = intval($_POST['id']);
    $user_id = $_SESSION['user_id'];

    // Get teacher ID
    $teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
    $teacher_result = mysqli_query($conn, $teacher_query);
    if (!$teacher_result || mysqli_num_rows($teacher_result) == 0) {
        throw new Exception('Teacher not found');
    }
    $teacher = mysqli_fetch_assoc($teacher_result);
    $teacher_id = $teacher['id'];

    // Verify ownership before deleting
    $check_sql = "SELECT id FROM announcements WHERE id = $announcement_id AND teacher_id = $teacher_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        throw new Exception('Announcement not found or access denied');
    }

    // Delete announcement
    $delete_sql = "DELETE FROM announcements WHERE id = $announcement_id AND teacher_id = $teacher_id";
    
    if (mysqli_query($conn, $delete_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Announcement deleted successfully'
        ]);
    } else {
        throw new Exception('Error deleting announcement: ' . mysqli_error($conn));
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
