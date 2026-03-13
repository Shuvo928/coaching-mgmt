<?php
session_start();
require_once '../includes/db.php';

// Function to generate unique teacher ID
function generateTeacherID($conn) {
    $prefix = 'TCH';
    $year = date('Y');
    $query = "SELECT COUNT(*) as total FROM teachers WHERE teacher_id LIKE '$prefix$year%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $count = $row['total'] + 1;
    return $prefix . $year . str_pad($count, 3, '0', STR_PAD_LEFT);
}

if(isset($_POST['submit'])) {
    $teacher_id = $_POST['teacher_id'] ?? '';
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $qualification = mysqli_real_escape_string($conn, $_POST['qualification']);
    $interested_subjects = mysqli_real_escape_string($conn, $_POST['interested_subjects']);
    $joining_date = $_POST['joining_date'];
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Handle photo upload
    $photo = '';
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            $photo = time() . '_' . $filename;
            $upload_path = '../uploads/teacher-photos/' . $photo;
            
            if(!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $_SESSION['error'] = "Failed to upload photo!";
                header("Location: teacher-management.php");
                exit();
            }
        }
    }
    
    if(empty($teacher_id)) {
        // Insert new teacher
        $teacher_unique_id = generateTeacherID($conn);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into users table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_query = "INSERT INTO users (username, password, email, role, status) 
                          VALUES ('$username', '$hashed_password', '$email', 'teacher', 1)";
            
            if(!mysqli_query($conn, $user_query)) {
                throw new Exception("Error creating user account");
            }
            
            $user_id = mysqli_insert_id($conn);
            
            // Insert into teachers table
            $teacher_query = "INSERT INTO teachers (user_id, teacher_id, first_name, last_name, email, 
                              phone, qualification, interested_subjects, joining_date, address, photo, status) 
                              VALUES ($user_id, '$teacher_unique_id', '$first_name', '$last_name', 
                              '$email', '$phone', '$qualification', '$interested_subjects', '$joining_date', 
                              '$address', '$photo', 1)";
            
            if(!mysqli_query($conn, $teacher_query)) {
                throw new Exception("Error adding teacher details");
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Teacher added successfully! Teacher ID: " . $teacher_unique_id;
            
        } catch(Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
    } else {
        // Update existing teacher
        // Get current photo
        $photo_query = "SELECT photo FROM teachers WHERE id = $teacher_id";
        $photo_result = mysqli_query($conn, $photo_query);
        $current = mysqli_fetch_assoc($photo_result);
        
        if(empty($photo)) {
            $photo = $current['photo'];
        } else {
            // Delete old photo if exists
            if($current['photo'] && file_exists("../uploads/teacher-photos/".$current['photo'])) {
                unlink("../uploads/teacher-photos/".$current['photo']);
            }
        }
        
        // Update query
        $update_query = "UPDATE teachers SET 
                         first_name = '$first_name',
                         last_name = '$last_name',
                         email = '$email',
                         phone = '$phone',
                         qualification = '$qualification',
                         interested_subjects = '$interested_subjects',
                         joining_date = '$joining_date',
                         address = '$address',
                         photo = '$photo'
                         WHERE id = $teacher_id";
        
        if(mysqli_query($conn, $update_query)) {
            // Update username in users table
            mysqli_query($conn, "UPDATE users SET username = '$username', email = '$email' 
                                 WHERE id = (SELECT user_id FROM teachers WHERE id = $teacher_id)");
            
            // Update password if provided
            if(!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_id_query = "SELECT user_id FROM teachers WHERE id = $teacher_id";
                $user_id_result = mysqli_query($conn, $user_id_query);
                $user = mysqli_fetch_assoc($user_id_result);
                mysqli_query($conn, "UPDATE users SET password = '$hashed_password' 
                                     WHERE id = {$user['user_id']}");
            }
            
            $_SESSION['success'] = "Teacher updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating teacher!";
        }
    }
    
    header("Location: teacher-management.php");
    exit();
}
?>