<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

// Check if teacher_id column exists
$teacherIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'teacher_id'");
$teacherIdColumnExists = ($teacherIdColumnCheck && mysqli_num_rows($teacherIdColumnCheck) > 0);

// Check if assigned_subjects column exists
$assignedSubjectsColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'assigned_subjects'");
$assignedSubjectsColumnExists = ($assignedSubjectsColumnCheck && mysqli_num_rows($assignedSubjectsColumnCheck) > 0);

// Check if class_id column exists in teacher_subjects table
$teacherSubjectsClassIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
$teacherSubjectsClassIdColumnExists = ($teacherSubjectsClassIdColumnCheck && mysqli_num_rows($teacherSubjectsClassIdColumnCheck) > 0);

// Function to generate unique teacher ID
function generateTeacherID(\mysqli $conn): string {
    global $teacherIdColumnExists;
    $prefix = 'TCH';
    $year = date('Y');
    
    if ($teacherIdColumnExists) {
        $query = "SELECT COUNT(*) as total FROM teachers WHERE teacher_id LIKE '$prefix$year%'";
    } else {
        // If teacher_id column doesn't exist, use id column or just count all teachers
        $query = "SELECT COUNT(*) as total FROM teachers";
    }
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $count = $row['total'] + 1;
    return $prefix . $year . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function autoAssignPreferredSubjects(\mysqli $conn, int $teacher_id, string $assigned_subjects, ?array $class_ids = null): void {
    global $teacherSubjectsClassIdColumnExists;

    $assigned_subjects = trim($assigned_subjects);
    if($assigned_subjects === '') {
        return;
    }

    if (is_array($class_ids)) {
        $class_ids = array_values(array_filter(array_map('intval', $class_ids), function($value) {
            return $value > 0;
        }));
    } else {
        $class_ids = $class_ids ? [intval($class_ids)] : [];
    }

    if ($teacherSubjectsClassIdColumnExists && !empty($class_ids)) {
        $validatedClassIds = [];
        $class_ids_list = implode(',', array_map('intval', $class_ids));
        $validClassesRes = mysqli_query($conn, "SELECT id FROM classes WHERE id IN ($class_ids_list)");
        if ($validClassesRes) {
            while ($classRow = mysqli_fetch_assoc($validClassesRes)) {
                $validatedClassIds[] = intval($classRow['id']);
            }
        }
        $class_ids = $validatedClassIds;
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
            $classFilter = '';
            if ($teacherSubjectsClassIdColumnExists && !empty($class_ids)) {
                $classFilter = ' AND class_id IN (' . implode(',', array_map('intval', $class_ids)) . ')';
            }

            $subject_query = mysqli_query($conn, "SELECT id, class_id FROM subjects WHERE LOWER(subject_name) LIKE '%$keyword%'$classFilter");
            if(!$subject_query) {
                continue;
            }

            while($subject = mysqli_fetch_assoc($subject_query)) {
                $subject_id = intval($subject['id']);
                $subject_class_id = intval($subject['class_id']);
                if ($teacherSubjectsClassIdColumnExists) {
                    $check_query = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id AND class_id = $subject_class_id LIMIT 1";
                    $check_result = mysqli_query($conn, $check_query);
                    if($check_result && mysqli_num_rows($check_result) === 0) {
                        $insert_query = "INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES ($teacher_id, $subject_id, $subject_class_id)";
                        mysqli_query($conn, $insert_query);
                    }
                } else {
                    $check_query = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id LIMIT 1";
                    $check_result = mysqli_query($conn, $check_query);
                    if($check_result && mysqli_num_rows($check_result) === 0) {
                        mysqli_query($conn, "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($teacher_id, $subject_id)");
                    }
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
    $submitted_class_names = mysqli_real_escape_string($conn, $_POST['class_names'] ?? '');
    $class_ids_raw = $_POST['class_id'] ?? [];
    if (!is_array($class_ids_raw)) {
        $class_ids_raw = array_filter(array_map('trim', explode(',', $class_ids_raw)));
    }
    $class_ids = array_values(array_filter(array_map('intval', (array)$class_ids_raw), function($value) {
        return $value > 0;
    }));
    $class_id = count($class_ids) ? $class_ids[0] : 0;
    $class_name = '';
    if (!empty($class_ids)) {
        $class_ids_list = implode(',', $class_ids);
        $class_result = mysqli_query($conn, "SELECT class_name FROM classes WHERE id IN ($class_ids_list)");
        $class_names = [];
        while ($class_row = mysqli_fetch_assoc($class_result)) {
            $class_names[] = $class_row['class_name'];
        }
        $class_name = mysqli_real_escape_string($conn, implode(', ', $class_names));
    }
    if (empty($class_name) && !empty($submitted_class_names)) {
        $class_name = $submitted_class_names;
    }
    $joining_date = $_POST['joining_date'];
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Handle photo upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename));
        $upload_dir = dirname(__DIR__) . '/uploads/teacher-photos/';

        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Invalid photo format. Allowed formats: JPG, JPEG, PNG, GIF.';
            header('Location: teacher-management.php');
            exit();
        }

        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            $_SESSION['error'] = 'Failed to create upload folder for teacher photos.';
            header('Location: teacher-management.php');
            exit();
        }

        $photo = time() . '_' . $filename;
        $upload_path = $upload_dir . $photo;

        if (!is_uploaded_file($_FILES['photo']['tmp_name']) || !move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = 'Failed to upload photo. Please check upload folder permissions and file size.';
            header('Location: teacher-management.php');
            exit();
        }
    }
    
    if(empty($teacher_id)) {
        // Insert new teacher
        $teacher_unique_id = generateTeacherID($conn);
        
        // Check if teacher_id column exists in teachers table
        $teacherIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'teacher_id'");
        $teacherIdColumnExists = ($teacherIdColumnCheck && mysqli_num_rows($teacherIdColumnCheck) > 0);
        
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
            
            // Insert into teachers table - conditionally include teacher_id, class_id/class_name, and assigned_subjects columns
            global $teacherIdColumnExists, $assignedSubjectsColumnExists;
            $columns = "user_id, first_name, last_name, email, phone, qualification, joining_date, address, photo, status";
            $values = "$user_id, '$first_name', '$last_name', '$email', '$phone', '$qualification', '$joining_date', '$address', '$photo', 1";

            if ($class_id > 0) {
                $columns .= ", class_id, class_name";
                $values .= ", $class_id, '$class_name'";
            } elseif(!empty($class_name)) {
                $columns .= ", class_name";
                $values .= ", '$class_name'";
            }

            if ($teacherIdColumnExists) {
                $columns .= ", teacher_id";
                $values .= ", '$teacher_unique_id'";
            }

            if ($assignedSubjectsColumnExists) {
                $columns .= ", assigned_subjects";
                $values .= ", '$assigned_subjects'";
            }

            $teacher_query = "INSERT INTO teachers ($columns) VALUES ($values)";
            
            if(!mysqli_query($conn, $teacher_query)) {
                throw new Exception("Error adding teacher details");
            }

            $inserted_teacher_id = mysqli_insert_id($conn);
            autoAssignPreferredSubjects($conn, $inserted_teacher_id, $assigned_subjects, $class_ids);
            
            // Send credentials email to teacher
            if(!empty($email)) {
                $subject = "Welcome to CoachingPro - Your Login Credentials";
                
                $emailBody = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px; padding: 20px; }
                        .header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 20px; border-radius: 5px; text-align: center; margin-bottom: 20px; }
                        .content { line-height: 1.6; }
                        .credentials { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2a5298; }
                        .credentials p { margin: 10px 0; }
                        .credentials strong { color: #2a5298; }
                        .footer { border-top: 1px solid #ddd; margin-top: 30px; padding-top: 20px; font-size: 12px; color: #666; }
                        .btn-login { display: inline-block; background: #2a5298; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Welcome to CoachingPro</h2>
                            <p>Your teacher account has been created successfully</p>
                        </div>
                        
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong>,</p>
                            
                            <p>Your teacher account has been successfully created in the CoachingPro system. You can now log in and start managing your classes and student results.</p>
                            
                            <div class='credentials'>
                                <p><strong>Your Login Credentials:</strong></p>
                                <p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                                <p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>
                            </div>
                            
                            <p><strong>Important Security Notice:</strong></p>
                            <ul>
                                <li>Please log in and change your password immediately after first login</li>
                                <li>Do not share your login credentials with anyone</li>
                                <li>Keep your password confidential at all times</li>
                            </ul>
                            
                            <a href='" . $_SERVER['HTTP_HOST'] . "' class='btn-login'>Login to CoachingPro</a>
                            
                            <p>If you have any issues logging in or need any assistance, please contact the admin.</p>
                            
                            <p>Best regards,<br>
                            <strong>CoachingPro Admin Team</strong></p>
                        </div>
                        
                        <div class='footer'>
                            <p>This is an automated email. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                sendEmail($email, $subject, $emailBody);
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Teacher added successfully! Teacher ID: " . $teacher_unique_id . " - Credentials sent to email";
            
            
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
        
        // Update query - conditionally include assigned_subjects column
        global $assignedSubjectsColumnExists;
        $update_query = "UPDATE teachers SET 
                         first_name = '$first_name',
                         last_name = '$last_name',
                         email = '$email',
                         phone = '$phone',
                         qualification = '$qualification',
                         joining_date = '$joining_date',
                         address = '$address',
                         photo = '$photo'";
        
        if ($class_id > 0) {
            $update_query .= ", class_id = $class_id, class_name = '$class_name'";
        } else {
            $update_query .= ", class_id = NULL, class_name = ''";
        }
        
        if ($assignedSubjectsColumnExists) {
            $update_query .= ", assigned_subjects = '$assigned_subjects'";
        }
        
        $update_query .= " WHERE id = $teacher_id";
        
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

            // Remove old subject mappings before reapplying assigned subjects
            mysqli_query($conn, "DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id");
            autoAssignPreferredSubjects($conn, $teacher_id, $assigned_subjects, $class_ids);
            
            $_SESSION['success'] = "Teacher updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating teacher!";
        }
    }
    
    header("Location: teacher-management.php");
    exit();
}
?>