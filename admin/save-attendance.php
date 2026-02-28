<?php
require_once '../includes/db.php';

$response = ['success' => false, 'message' => ''];

if(isset($_POST['date']) && isset($_POST['attendance'])) {
    $date = $_POST['date'];
    $attendance = $_POST['attendance'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        foreach($attendance as $record) {
            $student_id = $record['student_id'];
            $status = $record['status'];
            
            // Check if attendance already exists for this student on this date
            $check = mysqli_query($conn, "SELECT id FROM attendance WHERE student_id = $student_id AND date = '$date'");
            
            if(mysqli_num_rows($check) > 0) {
                // Update existing record
                $query = "UPDATE attendance SET status = '$status', created_at = NOW() 
                          WHERE student_id = $student_id AND date = '$date'";
            } else {
                // Insert new record
                // Get student's class_id
                $class_result = mysqli_query($conn, "SELECT class_id FROM students WHERE id = $student_id");
                $class = mysqli_fetch_assoc($class_result);
                $class_id = $class['class_id'];
                
                $query = "INSERT INTO attendance (student_id, class_id, date, status) 
                          VALUES ($student_id, $class_id, '$date', '$status')";
            }
            
            if(!mysqli_query($conn, $query)) {
                throw new Exception("Error saving attendance for student ID: $student_id");
            }
        }
        
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = 'Attendance saved successfully';
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
?>