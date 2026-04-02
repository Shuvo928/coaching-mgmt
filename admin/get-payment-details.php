<?php
session_start();
require_once '../includes/db.php';

// Check authentication
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

if(isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    $query = "SELECT id, student_id, amount, paid_amount, due_amount, status, payment_method FROM fee_collections WHERE id = $id";
    $result = mysqli_query($conn, $query);
    
    if($result && mysqli_num_rows($result) > 0) {
        $payment = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($payment);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
    }
}
?>
