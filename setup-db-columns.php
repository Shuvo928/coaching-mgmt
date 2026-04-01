<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Database Setup</h2>";

// Check if mother_name column exists in students table
$check_query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'coaching_db' 
                AND TABLE_NAME = 'students' 
                AND COLUMN_NAME = 'mother_name'";

$result = mysqli_query($conn, $check_query);

if(mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE students ADD COLUMN mother_name VARCHAR(100) AFTER father_name";
    
    if(mysqli_query($conn, $add_column)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'mother_name' column to students table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error adding column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'mother_name' column already exists in the students table!</strong></p>";
}

echo "<p><strong>Database setup is complete!</strong></p>";
echo "<p><a href='admin/student-management.php' style='color: blue; text-decoration: none;'>← Back to Student Management</a></p>";
echo "</div>";
?>
