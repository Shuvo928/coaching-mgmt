<?php
session_start();
require_once '../includes/db.php';

// Function to generate unique student ID
function generateStudentID($conn) {
    $prefix = 'STU';
    $year = date('Y');
    $pattern = $prefix . $year . '%';

    $query = "SELECT student_id FROM students WHERE student_id LIKE '$pattern' ORDER BY student_id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastId = $row['student_id'];
        $lastNumber = intval(substr($lastId, strlen($prefix . $year)));
        $count = $lastNumber + 1;
    } else {
        $count = 1;
    }

    do {
        $newId = $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        $checkQuery = "SELECT id FROM students WHERE student_id = '$newId' LIMIT 1";
        $checkResult = mysqli_query($conn, $checkQuery);
        $count++;
    } while($checkResult && mysqli_num_rows($checkResult) > 0);

    return $newId;
}

// Check if classes table has section column
$classesSectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
$classesHasSection = ($classesSectionColumn && mysqli_num_rows($classesSectionColumn) > 0);

function redirectWithError($message) {
    $_SESSION['error'] = $message;
    header("Location: student-management.php");
    exit();
}

if(isset($_POST['submit'])) {
    $student_id = $_POST['student_id'] ?? '';
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name']);
    $mother_name = mysqli_real_escape_string($conn, $_POST['mother_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'] ?: null;
    $class_label = mysqli_real_escape_string($conn, $_POST['class_label']);
    $admission_date = $_POST['admission_date'];
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Validation
    if(empty($first_name) || empty($last_name) || empty($phone) || empty($username) || empty($class_label)) {
        redirectWithError('Please fill in all required student fields.');
    }

    if(empty($student_id) && empty($password)) {
        redirectWithError('Password is required when adding a new student.');
    }

    // Ensure unique username for the account
    $existingUserQuery = "SELECT id FROM users WHERE username = '$username'";
    if(!empty($student_id)) {
        $existingUserQuery .= " AND id <> (SELECT user_id FROM students WHERE id = $student_id)";
    }
    $existingUserResult = mysqli_query($conn, $existingUserQuery);
    if(mysqli_num_rows($existingUserResult) > 0) {
        redirectWithError('The username is already in use. Please choose a different username.');
    }

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
                redirectWithError('Failed to upload photo.');
            }
        } else {
            redirectWithError('Only JPG, PNG, and GIF files are allowed for photo upload.');
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
            $user_query = "INSERT INTO users (username, password, role, status) 
                          VALUES ('$username', '$hashed_password', 'student', 1)";

            if(!mysqli_query($conn, $user_query)) {
                throw new Exception('Error creating user account: ' . mysqli_error($conn));
            }

            $user_id = mysqli_insert_id($conn);

            // Prepare optional field values for SQL
            $class_id = null;
            $class_label_value = trim($class_label);
            if(!empty($class_label_value)) {
                $parts = explode('-', $class_label_value, 2);
                $class_name = trim($parts[0]);
                $class_section = isset($parts[1]) ? trim($parts[1]) : '';
                
                // Build dynamic query based on whether section column exists
                if($classesHasSection && !empty($class_section)) {
                    $class_query = "SELECT id FROM classes WHERE class_name = '$class_name' AND section = '$class_section' LIMIT 1";
                } else {
                    $class_query = "SELECT id FROM classes WHERE class_name = '$class_name' LIMIT 1";
                }
                
                $class_result = mysqli_query($conn, $class_query);
                if($class_result && mysqli_num_rows($class_result) > 0) {
                    $class_row = mysqli_fetch_assoc($class_result);
                    $class_id = $class_row['id'];
                } else {
                    // Insert new class
                    if($classesHasSection) {
                        $insert_class = "INSERT INTO classes (class_name, section) VALUES ('$class_name', '$class_section')";
                    } else {
                        $insert_class = "INSERT INTO classes (class_name) VALUES ('$class_name')";
                    }
                    if(mysqli_query($conn, $insert_class)) {
                        $class_id = mysqli_insert_id($conn);
                    }
                }
            }
            $class_id_sql = $class_id ? (int)$class_id : 'NULL';
            $gender_sql = $gender ? "'" . mysqli_real_escape_string($conn, $gender) . "'" : 'NULL';
            $dob_sql = !empty($dob) ? "'" . mysqli_real_escape_string($conn, $dob) . "'" : 'NULL';
            $admission_date_sql = !empty($admission_date) ? "'" . mysqli_real_escape_string($conn, $admission_date) . "'" : 'NULL';
            $photo_sql = !empty($photo) ? "'" . mysqli_real_escape_string($conn, $photo) . "'" : 'NULL';

            $student_result = false;
            $student_error = '';
            $attempts = 0;
            $maxAttempts = 5;

            while($attempts < $maxAttempts) {
                $student_unique_id = generateStudentID($conn);
                $student_query = "INSERT INTO students (user_id, student_id, first_name, last_name, father_name, 
                                  mother_name, phone, dob, gender, address, photo, class_id, 
                                  admission_date, status) 
                                  VALUES ($user_id, '$student_unique_id', '$first_name', '$last_name', 
                                  '$father_name', '$mother_name', '$phone', $dob_sql, $gender_sql, 
                                  '$address', $photo_sql, $class_id_sql, $admission_date_sql, 1)";

                if(mysqli_query($conn, $student_query)) {
                    $student_result = true;
                    break;
                }

                $student_error = mysqli_error($conn);
                if(strpos($student_error, 'Duplicate entry') === false) {
                    break;
                }

                $attempts++;
            }

            if(!$student_result) {
                throw new Exception('Error adding student details: ' . $student_error);
            }

            mysqli_commit($conn);
            $_SESSION['success'] = 'Student added successfully! Student ID: ' . $student_unique_id;

        } catch(Exception $e) {
            mysqli_rollback($conn);
            redirectWithError($e->getMessage());
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
        
        // Prepare optional values for update
        $dob_sql = !empty($dob) ? "'" . mysqli_real_escape_string($conn, $dob) . "'" : 'NULL';
        $gender_sql = $gender ? "'" . mysqli_real_escape_string($conn, $gender) . "'" : 'NULL';
        $class_id_sql = $class_id ? (int)$class_id : 'NULL';
        $admission_date_sql = !empty($admission_date) ? "'" . mysqli_real_escape_string($conn, $admission_date) . "'" : 'NULL';
        $photo_sql = !empty($photo) ? "'" . mysqli_real_escape_string($conn, $photo) . "'" : 'NULL';

        // Update query
        $class_id = null;
        $class_label_value = trim($class_label);
        if(!empty($class_label_value)) {
            $parts = explode('-', $class_label_value, 2);
            $class_name = trim($parts[0]);
            $class_section = isset($parts[1]) ? trim($parts[1]) : '';
            
            // Build dynamic query based on whether section column exists
            if($classesHasSection && !empty($class_section)) {
                $class_query = "SELECT id FROM classes WHERE class_name = '$class_name' AND section = '$class_section' LIMIT 1";
            } else {
                $class_query = "SELECT id FROM classes WHERE class_name = '$class_name' LIMIT 1";
            }
            
            $class_result = mysqli_query($conn, $class_query);
            if($class_result && mysqli_num_rows($class_result) > 0) {
                $class_row = mysqli_fetch_assoc($class_result);
                $class_id = $class_row['id'];
            } else {
                // Insert new class
                if($classesHasSection) {
                    $insert_class = "INSERT INTO classes (class_name, section) VALUES ('$class_name', '$class_section')";
                } else {
                    $insert_class = "INSERT INTO classes (class_name) VALUES ('$class_name')";
                }
                if(mysqli_query($conn, $insert_class)) {
                    $class_id = mysqli_insert_id($conn);
                }
            }
        }
        $class_id_sql = $class_id ? (int)$class_id : 'NULL';

        $update_query = "UPDATE students SET 
                         first_name = '$first_name',
                         last_name = '$last_name',
                         father_name = '$father_name',
                         mother_name = '$mother_name',
                         phone = '$phone',
                         dob = $dob_sql,
                         gender = $gender_sql,
                         address = '$address',
                         photo = $photo_sql,
                         class_id = $class_id_sql,
                         admission_date = $admission_date_sql
                         WHERE id = $student_id";
        
        if(mysqli_query($conn, $update_query)) {
            // Update username in users table
            mysqli_query($conn, "UPDATE users SET username = '$username' 
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