<?php
require_once __DIR__ . '/../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Adding exam_date Column to Results Table</h2>";

// Check if exam_date column already exists
$check_query = "SHOW COLUMNS FROM results LIKE 'exam_date'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    // Column doesn't exist, add it
    $add_column_sql = "ALTER TABLE results ADD COLUMN exam_date DATE AFTER test_type";
    
    if (mysqli_query($conn, $add_column_sql)) {
        echo "<p style='color: green;'><strong>✅ Success!</strong> 'exam_date' column added to results table.</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error:</strong> " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: blue;'><strong>ℹ️ Info:</strong> 'exam_date' column already exists in results table.</p>";
}

// Show current schema
echo "<h3 style='margin-top: 20px;'>Current Results Table Schema:</h3>";
$schema_result = mysqli_query($conn, "DESCRIBE results");
echo "<table border='1' style='border-collapse:collapse; margin-top: 10px;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($schema_result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='margin-top: 20px;'><a href='teacher-dashboard.php' style='color: blue; text-decoration: none;'>← Back to Teacher Dashboard</a></p>";
echo "</div>";
?>
