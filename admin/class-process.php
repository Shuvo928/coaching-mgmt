<?php
session_start();
require_once '../includes/db.php';

// Save Class
if(isset($_POST['save_class'])) {
    $class_id = $_POST['class_id'] ?? '';
    $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    
    if(empty($class_id)) {
        // Check if class already exists
        $check = mysqli_query($conn, "SELECT id FROM classes WHERE class_name = '$class_name' AND section = '$section'");
        if(mysqli_num_rows($check) > 0) {
            $_SESSION['error'] = "Class with same name and section already exists!";
        } else {
            $query = "INSERT INTO classes (class_name, section) VALUES ('$class_name', '$section')";
            if(mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Class added successfully!";
            } else {
                $_SESSION['error'] = "Error adding class!";
            }
        }
    } else {
        // Update class
        $query = "UPDATE classes SET class_name = '$class_name', section = '$section' WHERE id = $class_id";
        if(mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Class updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating class!";
        }
    }
    
    header("Location: class-management.php");
    exit();
}

// Save Subject
if(isset($_POST['save_subject'])) {
    $subject_id = $_POST['subject_id'] ?? '';
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $subject_code = mysqli_real_escape_string($conn, $_POST['subject_code']);
    $class_id = $_POST['class_id'];
    
    if(empty($subject_id)) {
        // Check if subject code already exists
        $check = mysqli_query($conn, "SELECT id FROM subjects WHERE subject_code = '$subject_code'");
        if(mysqli_num_rows($check) > 0) {
            $_SESSION['error'] = "Subject code already exists!";
        } else {
            $query = "INSERT INTO subjects (subject_name, subject_code, class_id) 
                      VALUES ('$subject_name', '$subject_code', $class_id)";
            if(mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Subject added successfully!";
            } else {
                $_SESSION['error'] = "Error adding subject!";
            }
        }
    } else {
        // Update subject
        $query = "UPDATE subjects SET subject_name = '$subject_name', 
                  subject_code = '$subject_code', class_id = $class_id 
                  WHERE id = $subject_id";
        if(mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Subject updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating subject!";
        }
    }
    
    header("Location: class-management.php");
    exit();
}
?>