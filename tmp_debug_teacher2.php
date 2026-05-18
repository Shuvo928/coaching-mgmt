<?php
require_once 'includes/db.php';

$query = "SELECT id, first_name, last_name, CONCAT(first_name, ' ', last_name) AS fullname FROM teachers WHERE first_name LIKE '%teacher%' OR last_name LIKE '%teacher%'";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    echo json_encode($row) . "\n";
}
echo "--- routines ---\n";
$query2 = "SELECT id, class_group, day, start_time, end_time, subject, teacher, room FROM routine WHERE teacher LIKE '%teacher%' OR class_group LIKE '%teacher%' LIMIT 50";
$res2 = mysqli_query($conn, $query2);
while ($row = mysqli_fetch_assoc($res2)) {
    echo json_encode($row) . "\n";
}
