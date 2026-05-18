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

    // Fetch announcement with permission check
    $fetch_sql = "SELECT a.*, c.class_name, COALESCE(g.group_name, 'All Groups') AS group_name
                  FROM announcements a
                  JOIN classes c ON a.class_id = c.id
                  LEFT JOIN `groups` g ON a.group_id = g.id
                  WHERE a.id = $announcement_id AND a.teacher_id = $teacher_id";
    
    $result = mysqli_query($conn, $fetch_sql);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        throw new Exception('Announcement not found or access denied');
    }

    $announcement = mysqli_fetch_assoc($result);

    echo json_encode([
        'success' => true,
        'announcement' => $announcement
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
