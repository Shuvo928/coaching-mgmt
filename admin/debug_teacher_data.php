<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in and is a teacher
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Not logged in as teacher");
}

$user_id = $_SESSION['user_id'];
echo "User ID: $user_id<br>";

// Get teacher details
$teacher_query = "SELECT t.* FROM teachers t WHERE t.user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);

if(mysqli_num_rows($teacher_result) == 0) {
    die("Teacher record not found.");
}

$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];
echo "Teacher ID: $teacher_id<br>";
echo "Teacher Name: " . $teacher['first_name'] . ' ' . $teacher['last_name'] . '<br>';

// Check teacher_subjects table
$assignments_query = "SELECT ts.*, s.subject_name, c.class_name
                      FROM teacher_subjects ts
                      LEFT JOIN subjects s ON ts.subject_id = s.id
                      LEFT JOIN classes c ON ts.class_id = c.id
                      WHERE ts.teacher_id = '$teacher_id'";

$assignments_result = mysqli_query($conn, $assignments_query);
echo "<br>Teacher assignments (" . mysqli_num_rows($assignments_result) . "):<br>";
while($row = mysqli_fetch_assoc($assignments_result)) {
    echo "- Subject: " . ($row['subject_name'] ?? 'NULL') . " (ID: " . $row['subject_id'] . "), Class: " . ($row['class_name'] ?? 'NULL') . " (ID: " . $row['class_id'] . ")<br>";
}

// Check students in teacher's classes
$students_query = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.class_name
                   FROM students s
                   JOIN classes c ON s.class_id = c.id
                   WHERE s.class_id IN (SELECT class_id FROM teacher_subjects WHERE teacher_id = '$teacher_id')
                   ORDER BY c.class_name, s.roll_number";

$students_result = mysqli_query($conn, $students_query);
echo "<br>Students in teacher's classes (" . mysqli_num_rows($students_result) . "):<br>";
$count = 0;
while($row = mysqli_fetch_assoc($students_result) && $count < 5) {
    echo "- " . $row['roll_number'] . ' - ' . $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['class_name'] . ')<br>';
    $count++;
}
?>