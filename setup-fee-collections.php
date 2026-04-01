<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Database Setup - Modifying Fee Collections Table</h2>";

// Check current fee_collections structure
$check_query = "SHOW COLUMNS FROM fee_collections WHERE Field = 'student_id'";
$result = mysqli_query($conn, $check_query);
$column_info = mysqli_fetch_assoc($result);

if($column_info['Null'] == 'NO') {
    // Modify the column to allow NULL
    $alter_query = "ALTER TABLE fee_collections MODIFY COLUMN student_id INT NULL";
    
    if(mysqli_query($conn, $alter_query)) {
        echo "<p style='color: green;'><strong>✅ Successfully modified fee_collections table to allow NULL student_id!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ fee_collections table already allows NULL student_id!</strong></p>";
}

echo "<p><strong>Database setup is complete!</strong></p>";
echo "</div>";
?>
