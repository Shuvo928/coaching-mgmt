<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'DESCRIBE results');
echo "results table structure:\n";
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' (Null: ' . $row['Null'] . ')\n';
}
?>