<?php
session_start();
require_once '../includes/db.php';

// Check if class_id column exists in teacher_subjects table
$teacherSubjectsClassIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
$teacherSubjectsClassIdColumnExists = ($teacherSubjectsClassIdColumnCheck && mysqli_num_rows($teacherSubjectsClassIdColumnCheck) > 0);

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
                    global $teacherSubjectsClassIdColumnExists;
                    $class_id = intval($subject['class_id']);
                    
                    // Build INSERT query conditionally
                    $columns = "teacher_id, subject_id";
                    $values = "$teacher_id, $subject_id";
                    
                    if ($teacherSubjectsClassIdColumnExists) {
                        $columns .= ", class_id";
                        $values .= ", " . ($class_id ? $class_id : 'NULL');
                    }
                    
                    $insert_query = "INSERT INTO teacher_subjects ($columns) VALUES ($values)";
                    mysqli_query($conn, $insert_query);
                }
            }
        }
    }
}

if(isset($_POST['assign'])) {
    $teacher_id = intval($_POST['teacher_id']);
    if($teacher_id <= 0) {
        $_SESSION['error'] = "Invalid teacher selected for assignment.";
        header("Location: teacher-management.php");
        exit();
    }
    $subjects = $_POST['subjects'] ?? [];
    $subjects = array_map('intval', $subjects);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete existing assignments
        $delete_query = "DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id";
        if(!mysqli_query($conn, $delete_query)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Insert new assignments
        if(!empty($subjects)) {
            foreach($subjects as $subject_id) {
                $subject_id = intval($subject_id);
                $class_id = null;
                $class_result = mysqli_query($conn, "SELECT class_id FROM subjects WHERE id = $subject_id LIMIT 1");
                if(!$class_result) {
                    throw new Exception(mysqli_error($conn));
                }
                if($class_row = mysqli_fetch_assoc($class_result)) {
                    $class_id = intval($class_row['class_id']);
                }
                
                // Build INSERT query conditionally
                global $teacherSubjectsClassIdColumnExists;
                $columns = "teacher_id, subject_id";
                $values = "$teacher_id, $subject_id";
                
                if ($teacherSubjectsClassIdColumnExists) {
                    $columns .= ", class_id";
                    $values .= ", " . ($class_id ? $class_id : 'NULL');
                }
                
                $insert_query = "INSERT INTO teacher_subjects ($columns) VALUES ($values)";
                if(!mysqli_query($conn, $insert_query)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
        }
        
        // Automatically add preferred subjects assignments such as Bangla
        $teacher_query = mysqli_query($conn, "SELECT assigned_subjects FROM teachers WHERE id = $teacher_id LIMIT 1");
        if($teacher_query && $teacher_row = mysqli_fetch_assoc($teacher_query)) {
            autoAssignPreferredSubjects($conn, $teacher_id, $teacher_row['assigned_subjects']);
        }
        
        // Persist assigned subjects to teacher assigned_subjects column if it exists
        $subjects_column_check = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'assigned_subjects'");
        if(!$subjects_column_check) {
            throw new Exception(mysqli_error($conn));
        }
        if(mysqli_num_rows($subjects_column_check) > 0) {
            $subject_names = [];
            if(!empty($subjects)) {
                $subject_ids = implode(',', $subjects);
                $subject_query = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id IN ($subject_ids)");
                if(!$subject_query) {
                    throw new Exception(mysqli_error($conn));
                }
                while($sub = mysqli_fetch_assoc($subject_query)) {
                    $subject_names[] = $sub['subject_name'];
                }
            }
            $subject_list = implode(', ', $subject_names);
            $subject_list_safe = mysqli_real_escape_string($conn, $subject_list);
            if(!mysqli_query($conn, "UPDATE teachers SET assigned_subjects = '$subject_list_safe' WHERE id = $teacher_id")) {
                throw new Exception(mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "Subjects assigned successfully!";
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error assigning subjects: " . $e->getMessage();
    }
    
    header("Location: teacher-management.php");
    exit();
}
?>