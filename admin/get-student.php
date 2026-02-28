<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT s.*, u.username 
              FROM students s 
              LEFT JOIN users u ON s.user_id = u.id 
              WHERE s.id = $id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    
    echo json_encode($student);
}
?>