<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$result_id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];

// Get teacher ID
$teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

// Fetch result with related data
$query = "SELECT r.id, r.student_id, r.subject_id, r.test_type, r.marks_obtained, r.exam_date,
                 s.first_name, s.last_name, s.class_id, s.group_id,
                 sub.subject_name, c.class_name,
                 COALESCE(g.group_name, 'Unassigned') as group_name
          FROM results r
          JOIN students s ON r.student_id = s.id
          JOIN subjects sub ON r.subject_id = sub.id
          JOIN classes c ON sub.class_id = c.id
          LEFT JOIN `groups` g ON s.group_id = g.id
          WHERE r.id = $result_id";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Result not found']);
    exit;
}

$row = mysqli_fetch_assoc($result);

// Verify teacher has permission to edit this subject
$check_perm = "SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = " . $row['subject_id'];
$perm_result = mysqli_query($conn, $check_perm);

if (!$perm_result || mysqli_num_rows($perm_result) == 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'id' => $row['id'],
    'student_id' => $row['student_id'],
    'subject_id' => $row['subject_id'],
    'test_type' => $row['test_type'],
    'marks_obtained' => $row['marks_obtained'],
    'exam_date' => $row['exam_date'],
    'student_name' => $row['first_name'] . ' ' . $row['last_name'],
    'subject_name' => $row['subject_name'],
    'class_id' => $row['class_id'],
    'class_name' => $row['class_name'],
    'group_id' => $row['group_id'],
    'group_name' => $row['group_name']
]);
?>
