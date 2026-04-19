<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'DESCRIBE monthly_fees');
echo "monthly_fees table structure:\n";
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' (Null: ' . $row['Null'] . ') DEFAULT: ' . $row['Default'] . "\n";
}
?>