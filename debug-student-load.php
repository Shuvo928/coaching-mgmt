<?php
require_once 'includes/db.php';

// Get all students
$result = mysqli_query($conn, "SELECT id, first_name, last_name, roll_number, class_id, group_id FROM students LIMIT 10");

echo "<h3>Students Data:</h3>";
echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Roll</th><th>Class ID</th><th>Group ID</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
    echo "<td>" . $row['roll_number'] . "</td>";
    echo "<td>" . $row['class_id'] . "</td>";
    echo "<td>" . $row['group_id'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check classes
echo "<h3>Classes:</h3>";
$class_result = mysqli_query($conn, "SELECT id, class_name FROM classes");
echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
echo "<tr><th>ID</th><th>Class Name</th></tr>";
while ($row = mysqli_fetch_assoc($class_result)) {
    echo "<tr><td>" . $row['id'] . "</td><td>" . $row['class_name'] . "</td></tr>";
}
echo "</table>";

// Check groups
echo "<h3>Groups:</h3>";
$group_result = mysqli_query($conn, "SELECT id, group_name FROM `groups`");
echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
echo "<tr><th>ID</th><th>Group Name</th></tr>";
while ($row = mysqli_fetch_assoc($group_result)) {
    echo "<tr><td>" . $row['id'] . "</td><td>" . $row['group_name'] . "</td></tr>";
}
echo "</table>";
?>
