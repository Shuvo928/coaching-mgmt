<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if(isset($_POST['teacher_id']) && is_numeric($_POST['teacher_id'])) {
    $teacher_id = intval($_POST['teacher_id']);
    if($teacher_id > 0) {
        $query = "SELECT subject_id FROM teacher_subjects WHERE teacher_id = $teacher_id";
        $result = mysqli_query($conn, $query);
        $subjects = [];
        while($row = mysqli_fetch_assoc($result)) {
            $subjects[] = intval($row['subject_id']);
        }
        echo json_encode($subjects);
        exit();
    }
}

echo json_encode([]);
exit();
