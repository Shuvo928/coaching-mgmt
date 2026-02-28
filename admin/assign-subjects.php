<?php
session_start();
require_once '../includes/db.php';

if(isset($_POST['assign'])) {
    $teacher_id = $_POST['teacher_id'];
    $subjects = $_POST['subjects'] ?? [];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete existing assignments
        $delete_query = "DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id";
        mysqli_query($conn, $delete_query);
        
        // Insert new assignments
        if(!empty($subjects)) {
            foreach($subjects as $subject_id) {
                $insert_query = "INSERT INTO teacher_subjects (teacher_id, subject_id) 
                                VALUES ($teacher_id, $subject_id)";
                mysqli_query($conn, $insert_query);
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "Subjects assigned successfully!";
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error assigning subjects!";
    }
    
    header("Location: teacher-management.php");
    exit();
}
?>