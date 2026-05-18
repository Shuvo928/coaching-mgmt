<?php
// Prevent any output before JSON header - MUST BE FIRST
ob_start();

// Log errors to a file for debugging
error_log("=== delete-result.php execution started ===");

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

    // Check if result exists
    $check_sql = "SELECT id FROM results WHERE id = $result_id";
    $check_result = mysqli_query($conn, $check_sql);

    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        throw new Exception('Result not found');
    }

    // Delete the result
    $delete_sql = "DELETE FROM results WHERE id = $result_id";
    if (mysqli_query($conn, $delete_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Result deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete result: ' . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in delete-result.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
?>