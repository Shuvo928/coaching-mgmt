<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT * FROM subjects WHERE id = $id";
    $result = mysqli_query($conn, $query);
    $subject = mysqli_fetch_assoc($result);
    
    echo json_encode($subject);
}
?>