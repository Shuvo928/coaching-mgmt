<?php
require_once 'includes/db.php';

$result = mysqli_query($conn, 'DESCRIBE fee_collections');
echo "<pre>";
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' (Null: ' . $row['Null'] . ') DEFAULT: ' . $row['Default'] . "\n";
}
echo "</pre>";

echo "<br><h3>Current fee_collections data:</h3>";
$fees = mysqli_query($conn, "SELECT * FROM fee_collections ORDER BY created_at DESC LIMIT 5");
echo "<pre>";
echo json_encode(mysqli_fetch_all($fees, MYSQLI_ASSOC), JSON_PRETTY_PRINT);
echo "</pre>";
?>
