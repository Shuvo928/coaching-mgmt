<?php
session_start();
require_once './includes/db.php';

echo "<h2>Fix Missing Monthly Fees for Yamin Raj</h2>";

// Find Yamin Raj's student record
$student_query = "SELECT * FROM students WHERE first_name LIKE '%Yamin%' AND last_name LIKE '%Raj%' ORDER BY id DESC LIMIT 1";
$student_result = mysqli_query($conn, $student_query);

if(mysqli_num_rows($student_result) > 0) {
    $student = mysqli_fetch_assoc($student_result);
    $student_id = $student['id'];
    $class_id = $student['class_id'];
    
    echo "<p>Found student: <strong>{$student['first_name']} {$student['last_name']}</strong> (ID: {$student_id}, Class: {$class_id})</p>";
    
    // Get class fee
    $class_query = "SELECT id, monthly_fee FROM classes WHERE id = {$class_id}";
    $class_result = mysqli_query($conn, $class_query);
    
    if(mysqli_num_rows($class_result) > 0) {
        $class = mysqli_fetch_assoc($class_result);
        $monthly_fee = $class['monthly_fee'];
        
        echo "<p>Class monthly fee: <strong>৳{$monthly_fee}</strong></p>";
        
        // Check if May 2026 fee already exists
        $check_query = "SELECT * FROM fee_collections WHERE student_id = {$student_id} AND fee_month LIKE '%May 2026%'";
        $check_result = mysqli_query($conn, $check_query);
        
        if(mysqli_num_rows($check_result) > 0) {
            echo "<p style='color: green;'>✅ May 2026 fee already exists for this student</p>";
        } else {
            // Create missing fee record
            $insert_query = "INSERT INTO fee_collections 
                (student_id, fee_month, expected_amount, paid_amount, payment_status, due_date, created_at)
                VALUES ({$student_id}, 'May 2026', {$monthly_fee}, 0, 'unpaid', '2026-05-10', NOW())";
            
            if(mysqli_query($conn, $insert_query)) {
                echo "<p style='color: green;'><strong>✅ SUCCESS!</strong> Created May 2026 fee record:</p>";
                echo "<ul>";
                echo "<li>Student ID: {$student_id}</li>";
                echo "<li>Fee Month: May 2026</li>";
                echo "<li>Expected Amount: ৳{$monthly_fee}</li>";
                echo "<li>Due Date: 10 May 2026</li>";
                echo "</ul>";
                
                // Verify it was created
                $verify_query = "SELECT * FROM fee_collections WHERE student_id = {$student_id} AND fee_month LIKE '%May 2026%'";
                $verify_result = mysqli_query($conn, $verify_query);
                if(mysqli_num_rows($verify_result) > 0) {
                    echo "<p style='color: green;'>✅ Verified: Fee record created successfully</p>";
                }
            } else {
                echo "<p style='color: red;'><strong>❌ ERROR:</strong> " . mysqli_error($conn) . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Class not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Student not found. Checking all students...</p>";
    
    // Show all students
    echo "<h3>All Students in Database:</h3>";
    $all_students = mysqli_query($conn, "SELECT id, first_name, last_name, class_id FROM students ORDER BY id");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #3498db; color: white;'><th>ID</th><th>Name</th><th>Class</th></tr>";
    while($row = mysqli_fetch_assoc($all_students)) {
        echo "<tr><td>{$row['id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['class_id']}</td></tr>";
    }
    echo "</table>";
}
?>
