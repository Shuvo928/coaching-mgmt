<?php
// check_teacher_subject.php
// Checks if a subject is assigned to a specific teacher

include '../includes/db.php';

$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

header('Content-Type: application/json');

if ($teacher_id && $subject_id) {
    $query = "SELECT * FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo json_encode(['assigned' => true]);
    } else {
        echo json_encode(['assigned' => false]);
    }
} else {
    echo json_encode(['assigned' => false, 'error' => 'Invalid parameters']);
}

mysqli_close($conn);
?>
