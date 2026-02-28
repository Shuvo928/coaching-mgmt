<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT t.*, u.username 
              FROM teachers t 
              LEFT JOIN users u ON t.user_id = u.id 
              WHERE t.id = $id";
    
    $result = mysqli_query($conn, $query);
    $teacher = mysqli_fetch_assoc($result);
    
    echo json_encode($teacher);
}
?>