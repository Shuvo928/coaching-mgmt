<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$class_id = intval($_POST['class_id']);
$section_id = intval($_POST['section_id']);
$subject_id = intval($_POST['subject_id']);
$teacher_id = intval($_POST['teacher_id']);
$day = mysqli_real_escape_string($conn, trim($_POST['day']));
$start_time = mysqli_real_escape_string($conn, trim($_POST['start_time']));
$end_time = mysqli_real_escape_string($conn, trim($_POST['end_time']));
$room = mysqli_real_escape_string($conn, trim($_POST['room'] ?? ""));

/* =========================
   TEACHER / SUBJECT / CLASS VALIDATION
========================= */
$class_check = mysqli_query($conn, "SELECT id FROM classes WHERE id = $class_id LIMIT 1");
if (!$class_check || mysqli_num_rows($class_check) === 0) {
    echo "<script>alert('⚠ Invalid class selected.'); window.history.back();</script>";
    exit();
}

$subject_check = mysqli_query($conn, "SELECT id FROM subjects WHERE id = $subject_id LIMIT 1");
if (!$subject_check || mysqli_num_rows($subject_check) === 0) {
    echo "<script>alert('⚠ Invalid subject selected.'); window.history.back();</script>";
    exit();
}

$teacher_check = mysqli_query($conn, "SELECT id FROM teachers WHERE id = $teacher_id AND status = 1 LIMIT 1");
if (!$teacher_check || mysqli_num_rows($teacher_check) === 0) {
    echo "<script>alert('⚠ Selected teacher is not registered or active.'); window.history.back();</script>";
    exit();
}

$eligibility_sql = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
$column_check = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
if ($column_check && mysqli_num_rows($column_check) > 0) {
    $eligibility_sql .= " AND class_id = $class_id";
}
$eligibility_check = mysqli_query($conn, $eligibility_sql);
if (!$eligibility_check || mysqli_num_rows($eligibility_check) === 0) {
    echo "<script>alert('⚠ Selected teacher is not assigned to this subject/class combination.'); window.history.back();</script>";
    exit();
}

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