<?php
// Prevent any output before JSON header - MUST BE FIRST
ob_start();

// Log errors to a file for debugging
error_log("=== save-manual-marks.php execution started ===");

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
    $totalMarksColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'total_marks'");
    if ($totalMarksColumnRes && mysqli_num_rows($totalMarksColumnRes) > 0) {
        $has_total_marks_column = true;
    }
    $percentageColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'percentage'");
    if ($percentageColumnRes && mysqli_num_rows($percentageColumnRes) > 0) {
        $has_percentage_column = true;
    }

    foreach ($marks_to_save as $item) {
        $student_id = intval($item['student_id'] ?? 0);
        $student_name = mysqli_real_escape_string($conn, $item['student_name'] ?? '');
        $roll_number = intval($item['roll_number'] ?? 0);
        $subject_id = intval($item['subject_id'] ?? 0);
        $exam_type = mysqli_real_escape_string($conn, $item['exam_type'] ?? '');
        $marks = floatval($item['marks'] ?? 0);
        $class_id = intval($item['class_id'] ?? 0);
        $group_id = intval($item['group_id'] ?? 0);
        $exam_date = isset($item['exam_date']) && $item['exam_date'] !== '' ? mysqli_real_escape_string($conn, $item['exam_date']) : null;
        
        // Validation
        if (empty($student_name)) {
            $errors[] = "Student name is required";
            continue;
        }
        
        if ($subject_id <= 0 || $class_id <= 0) {
            $errors[] = "Invalid subject or class ID";
            continue;
        }
        
        if ($marks < 0 || $marks > 100) {
            $errors[] = "Marks for $student_name must be between 0 and 100";
            continue;
        }
        
        if (empty($exam_type)) {
            $errors[] = "Exam type is required";
            continue;
        }
        
        // Security: verify teacher is allowed to enter marks for this subject
        $check_sql = "SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
        $check_res = mysqli_query($conn, $check_sql);
        if (!$check_res || mysqli_num_rows($check_res) == 0) {
            $errors[] = "You are not allowed to add marks for subject ID $subject_id";
            continue;
        }
        
        // If student_id is provided (from frontend dropdown), use it directly
        // Otherwise, try to find by name and roll_number
        if ($student_id <= 0) {
            // Fallback: find student by name and roll_number
            $student_check = "SELECT id FROM students WHERE LOWER(CONCAT(first_name, ' ', last_name)) = LOWER('$student_name') 
                              AND id = '$roll_number' AND class_id = $class_id";
            $student_check_res = mysqli_query($conn, $student_check);
            
            if ($student_check_res && mysqli_num_rows($student_check_res) > 0) {
                $student = mysqli_fetch_assoc($student_check_res);
                $student_id = $student['id'];
            } else {
                $errors[] = "Student $student_name with ID $roll_number not found in class $class_id";
                continue;
            }
        }
        
        if ($student_id <= 0) {
            $errors[] = "Could not determine student ID for $student_name";
            continue;
        }
        
        // Check if result already exists
        $exists_sql = "SELECT id FROM results WHERE student_id = $student_id AND subject_id = $subject_id AND test_type = '$exam_type'";
        $exists_res = mysqli_query($conn, $exists_sql);
        
        if (!$exists_res) {
            $errors[] = "Database query error: " . mysqli_error($conn);
            continue;
        }
        
        if (mysqli_num_rows($exists_res) > 0) {
            // Update existing record
            $exam_date_clause = $exam_date !== null ? ", exam_date = '$exam_date'" : "";
            $update_fields = "marks_obtained = $marks";
            if ($has_total_marks_column) {
                $update_fields .= ", total_marks = 100";
            }
            if ($has_percentage_column) {
                $update_fields .= ", percentage = $marks";
            }
            $update_sql = "UPDATE results SET $update_fields, created_at = NOW() $exam_date_clause
                          WHERE student_id = $student_id AND subject_id = $subject_id AND test_type = '$exam_type'";
            if (mysqli_query($conn, $update_sql)) {
                $saved_count++;
            } else {
                $errors[] = "Error updating marks for $student_name: " . mysqli_error($conn);
            }
        } else {
            // Insert new record
            $exam_date_clause = $exam_date !== null ? ", '$exam_date'" : ", NULL";
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
            $insert_columns .= ", exam_date, created_at";
            $insert_values .= $exam_date_clause . ", NOW()";
            $insert_sql = "INSERT INTO results ($insert_columns) VALUES ($insert_values)";
            if (mysqli_query($conn, $insert_sql)) {
                $saved_count++;
            } else {
                $errors[] = "Error inserting marks for $student_name: " . mysqli_error($conn);
            }
        }
    }

    // Return response
    if ($saved_count > 0) {
        echo json_encode([
            'success' => true,
            'saved_count' => $saved_count,
            'message' => "Successfully saved $saved_count student mark(s)" . (count($errors) > 0 ? ". Errors: " . implode(", ", $errors) : "")
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'saved_count' => 0,
            'message' => "No marks were saved. " . (count($errors) > 0 ? implode(", ", $errors) : "Unknown error"),
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in save-manual-marks.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
?>
