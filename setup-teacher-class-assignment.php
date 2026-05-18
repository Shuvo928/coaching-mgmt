<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Teacher Class Assignment Setup</h2>";

// Check if class_id column exists in teachers table
$check_class_id = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'coaching_db1' 
                   AND TABLE_NAME = 'teachers' 
                   AND COLUMN_NAME = 'class_id'";

$result_class_id = mysqli_query($conn, $check_class_id);

if(mysqli_num_rows($result_class_id) == 0) {
    // class_id column doesn't exist, add it
    $add_class_id = "ALTER TABLE teachers ADD COLUMN class_id INT(11) DEFAULT NULL AFTER qualification";
    
    if(mysqli_query($conn, $add_class_id)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'class_id' column to teachers table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error adding class_id column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'class_id' column already exists in the teachers table!</strong></p>";
}

// Check if class_name column exists in teachers table
$check_class_name = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = 'coaching_db1' 
                     AND TABLE_NAME = 'teachers' 
                     AND COLUMN_NAME = 'class_name'";

$result_class_name = mysqli_query($conn, $check_class_name);

if(mysqli_num_rows($result_class_name) == 0) {
    // class_name column doesn't exist, add it
    $add_class_name = "ALTER TABLE teachers ADD COLUMN class_name VARCHAR(100) DEFAULT NULL AFTER class_id";
    
    if(mysqli_query($conn, $add_class_name)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'class_name' column to teachers table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error adding class_name column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'class_name' column already exists in the teachers table!</strong></p>";
}

echo "<hr style='margin: 20px 0;'>";
echo "<h3>Summary</h3>";
echo "<p><strong>What this script does:</strong></p>";
echo "<ul>";
echo "<li>Adds 'class_id' column to teachers table (stores the class ID)</li>";
echo "<li>Adds 'class_name' column to teachers table (stores the class name for display)</li>";
echo "<li>These columns allow teachers to be assigned to specific classes</li>";
echo "<li>This data will now be visible in the Teacher Management page</li>";
echo "</ul>";
echo "<p style='color: green;'><strong>✅ Database setup is complete! You can now assign classes to teachers.</strong></p>";
echo "<p><a href='admin/teacher-management.php' style='color: blue; text-decoration: none;'>← Back to Teacher Management</a></p>";
echo "</div>";
?>
