<?php
require_once __DIR__ . '/../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Results Table Schema Verification & Repair</h2>";

$required_columns = [
    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'student_id' => 'INT NOT NULL',
    'subject_id' => 'INT NOT NULL',
    'test_type' => 'VARCHAR(50)',
    'marks_obtained' => 'DECIMAL(5, 2)',
    'exam_date' => 'DATE',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
];

// Get existing columns
$show_columns = "SHOW COLUMNS FROM results";
$result = mysqli_query($conn, $show_columns);

$existing_columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $existing_columns[] = $row['Field'];
}

echo "<h3>Current Columns:</h3>";
echo "<p>" . implode(", ", $existing_columns) . "</p>";

echo "<h3>Adding Missing Columns:</h3>";

$columns_to_add = [
    'exam_date' => "DATE",
    'total_marks' => "INT DEFAULT 100",
    'percentage' => "DECIMAL(5, 2)",
    'marks_obtained' => "DECIMAL(5, 2)"
];

foreach ($columns_to_add as $col => $type) {
    if (!in_array($col, $existing_columns)) {
        $add_col = "ALTER TABLE results ADD COLUMN $col $type";
        if (mysqli_query($conn, $add_col)) {
            echo "<p style='color: green;'>✅ Added column: $col</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding $col: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Column already exists: $col</p>";
    }
}

echo "<h3>Final Schema:</h3>";
$result = mysqli_query($conn, "DESCRIBE results");
echo "<table border='1' style='border-collapse:collapse; margin-top: 10px;'>";
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

echo "<p style='margin-top: 20px;'><a href='teacher-dashboard.php' style='color: blue; text-decoration: none;'>← Back to Teacher Dashboard</a></p>";
echo "</div>";
?>
