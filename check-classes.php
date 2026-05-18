<?php
require_once 'includes/db.php';

echo "<h2>Classes in Database:</h2>";
$classes_query = "SELECT * FROM classes";
$result = mysqli_query($conn, $classes_query);
echo "<pre>";
while($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}
echo "</pre>";
?>
