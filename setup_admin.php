<?php
include 'db.php';

$username = "admin12";
$email = "admin12@gmail.com";
$password = "admin12";
$role = "admin";

// Store only the hash in the database so the plain password is not visible.
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $email, $hash, $role);

if ($stmt->execute()) {
    echo "Admin created successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
?>