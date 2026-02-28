<?php
require_once '../includes/db.php';

$response = ['success' => false, 'message' => ''];

if(isset($_POST['exam_id']) && isset($_POST['subject_id']) && isset($_POST['marks'])) {
    $exam_id = $_POST['exam_id'];
    $subject_id = $_POST['subject_id'];
    $marks_data = $_POST['marks'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        foreach($marks_data as $data) {
            $student_id = $data['student_id'];
            $marks_obtained = floatval($data['marks']);
            
            // Calculate grade and point based on Bangladesh grading system
            if($marks_obtained >= 80) {
                $grade = 'A+';
                $point = 5.00;
            } elseif($marks_obtained >= 70) {
                $grade = 'A';
                $point = 4.00;
            } elseif($marks_obtained >= 60) {
                $grade = 'A-';
                $point = 3.50;
            } elseif($marks_obtained >= 50) {
                $grade = 'B';
                $point = 3.00;
            } elseif($marks_obtained >= 40) {
                $grade = 'C';
                $point = 2.00;
            } elseif($marks_obtained >= 33) {
                $grade = 'D';
                $point = 1.00;
            } else {
                $grade = 'F';
                $point = 0.00;
            }
            
            // Check if record exists
            $check = mysqli_query($conn, "SELECT id FROM results 
                                          WHERE student_id = $student_id 
                                          AND exam_type_id = $exam_id 
                                          AND subject_id = $subject_id");
            
            if(mysqli_num_rows($check) > 0) {
                // Update existing record
                $query = "UPDATE results SET 
                          marks_obtained = $marks_obtained,
                          percentage = $marks_obtained,
                          grade = '$grade',
                          points = $point
                          WHERE student_id = $student_id 
                          AND exam_type_id = $exam_id 
                          AND subject_id = $subject_id";
            } else {
                // Insert new record
                $query = "INSERT INTO results 
                          (student_id, exam_type_id, subject_id, marks_obtained, total_marks, percentage, grade, points) 
                          VALUES 
                          ($student_id, $exam_id, $subject_id, $marks_obtained, 100, $marks_obtained, '$grade', $point)";
            }
            
            if(!mysqli_query($conn, $query)) {
                throw new Exception("Error saving marks for student ID: $student_id");
            }
        }
        
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = 'Marks saved successfully';
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
?>