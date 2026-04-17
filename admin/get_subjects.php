<?php
require_once '../includes/db.php';

$class_id = $_POST['class_id'];
$section_id = $_POST['section_id'];

/* If you don’t have mapping table yet → simple version */
$q = mysqli_query($conn, "SELECT * FROM subjects");

echo '<option value="">Select Subject</option>';

while($row = mysqli_fetch_assoc($q)) {
    echo "<option value='{$row['id']}'>{$row['subject_name']}</option>";
}
?>