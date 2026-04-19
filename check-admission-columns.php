<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'DESCRIBE admission_applications');
echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC), JSON_PRETTY_PRINT);
?>