<?php
session_start();
require_once '../includes/db.php';

if(isset($_POST['collect_fee'])) {
    $student_id = $_POST['student_id'];
    $fee_head_id = $_POST['fee_head_id'];
    $paying_amount = floatval($_POST['paying_amount']);
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    
    // Get current fee details
    $fee_query = "SELECT fc.*, cf.amount as total_fee, cf.due_date 
                  FROM fee_collections fc
                  JOIN class_fees cf ON fc.fee_head_id = cf.fee_head_id 
                      AND fc.student_id IN (SELECT id FROM students WHERE class_id = cf.class_id)
                  WHERE fc.student_id = $student_id AND fc.fee_head_id = $fee_head_id
                  ORDER BY fc.id DESC LIMIT 1";
    
    $fee_result = mysqli_query($conn, $fee_query);
    
    if(mysqli_num_rows($fee_result) > 0) {
        // Update existing collection
        $fee = mysqli_fetch_assoc($fee_result);
        $new_paid = $fee['paid_amount'] + $paying_amount;
        $new_due = $fee['amount'] - $new_paid;
        $status = $new_due <= 0 ? 'Paid' : ($new_paid > 0 ? 'Partial' : 'Unpaid');
        
        $update_query = "UPDATE fee_collections SET 
                         paid_amount = $new_paid,
                         due_amount = $new_due,
                         status = '$status',
                         payment_date = '$payment_date',
                         payment_method = '$payment_method',
                         remarks = '$remarks'
                         WHERE student_id = $student_id AND fee_head_id = $fee_head_id";
        
        if(mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = "Fee collected successfully!";
        } else {
            $_SESSION['error'] = "Error updating fee collection!";
        }
        
    } else {
        // Get class-wise fee amount
        $class_query = "SELECT class_id FROM students WHERE id = $student_id";
        $class_result = mysqli_query($conn, $class_query);
        $class = mysqli_fetch_assoc($class_result);
        
        $fee_amount_query = "SELECT amount, due_date FROM class_fees 
                             WHERE class_id = {$class['class_id']} AND fee_head_id = $fee_head_id";
        $fee_amount_result = mysqli_query($conn, $fee_amount_query);
        $fee_amount = mysqli_fetch_assoc($fee_amount_result);
        
        $total_amount = $fee_amount['amount'];
        $due_amount = $total_amount - $paying_amount;
        $status = $due_amount <= 0 ? 'Paid' : 'Partial';
        
        // Generate receipt number
        $receipt_no = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $insert_query = "INSERT INTO fee_collections 
                         (student_id, fee_head_id, amount, paid_amount, due_amount, 
                          payment_date, payment_method, receipt_no, status, remarks) 
                         VALUES 
                         ($student_id, $fee_head_id, $total_amount, $paying_amount, $due_amount,
                          '$payment_date', '$payment_method', '$receipt_no', '$status', '$remarks')";
        
        if(mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = "Fee collected successfully! Receipt No: $receipt_no";
        } else {
            $_SESSION['error'] = "Error collecting fee!";
        }
    }
    
    header("Location: fees-management.php");
    exit();
}
?>