<?php
session_start();
require_once '../includes/db.php';

if(isset($_POST['save_fee_head'])) {
    $fee_name = mysqli_real_escape_string($conn, $_POST['fee_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    
    $query = "INSERT INTO fees_head (fee_name, description, is_mandatory) 
              VALUES ('$fee_name', '$description', $is_mandatory)";
    
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Fee head added successfully!";
    } else {
        $_SESSION['error'] = "Error adding fee head!";
    }
    
    header("Location: fees-management.php");
    exit();
}
?>