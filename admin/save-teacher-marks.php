<?php
// Prevent any output before JSON header - MUST BE FIRST
ob_start();

// Log errors to a file for debugging
error_log("=== save-teacher-marks.php execution started ===");

session_start();
require_once '../includes/db.php';

// Clear any output that may have been generated
ob_end_clean();

// Set JSON header FIRST before any output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Authentication check
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
        throw new Exception('Unauthorized access');
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    $user_id = $_SESSION['user_id'];

    // Get teacher details
    $teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
    $teacher_result = mysqli_query($conn, $teacher_query);
    if (!$teacher_result || mysqli_num_rows($teacher_result) == 0) {
        throw new Exception('Teacher record not found');
    }
    $teacher = mysqli_fetch_assoc($teacher_result);
    $teacher_id = $teacher['id'];

    // Get input data
    $input = file_get_contents('php://input');
    error_log("Input data: " . substr($input, 0, 500));
    $data = json_decode($input, true);

    if (!isset($data['marks']) || !is_array($data['marks'])) {
        throw new Exception('Invalid data format: marks array not found');
    }

    $marks_to_save = $data['marks'];
    $saved_count = 0;
    $errors = [];

    $has_total_marks_column = false;
    $has_percentage_column = false;
    $has_exam_date_column = false;
    $has_updated_at_column = false;
    
    $totalMarksColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'total_marks'");
    if ($totalMarksColumnRes && mysqli_num_rows($totalMarksColumnRes) > 0) {
        $has_total_marks_column = true;
    }
    $percentageColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'percentage'");
    if ($percentageColumnRes && mysqli_num_rows($percentageColumnRes) > 0) {
        $has_percentage_column = true;
    }
    $examDateColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'exam_date'");
    if ($examDateColumnRes && mysqli_num_rows($examDateColumnRes) > 0) {
        $has_exam_date_column = true;
    }
    $updatedAtColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'updated_at'");
    if ($updatedAtColumnRes && mysqli_num_rows($updatedAtColumnRes) > 0) {
        $has_updated_at_column = true;
    }

    foreach ($marks_to_save as $item) {
        $result_id = intval($item['result_id'] ?? 0);
        $student_id = intval($item['student_id'] ?? 0);
        $subject_id = intval($item['subject_id'] ?? 0);
        $exam_type = mysqli_real_escape_string($conn, $item['exam_type'] ?? '');
        $marks = floatval($item['marks'] ?? 0);
        $exam_date = isset($item['exam_date']) && $item['exam_date'] !== '' ? mysqli_real_escape_string($conn, $item['exam_date']) : null;
        
        error_log("Processing: ResultID=$result_id, Student=$student_id, Subject=$subject_id, Type=$exam_type, Marks=$marks, Date=$exam_date");
        
        // Validation
        if ($student_id <= 0 || $subject_id <= 0) {
            $errors[] = "Invalid student or subject ID";
            continue;
        }
        
        if ($marks < 0 || $marks > 100) {
            $errors[] = "Marks for student $student_id must be between 0 and 100";
            continue;
        }
        
        // Security: verify teacher is allowed to enter marks for this subject
        $check_sql = "SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
        $check_res = mysqli_query($conn, $check_sql);
        if (!$check_res || mysqli_num_rows($check_res) == 0) {
            $errors[] = "You are not allowed to add marks for subject ID $subject_id";
            continue;
        }

        if ($result_id > 0) {
            $result_check_sql = "SELECT r.student_id, r.subject_id, r.test_type FROM results r
                                 JOIN teacher_subjects ts ON r.subject_id = ts.subject_id
                                 WHERE r.id = $result_id AND ts.teacher_id = $teacher_id";
            $result_check_res = mysqli_query($conn, $result_check_sql);
            if (!$result_check_res || mysqli_num_rows($result_check_res) == 0) {
                $errors[] = "Result record not found or permission denied for result ID $result_id";
                continue;
            }
            $existing_result = mysqli_fetch_assoc($result_check_res);
            if (intval($existing_result['student_id']) !== $student_id) {
                $errors[] = "Cannot change the student for result ID $result_id";
                continue;
            }

            $update_fields = "subject_id = $subject_id, test_type = '$exam_type', marks_obtained = $marks";
            if ($has_total_marks_column) {
                $update_fields .= ", total_marks = 100";
            }
            if ($has_percentage_column) {
                $update_fields .= ", percentage = $marks";
            }
            if ($has_exam_date_column) {
                $update_fields .= $exam_date !== null ? ", exam_date = '$exam_date'" : ", exam_date = NULL";
            }
            if ($has_updated_at_column) {
                $update_fields .= ", updated_at = NOW()";
            }

            $update_sql = "UPDATE results SET $update_fields WHERE id = $result_id";
            error_log("UPDATE SQL: $update_sql");
            if (mysqli_query($conn, $update_sql)) {
                $saved_count++;
            } else {
                $db_error = mysqli_error($conn);
                error_log("UPDATE Error: $db_error");
                $errors[] = "Error updating marks for student $student_id: " . $db_error;
            }
            continue;
        }

        // Check if result already exists (student_id + subject_id + test_type)
        $exists_sql = "SELECT id FROM results WHERE student_id = $student_id AND subject_id = $subject_id AND test_type = '$exam_type'";
        $exists_res = mysqli_query($conn, $exists_sql);
        
        if (!$exists_res) {
            $errors[] = "Database query error: " . mysqli_error($conn);
            continue;
        }
        
        if (mysqli_num_rows($exists_res) > 0) {
            // Update existing record
            $update_fields = "marks_obtained = $marks";
            if ($has_total_marks_column) {
                $update_fields .= ", total_marks = 100";
            }
            if ($has_percentage_column) {
                $update_fields .= ", percentage = $marks";
            }
            if ($has_exam_date_column && $exam_date !== null) {
                $update_fields .= ", exam_date = '$exam_date'";
            }
            if ($has_updated_at_column) {
                $update_fields .= ", updated_at = NOW()";
            }
            $update_sql = "UPDATE results SET $update_fields, created_at = NOW()
                          WHERE student_id = $student_id AND subject_id = $subject_id AND test_type = '$exam_type'";
            error_log("UPDATE SQL: $update_sql");
            if (mysqli_query($conn, $update_sql)) {
                $saved_count++;
            } else {
                $db_error = mysqli_error($conn);
                error_log("UPDATE Error: $db_error");
                $errors[] = "Error updating marks for student $student_id: " . $db_error;
            }
        } else {
            // Insert new record
            $insert_columns = "student_id, subject_id, test_type, marks_obtained";
            $insert_values = "$student_id, $subject_id, '$exam_type', $marks";
            if ($has_total_marks_column) {
                $insert_columns .= ", total_marks";
                $insert_values .= ", 100";
            }
            if ($has_percentage_column) {
                $insert_columns .= ", percentage";
                $insert_values .= ", $marks";
            }
            if ($has_exam_date_column) {
                $insert_columns .= ", exam_date";
                $insert_values .= $exam_date !== null ? ", '$exam_date'" : ", NULL";
            }
            $insert_columns .= ", created_at";
            $insert_values .= ", NOW()";
            $insert_sql = "INSERT INTO results ($insert_columns) VALUES ($insert_values)";
            error_log("INSERT SQL: $insert_sql");
            if (mysqli_query($conn, $insert_sql)) {
                $saved_count++;
            } else {
                $db_error = mysqli_error($conn);
                error_log("INSERT Error: $db_error");
                $errors[] = "Error inserting marks for student $student_id: " . $db_error;
            }
        }
    }

    // Return response
    if ($saved_count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "Successfully saved $saved_count student mark(s)" . (count($errors) > 0 ? ". Errors: " . implode(", ", $errors) : ""),
            'saved_count' => $saved_count,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "No marks were saved. " . (count($errors) > 0 ? implode("; ", $errors) : "Unknown error"),
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    $error_msg = "Exception in save-teacher-marks.php: " . $e->getMessage();
    error_log($error_msg);
    // Also write to a file for debugging
    file_put_contents('../logs/save-marks-error.log', date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
?>
