<?php
$host = 'localhost';
$dbname = 'coaching_db';
$username = 'root';
$password = '';

try {
    // PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // MySQLi connection
    $conn = mysqli_connect($host, $username, $password, $dbname);
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, "utf8");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>