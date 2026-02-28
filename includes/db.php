<?php
$host = 'localhost';
$dbname = 'coaching_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // For procedural MySQLi (optional)
    $conn = mysqli_connect($host, $username, $password, $dbname);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>