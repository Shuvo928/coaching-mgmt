<?php
require_once 'includes/db.php';

echo "<h2>Results Table Schema:</h2>";
$result = mysqli_query($conn, "DESCRIBE results");
if ($result) {
    echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
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
} else {
    echo "<p style='color:red;'>Error: " . mysqli_error($conn) . "</p>";
}

echo "<h2>Error Log:</h2>";
if (file_exists('../logs/save-marks-error.log')) {
    echo "<pre>";
    echo htmlspecialchars(file_get_contents('../logs/save-marks-error.log'));
    echo "</pre>";
} else {
    echo "<p>No error log file yet.</p>";
}

echo "<h2>Test Query - Check teacher_subjects:</h2>";
$teacher_id = 1; // Adjust as needed
$test_query = "SELECT COUNT(*) as count FROM teacher_subjects WHERE teacher_id = $teacher_id";
$test_result = mysqli_query($conn, $test_query);
if ($test_result) {
    $row = mysqli_fetch_assoc($test_result);
    echo "<p>Teacher $teacher_id has " . $row['count'] . " subjects assigned</p>";
}
?>
