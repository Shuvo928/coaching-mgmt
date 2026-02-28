<?php
// Run this file once to generate password hashes
// Access: http://localhost/coaching-mgmt/admin/create-password-hash.php

echo "Password 'admin123' hash: " . password_hash('admin123', PASSWORD_DEFAULT) . "<br>";
echo "Password 'teacher123' hash: " . password_hash('teacher123', PASSWORD_DEFAULT) . "<br>";
echo "Password 'student123' hash: " . password_hash('student123', PASSWORD_DEFAULT) . "<br>";

// Update these hashes in your database
?>