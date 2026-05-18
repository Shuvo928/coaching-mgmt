<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "coaching_db1";

/** @var \mysqli|null $conn */
$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // Check if this is a JSON endpoint
    if (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', 'save-') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit;
    } else {
        die("Connection failed: " . mysqli_connect_error());
    }
}