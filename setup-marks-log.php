<?php
session_start();
require_once 'includes/db.php';

// Check if logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<span style='color: red;'>Access Denied. Admin only.</span>";
    exit();
}

echo "<h2>Setting up Marks Log Table</h2>";

// Create marks_log table to store manual mark entries
$create_table_sql = "CREATE TABLE IF NOT EXISTS marks_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    roll_number VARCHAR(50),
    class_id INT,
    group_id INT,
    marks DECIMAL(5, 2),
    exam_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (teacher_id, subject_id, student_name, roll_number, exam_type),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
)";

if (mysqli_query($conn, $create_table_sql)) {
    echo "<span style='color: green;'><strong>✓ Success!</strong> marks_log table created/updated.</span><br><br>";
} else {
    echo "<span style='color: red;'><strong>✗ Error:</strong> " . mysqli_error($conn) . "</span><br><br>";
}

// Add index for faster queries
$add_index_sql = "ALTER TABLE marks_log ADD INDEX idx_teacher_subject (teacher_id, subject_id)";
@mysqli_query($conn, $add_index_sql);

echo "<p>The marks_log table is now ready to store manual mark entries from teachers.</p>";
echo "<p><a href='admin/teacher-dashboard.php'>← Go back to Teacher Dashboard</a></p>";
?>
