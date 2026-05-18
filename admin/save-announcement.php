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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Get teacher details
    $user_id = $_SESSION['user_id'];
    $teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
    $teacher_result = mysqli_query($conn, $teacher_query);
    if (!$teacher_result || mysqli_num_rows($teacher_result) == 0) {
        throw new Exception('Teacher not found');
    }
    $teacher = mysqli_fetch_assoc($teacher_result);
    $teacher_id = $teacher['id'];

    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['class_id']) || !isset($data['title']) || !isset($data['message'])) {
        throw new Exception('Missing required fields');
    }

    $class_id = intval($data['class_id']);
    $group_id = isset($data['group_id']) && $data['group_id'] ? intval($data['group_id']) : null;
    $title = mysqli_real_escape_string($conn, substr($data['title'], 0, 255));
    $message = mysqli_real_escape_string($conn, substr($data['message'], 0, 5000));

    // Validate teacher has subjects in this class
    $check_permission = "SELECT 1 FROM teacher_subjects ts
                        JOIN subjects s ON ts.subject_id = s.id
                        WHERE ts.teacher_id = $teacher_id AND s.class_id = $class_id LIMIT 1";
    $perm_result = mysqli_query($conn, $check_permission);
    if (!$perm_result || mysqli_num_rows($perm_result) == 0) {
        throw new Exception('You do not have permission to create announcements for this class');
    }

    // Insert announcement
    $group_insert = $group_id ? ", $group_id" : ", NULL";
    $insert_sql = "INSERT INTO announcements (teacher_id, class_id, group_id, title, message)
                   VALUES ($teacher_id, $class_id" . $group_insert . ", '$title', '$message')";
    
    if (mysqli_query($conn, $insert_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Announcement published successfully',
            'announcement_id' => mysqli_insert_id($conn)
        ]);
    } else {
        throw new Exception('Error saving announcement: ' . mysqli_error($conn));
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
