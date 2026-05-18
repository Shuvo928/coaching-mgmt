<?php
// Prevent any output before JSON header - MUST BE FIRST
ob_start();

// Log errors to a file for debugging
error_log("=== update-result.php execution started ===");

session_start();
require_once '../includes/db.php';

// Clear any output that may have been generated
ob_end_clean();

// Set JSON header FIRST before any output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Authentication check
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access - Admin only');
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Get input data
    $input = file_get_contents('php://input');
    error_log("Input data: " . substr($input, 0, 500));
    $data = json_decode($input, true);

    if (!isset($data['result_id']) || !is_numeric($data['result_id'])) {
        throw new Exception('Invalid result ID');
    }

    $result_id = intval($data['result_id']);
    $marks = floatval($data['marks'] ?? 0);
    $exam_date = isset($data['exam_date']) && $data['exam_date'] !== '' ? mysqli_real_escape_string($conn, $data['exam_date']) : null;

    // Validation
    if ($marks < 0 || $marks > 100) {
        throw new Exception('Marks must be between 0 and 100');
    }

    // Check if result exists
    $check_sql = "SELECT id FROM results WHERE id = $result_id";
    $check_result = mysqli_query($conn, $check_sql);

    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        throw new Exception('Result not found');
    }

    // Check if results table has total_marks and percentage columns
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

    // Update the result
    $update_fields = "marks_obtained = $marks";
    if ($has_total_marks_column) {
        $update_fields .= ", total_marks = 100";
    }
    if ($has_percentage_column) {
        $update_fields .= ", percentage = $marks";
    }
    if ($exam_date !== null) {
        $update_fields .= ", exam_date = '$exam_date'";
    }

    $update_sql = "UPDATE results SET $update_fields, updated_at = NOW() WHERE id = $result_id";

    if (mysqli_query($conn, $update_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Result updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update result: ' . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in update-result.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
?>