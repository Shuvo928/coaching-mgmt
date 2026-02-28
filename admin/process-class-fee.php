<?php
session_start();
require_once '../includes/db.php';

if(isset($_POST['save_class_fee'])) {
    $class_id = $_POST['class_id'];
    $fee_head_id = $_POST['fee_head_id'];
    $amount = floatval($_POST['amount']);
    $due_date = $_POST['due_date'] ?: null;
    
    // Check if already exists
    $check = mysqli_query($conn, "SELECT id FROM class_fees 
                                   WHERE class_id = $class_id AND fee_head_id = $fee_head_id");
    
    if(mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        $query = "UPDATE class_fees SET 
                  amount = $amount, 
                  due_date = " . ($due_date ? "'$due_date'" : "NULL") . "
                  WHERE id = {$row['id']}";
    } else {
        $query = "INSERT INTO class_fees (class_id, fee_head_id, amount, due_date) 
                  VALUES ($class_id, $fee_head_id, $amount, " . ($due_date ? "'$due_date'" : "NULL") . ")";
    }
    
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Class fee setup saved successfully!";
    } else {
        $_SESSION['success'] = "Error saving class fee!";
    }
    
    header("Location: fees-management.php");
    exit();
}
?>