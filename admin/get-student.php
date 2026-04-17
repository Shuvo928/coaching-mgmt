<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $sectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
    $sectionSelect = ($sectionColumn && mysqli_num_rows($sectionColumn) > 0) ? 'c.section' : "''";

    $query = "SELECT s.*, u.username, CONCAT_WS(' - ', c.class_name, $sectionSelect) AS class_label 
              FROM students s 
              LEFT JOIN users u ON s.user_id = u.id 
              LEFT JOIN classes c ON s.class_id = c.id 
              WHERE s.id = $id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    
    echo json_encode($student);
}
?>