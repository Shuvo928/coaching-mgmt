<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$class_id = $_POST['class_id'];
$section_id = $_POST['section_id'];
$subject_id = $_POST['subject_id'];
$teacher_id = $_POST['teacher_id'];
$day = $_POST['day'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$room = $_POST['room'] ?? "";

/* =========================
   CONFLICT CHECK
========================= */

$check = mysqli_query($conn, "
SELECT * FROM class_routine 
WHERE day='$day'
AND (
    teacher_id='$teacher_id'
    OR class_id='$class_id'
)
AND (
    (start_time <= '$start_time' AND end_time > '$start_time')
    OR
    (start_time < '$end_time' AND end_time >= '$end_time')
)
");

if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('⚠ Conflict detected! Teacher or Class already has routine at this time'); window.history.back();</script>";
    exit();
}

/* =========================
   INSERT ROUTINE
========================= */

mysqli_query($conn, "
INSERT INTO class_routine 
(class_id, section_id, subject_id, teacher_id, day, start_time, end_time, room)
VALUES 
('$class_id','$section_id','$subject_id','$teacher_id','$day','$start_time','$end_time','$room')
");

echo "<script>alert('Routine Added Successfully'); window.location='add_routine.php';</script>";
?>