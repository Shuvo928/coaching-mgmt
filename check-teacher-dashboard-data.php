<?php
require_once 'includes/db.php';

// For testing, assuming teacher_id = 5 (Mr. teacher1)
$teacher_id = 5;

echo "<h3>Teacher Dashboard Data Check for Teacher ID: $teacher_id</h3>";

// Check teacher_subjects
echo "<h4>1. Teacher Subjects (from teacher_subjects table):</h4>";
$ts_query = "SELECT ts.*, s.subject_name, c.class_name 
             FROM teacher_subjects ts
             LEFT JOIN subjects s ON ts.subject_id = s.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE ts.teacher_id = $teacher_id";
$ts_result = mysqli_query($conn, $ts_query);
echo "<pre>";
if (mysqli_num_rows($ts_result) > 0) {
    while ($row = mysqli_fetch_assoc($ts_result)) {
        print_r($row);
    }
} else {
    echo "❌ NO RECORDS FOUND IN teacher_subjects table for teacher_id=$teacher_id";
}
echo "</pre>";

// Check if teacher exists
echo "<h4>2. Teacher Record:</h4>";
$teacher_query = "SELECT id, user_id, first_name, last_name FROM teachers WHERE id = $teacher_id";
$teacher_result = mysqli_query($conn, $teacher_query);
echo "<pre>";
print_r(mysqli_fetch_assoc($teacher_result));
echo "</pre>";

// Check all teacher_subjects records (for comparison)
echo "<h4>3. All Teacher Subject Assignments (sample):</h4>";
$all_ts = "SELECT * FROM teacher_subjects LIMIT 5";
$all_ts_result = mysqli_query($conn, $all_ts);
echo "<pre>";
if (mysqli_num_rows($all_ts_result) > 0) {
    while ($row = mysqli_fetch_assoc($all_ts_result)) {
        print_r($row);
    }
} else {
    echo "NO RECORDS IN teacher_subjects table at all";
}
echo "</pre>";

// Check subjects table
echo "<h4>4. Available Subjects (sample):</h4>";
$subj_query = "SELECT id, subject_name, class_id FROM subjects LIMIT 10";
$subj_result = mysqli_query($conn, $subj_query);
echo "<pre>";
while ($row = mysqli_fetch_assoc($subj_result)) {
    print_r($row);
}
echo "</pre>";
?>
