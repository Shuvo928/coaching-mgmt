<?php
require 'includes/db.php';
$res = mysqli_query($conn, 'SELECT teacher_id, subject_id, class_id FROM teacher_subjects');
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['teacher_id'] . '|' . $r['subject_id'] . '|' . $r['class_id'] . "\n";
}
?>