<?php
require_once 'includes/db.php';
$result = mysqli_query($conn, 'SELECT s.id, s.first_name, s.last_name, s.phone, u.username FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = 6');
echo json_encode(mysqli_fetch_assoc($result), JSON_PRETTY_PRINT);
?>