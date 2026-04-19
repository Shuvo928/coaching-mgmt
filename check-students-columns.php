<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'DESCRIBE students');
echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC), JSON_PRETTY_PRINT);
?>