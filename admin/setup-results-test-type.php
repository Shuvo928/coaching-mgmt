<?php
require_once __DIR__ . '/../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Results Table Setup</h2>";

// Check if test_type column exists in results table
$check_query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'coaching_db' 
                AND TABLE_NAME = 'results' 
                AND COLUMN_NAME = 'test_type'";

$result = mysqli_query($conn, $check_query);

if(mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE results ADD COLUMN test_type VARCHAR(50) DEFAULT 'exam' COMMENT 'exam, weekly_test, monthly_test' AFTER subject_id";
    
    if(mysqli_query($conn, $add_column)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'test_type' column to results table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error adding column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'test_type' column already exists in the results table!</strong></p>";
}

// Check if created_at column exists
$check_created = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'coaching_db' 
                AND TABLE_NAME = 'results' 
                AND COLUMN_NAME = 'created_at'";

$result2 = mysqli_query($conn, $check_created);

if(mysqli_num_rows($result2) == 0) {
    // Column doesn't exist, add it
    $add_created = "ALTER TABLE results ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    
    if(mysqli_query($conn, $add_created)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'created_at' column to results table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error adding column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'created_at' column already exists in the results table!</strong></p>";
}

echo "<p><strong>Database setup is complete!</strong></p>";
echo "<p><a href='teacher-dashboard.php' style='color: blue; text-decoration: none;'>← Back to Teacher Dashboard</a></p>";
echo "</div>";
?>
