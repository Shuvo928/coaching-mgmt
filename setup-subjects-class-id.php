<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 900px; margin: 30px auto;'>";
echo "<h2>Database Migration: Fix subjects table structure</h2>";

$changes_made = [];
$errors = [];

// 1. Check and add subject_code column if missing
$check_subject_code = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'subject_code'");
if($check_subject_code && mysqli_num_rows($check_subject_code) == 0) {
    echo "<p>⏳ Adding 'subject_code' column to subjects table...</p>";
    $add_subject_code = "ALTER TABLE subjects ADD COLUMN subject_code VARCHAR(50) UNIQUE AFTER subject_name";
    
    if(mysqli_query($conn, $add_subject_code)) {
        $changes_made[] = "✅ Added 'subject_code' column";
        echo "<p style='color: green;'><strong>✅ Successfully added 'subject_code' column!</strong></p>";
    } else {
        $errors[] = "Error adding subject_code: " . mysqli_error($conn);
        echo "<p style='color: red;'><strong>❌ Error adding subject_code column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: blue;'><strong>ℹ️ 'subject_code' column already exists</strong></p>";
}

// 2. Check and add class_id column if missing
$check_class_id = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'class_id'");
if($check_class_id && mysqli_num_rows($check_class_id) == 0) {
    echo "<p>⏳ Adding 'class_id' column to subjects table...</p>";
    
    // Check if subject_code exists for positioning
    $check_code_exists = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'subject_code'");
    $after_column = ($check_code_exists && mysqli_num_rows($check_code_exists) > 0) ? 'subject_code' : 'subject_name';
    
    $add_class_id = "ALTER TABLE subjects ADD COLUMN class_id INT UNSIGNED AFTER $after_column";
    
    if(mysqli_query($conn, $add_class_id)) {
        $changes_made[] = "✅ Added 'class_id' column";
        echo "<p style='color: green;'><strong>✅ Successfully added 'class_id' column!</strong></p>";
    } else {
        $errors[] = "Error adding class_id: " . mysqli_error($conn);
        echo "<p style='color: red;'><strong>❌ Error adding class_id column: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: blue;'><strong>ℹ️ 'class_id' column already exists</strong></p>";
}

// Summary
echo "<hr>";
if (count($changes_made) > 0) {
    echo "<h3>Changes Made:</h3>";
    echo "<ul>";
    foreach ($changes_made as $change) {
        echo "<li>$change</li>";
    }
    echo "</ul>";
}

if (count($errors) > 0) {
    echo "<h3 style='color: red;'>Errors:</h3>";
    echo "<ul style='color: red;'>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'><strong>✅ Migration completed successfully!</strong></p>";
    echo "<p style='color: blue;'><strong>ℹ️ Note: You may need to assign class_id values to existing subjects through the class management interface.</strong></p>";
}

echo "<p><a href='admin/class-management.php' style='color: blue; text-decoration: none;'>← Back to Class Management</a></p>";
echo "</div>";
?>
