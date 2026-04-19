<?php
require 'includes/db.php';

// Check if class_id column exists in teacher_subjects table
$teacherSubjectsClassIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
$teacherSubjectsClassIdColumnExists = ($teacherSubjectsClassIdColumnCheck && mysqli_num_rows($teacherSubjectsClassIdColumnCheck) > 0);

$columns = 'teacher_id, subject_id';
if ($teacherSubjectsClassIdColumnExists) {
    $columns .= ', class_id';
}

$query = "SELECT $columns FROM teacher_subjects";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['teacher_id'] . '|' . $r['subject_id'];
    if ($teacherSubjectsClassIdColumnExists) {
        echo '|' . $r['class_id'];
    } else {
        echo '|N/A';
    }
    echo "\n";
}
?>