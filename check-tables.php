<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'SHOW TABLES');
echo "Available tables:\n";
while($row = mysqli_fetch_row($result)) {
    echo $row[0] . "\n";
}
?>