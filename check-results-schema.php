<?php
require_once 'includes/db.php';

// Check results table structure
$result = mysqli_query($conn, "DESCRIBE results");
echo "<h2>Results Table Schema:</h2>";
echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Also check a sample row
echo "<h2>Sample Result Record:</h2>";
$sample = mysqli_query($conn, "SELECT * FROM results LIMIT 1");
if (mysqli_num_rows($sample) > 0) {
    $row = mysqli_fetch_assoc($sample);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>No records in results table</p>";
}
?>
