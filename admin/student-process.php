<?php
session_start();
require_once '../includes/db.php';

// Function to generate unique student ID
function generateStudentID($conn) {
    $prefix = 'STU';
    $year = date('Y');
    $query = "SELECT COUNT(*) as total FROM students WHERE student_id LIKE '$prefix$year%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $count = $row['total'] + 1;
    return $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
}

if(isset($_POST['submit'])) {
    $student_id = $_POST['student_id'] ?? '';
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name']);
    $mother_name = mysqli_real_escape_string($conn, $_POST['mother_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $class_id = $_POST['class_id'];
    $roll_number = $_POST['roll_number'];
    $batch_no = $_POST['batch_no'];
    $admission_date = $_POST['admission_date'];
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
            $upload_path = '../uploads/student-photos/' . $photo;
            
            if(!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $_SESSION['error'] = "Failed to upload photo!";
                header("Location: student-management.php");
                exit();
            }
        }
    }
    
    if(empty($student_id)) {
        // Insert new student
        $student_unique_id = generateStudentID($conn);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into users table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_query = "INSERT INTO users (username, password, email, role, status) 
                          VALUES ('$username', '$hashed_password', '$email', 'student', 1)";
            
            if(!mysqli_query($conn, $user_query)) {
                throw new Exception("Error creating user account");
            }
            
            $user_id = mysqli_insert_id($conn);
            
            // Insert into students table
            $student_query = "INSERT INTO students (user_id, student_id, first_name, last_name, father_name, 
                              mother_name, email, phone, dob, gender, address, photo, class_id, 
                              batch_no, roll_number, admission_date, status) 
                              VALUES ($user_id, '$student_unique_id', '$first_name', '$last_name', 
                              '$father_name', '$mother_name', '$email', '$phone', '$dob', '$gender', 
                              '$address', '$photo', $class_id, '$batch_no', '$roll_number', 
                              '$admission_date', 1)";
            
            if(!mysqli_query($conn, $student_query)) {
                throw new Exception("Error adding student details");
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Student added successfully! Student ID: " . $student_unique_id;
            
        } catch(Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
    } else {
        // Update existing student
        // Get current photo
        $photo_query = "SELECT photo FROM students WHERE id = $student_id";
        $photo_result = mysqli_query($conn, $photo_query);
        $current = mysqli_fetch_assoc($photo_result);
        
        if(empty($photo)) {
            $photo = $current['photo'];
        } else {
            // Delete old photo if exists
            if($current['photo'] && file_exists("../uploads/student-photos/".$current['photo'])) {
                unlink("../uploads/student-photos/".$current['photo']);
            }
        }
        
        // Update query
        $update_query = "UPDATE students SET 
                         first_name = '$first_name',
                         last_name = '$last_name',
                         father_name = '$father_name',
                         mother_name = '$mother_name',
                         email = '$email',
                         phone = '$phone',
                         dob = '$dob',
                         gender = '$gender',
                         address = '$address',
                         photo = '$photo',
                         class_id = $class_id,
                         batch_no = '$batch_no',
                         roll_number = '$roll_number',
                         admission_date = '$admission_date'
                         WHERE id = $student_id";
        
        if(mysqli_query($conn, $update_query)) {
            // Update username in users table
            mysqli_query($conn, "UPDATE users SET username = '$username', email = '$email' 
                                 WHERE id = (SELECT user_id FROM students WHERE id = $student_id)");
            
            // Update password if provided
            if(!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_id_query = "SELECT user_id FROM students WHERE id = $student_id";
                $user_id_result = mysqli_query($conn, $user_id_query);
                $user = mysqli_fetch_assoc($user_id_result);
                mysqli_query($conn, "UPDATE users SET password = '$hashed_password' 
                                     WHERE id = {$user['user_id']}");
            }
            
            $_SESSION['success'] = "Student updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating student!";
        }
    }
    
    header("Location: student-management.php");
    exit();
}
?>