<?php
require_once '../includes/db.php';

if(isset($_POST['class_id'])) {
    $class_id = $_POST['class_id'];
    
    $query = "SELECT COUNT(*) as count FROM students WHERE class_id = $class_id AND status = 1";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    echo $row['count'];
}
?>