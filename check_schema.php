<?php
$conn = new mysqli('localhost', 'root', '', 'coaching_db1');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== STUDENTS TABLE STRUCTURE ===\n";
$result = $conn->query('DESCRIBE students');
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== SAMPLE DATA ===\n";
$sample = $conn->query('SELECT id, roll_number, first_name, last_name, class_id FROM students LIMIT 5');
while ($row = $sample->fetch_assoc()) {
    echo "ID: {$row['id']}, Roll: {$row['roll_number']}, Name: {$row['first_name']} {$row['last_name']}\n";
}
$conn->close();
?>
