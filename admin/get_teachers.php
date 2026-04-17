<?php
require_once '../includes/db.php';

$subject_id = $_POST['subject_id'];

/* Simple version (no subject mapping yet) */
$q = mysqli_query($conn, "SELECT * FROM teachers WHERE status=1");

echo '<option value="">Select Teacher</option>';

while($row = mysqli_fetch_assoc($q)) {
    echo "<option value='{$row['id']}'>{$row['first_name']} {$row['last_name']}</option>";
}
?>