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

function autoAssignPreferredSubjects($conn, $teacher_id, $assigned_subjects) {
    $assigned_subjects = trim($assigned_subjects);
    if($assigned_subjects === '') {
        return;
    }

    $mapping = [
        'bangla' => ['bangla 1st paper', 'bangla 2nd paper'],
        'english' => ['english 1st paper', 'english 2nd paper'],
        'math' => ['general mathematics', 'higher mathematics', 'business mathematics'],
        'mathematics' => ['general mathematics', 'higher mathematics', 'business mathematics'],
        'general mathematics' => ['general mathematics'],
        'higher mathematics' => ['higher mathematics'],
        'business mathematics' => ['business mathematics'],
    ];

    $terms = preg_split('/[\n\r,;]+/', strtolower($assigned_subjects));
    $terms = array_filter(array_map('trim', $terms));
    $terms = array_unique($terms);

    foreach($terms as $term) {
        if($term === '') {
            continue;
        }

        $searchTerms = $mapping[$term] ?? [$term];
        foreach($searchTerms as $searchTerm) {
            $keyword = mysqli_real_escape_string($conn, $searchTerm);
            $subject_query = mysqli_query($conn, "SELECT id, class_id FROM subjects WHERE LOWER(subject_name) LIKE '%$keyword%'");
            if(!$subject_query) {
                continue;
            }

            while($subject = mysqli_fetch_assoc($subject_query)) {
                $subject_id = intval($subject['id']);
                $check_query = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id LIMIT 1";
                $check_result = mysqli_query($conn, $check_query);
                if($check_result && mysqli_num_rows($check_result) === 0) {
                    $class_id = intval($subject['class_id']);
                    $insert_query = "INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES ($teacher_id, $subject_id, " . ($class_id ? $class_id : 'NULL') . ")";
                    mysqli_query($conn, $insert_query);
                }
            }
        }
    }
}

if(isset($_POST['submit'])) {
    $teacher_id = $_POST['teacher_id'] ?? '';
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $qualification = mysqli_real_escape_string($conn, $_POST['qualification']);
    $assigned_subjects = mysqli_real_escape_string($conn, $_POST['assigned_subjects']);
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
                              phone, qualification, assigned_subjects, joining_date, address, photo, status) 
                              VALUES ($user_id, '$teacher_unique_id', '$first_name', '$last_name', 
                              '$email', '$phone', '$qualification', '$assigned_subjects', '$joining_date', 
                              '$address', '$photo', 1)";
            
            if(!mysqli_query($conn, $teacher_query)) {
                throw new Exception("Error adding teacher details");
            }

            $inserted_teacher_id = mysqli_insert_id($conn);
            autoAssignPreferredSubjects($conn, $inserted_teacher_id, $assigned_subjects);
            
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
                         assigned_subjects = '$assigned_subjects',
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

            autoAssignPreferredSubjects($conn, $teacher_id, $assigned_subjects);
            
            $_SESSION['success'] = "Teacher updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating teacher!";
        }
    }
    
    header("Location: teacher-management.php");
    exit();
}
?>