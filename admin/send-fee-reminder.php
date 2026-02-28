<?php
require_once '../includes/db.php';
require_once 'send-sms.php'; // Reuse SMS function

if(isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    
    // Get student details and due amount
    $query = "SELECT s.*, SUM(fc.due_amount) as total_due
              FROM students s
              LEFT JOIN fee_collections fc ON s.id = fc.student_id
              WHERE s.id = $student_id AND fc.status != 'Paid'
              GROUP BY s.id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    
    if($student && !empty($student['phone'])) {
        $message = "Dear {$student['first_name']}, this is a reminder that your fee of ৳{$student['total_due']} is due. Please pay soon to avoid late fee. - Coaching Center";
        
        // Use SMS function from send-sms.php
        $sms_sent = sendViaAPI($student['phone'], $message);
        
        echo json_encode(['success' => $sms_sent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found or no phone number']);
    }
}
?>