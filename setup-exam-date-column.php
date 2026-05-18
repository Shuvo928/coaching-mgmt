<?php
require_once 'includes/db.php';

// Check if exam_date column exists in results table
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'exam_date'");

if (mysqli_num_rows($check_column) == 0) {
    // Column doesn't exist, add it
    $alter_sql = "ALTER TABLE results ADD COLUMN exam_date DATE DEFAULT NULL AFTER test_type";
    
    if (mysqli_query($conn, $alter_sql)) {
        echo "<div style='color: green; padding: 20px; font-size: 16px;'>";
        echo "<h3>✓ Success!</h3>";
        echo "<p>The 'exam_date' column has been successfully added to the results table.</p>";
        echo "<p>You can now set exam dates when entering marks.</p>";
        echo "<a href='admin/teacher-dashboard.php' style='background: #2c3e66; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Teacher Dashboard</a>";
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 20px; font-size: 16px;'>";
        echo "<h3>✗ Error!</h3>";
        echo "<p>Failed to add exam_date column: " . mysqli_error($conn) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='color: blue; padding: 20px; font-size: 16px;'>";
    echo "<h3>ℹ Information</h3>";
    echo "<p>The 'exam_date' column already exists in the results table.</p>";
    echo "<a href='admin/teacher-dashboard.php' style='background: #2c3e66; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Teacher Dashboard</a>";
    echo "</div>";
}

mysqli_close($conn);
?>
