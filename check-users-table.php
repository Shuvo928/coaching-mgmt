<?php
require_once 'includes/db.php';
$result = mysqli_query($conn, 'DESCRIBE users');
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}
?>