<?php
require_once 'includes/db.php';

// Test: Check if all required columns exist in results table
echo "<h2>Results Table Column Check:</h2>";

$columns_needed = ['id', 'student_id', 'subject_id', 'test_type', 'marks_obtained', 'exam_date', 'created_at'];
$result = mysqli_query($conn, "SHOW COLUMNS FROM results");

$existing_columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $existing_columns[] = $row['Field'];
}

echo "Columns needed: " . implode(", ", $columns_needed) . "<br>";
echo "Columns found: " . implode(", ", $existing_columns) . "<br><br>";

$missing = array_diff($columns_needed, $existing_columns);
if (!empty($missing)) {
    echo "<p style='color:red;'><strong>Missing columns:</strong> " . implode(", ", $missing) . "</p>";
} else {
    echo "<p style='color:green;'><strong>All required columns are present</strong></p>";
}

// Test a sample insert
echo "<h2>Sample Insert Test:</h2>";
$test_insert = "INSERT INTO results (student_id, subject_id, test_type, marks_obtained, exam_date, created_at) 
                VALUES (1, 1, 'weekly_test', 85, '2026-05-14', NOW())";
echo "SQL: $test_insert<br>";

if (mysqli_query($conn, $test_insert)) {
    echo "<p style='color:green;'>Insert succeeded</p>";
    // Get the last inserted ID to verify
    $last_id = mysqli_insert_id($conn);
    echo "Last inserted ID: $last_id<br>";
    
    // Delete test record
    mysqli_query($conn, "DELETE FROM results WHERE id = $last_id");
} else {
    echo "<p style='color:red;'>Insert failed: " . mysqli_error($conn) . "</p>";
}
?>
