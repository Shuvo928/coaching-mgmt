<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Database Setup - Adding Receipt No Column</h2>";

// Check if receipt_no column exists
$check_query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'coaching_db' 
                AND TABLE_NAME = 'admission_applications' 
                AND COLUMN_NAME = 'receipt_no'";

$result = mysqli_query($conn, $check_query);

if(mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE admission_applications ADD COLUMN receipt_no VARCHAR(50) UNIQUE AFTER application_fee";
    
    if(mysqli_query($conn, $add_column)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'receipt_no' column to admission_applications table!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ The 'receipt_no' column already exists!</strong></p>";
}

echo "<p><strong>Database setup is complete!</strong></p>";
echo "</div>";
?>
