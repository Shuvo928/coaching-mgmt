<?php
// Run this file once to generate hashed password
// Access: http://localhost/coaching-mgmt/admin/create-password.php

echo "<h2>Password Hash Generator</h2>";

$password = "admin123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "<p><strong>Password:</strong> " . $password . "</p>";
echo "<p><strong>Hashed Password:</strong> " . $hashed_password . "</p>";
echo "<p style='color:green;'>Copy this hashed password and update in database</p>";
echo "<hr>";

// Also generate for teacher and student
$teacher_pass = "teacher123";
$student_pass = "student123";

echo "<p><strong>Teacher Password (teacher123):</strong><br>" . password_hash($teacher_pass, PASSWORD_DEFAULT) . "</p>";
echo "<p><strong>Student Password (student123):</strong><br>" . password_hash($student_pass, PASSWORD_DEFAULT) . "</p>";
?>