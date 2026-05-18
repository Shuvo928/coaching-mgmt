<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['admin']);

echo "<h2>Database Diagnostic Report</h2>";

// 1. Check if subjects table exists
echo "<h3>1. Subjects Table Check</h3>";
$subjects_check = mysqli_query($conn, "SHOW COLUMNS FROM subjects");
if($subjects_check) {
    echo "<p><strong>✓ Subjects table exists</strong></p>";
    echo "<p>Columns: ";
    while($col = mysqli_fetch_assoc($subjects_check)) {
        echo $col['Field'] . " (" . $col['Type'] . ") | ";
    }
    echo "</p>";
} else {
    echo "<p><strong>✗ Error: Subjects table not found</strong></p>";
}

// 2. Count subjects
echo "<h3>2. Subjects Count</h3>";
$count = mysqli_query($conn, "SELECT COUNT(*) as total FROM subjects");
$count_result = mysqli_fetch_assoc($count);
echo "<p>Total subjects: <strong>" . $count_result['total'] . "</strong></p>";

// 3. Check subjects with class info
echo "<h3>3. All Subjects with Class Info</h3>";
$subjects = mysqli_query($conn, "SELECT s.id, s.subject_name, s.class_id, c.id as class_exists_id, c.class_name FROM subjects s LEFT JOIN classes c ON s.class_id = c.id");
if(mysqli_num_rows($subjects) > 0) {
    echo "<table border='1' cellpadding='10'><tr><th>Subject ID</th><th>Subject Name</th><th>Subject's class_id</th><th>Class Exists?</th><th>Class Name</th></tr>";
    while($row = mysqli_fetch_assoc($subjects)) {
        $class_status = $row['class_exists_id'] ? '✓ YES' : '✗ NO (NULL or Invalid)';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['subject_name'] . "</td>";
        echo "<td>" . ($row['class_id'] ?? 'NULL') . "</td>";
        echo "<td>" . $class_status . "</td>";
        echo "<td>" . ($row['class_name'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>✗ No subjects found in database</strong></p>";
}

// 4. Check classes table
echo "<h3>4. Classes Table Check</h3>";
$classes = mysqli_query($conn, "SELECT id, class_name FROM classes");
if(mysqli_num_rows($classes) > 0) {
    echo "<table border='1' cellpadding='10'><tr><th>Class ID</th><th>Class Name</th></tr>";
    while($row = mysqli_fetch_assoc($classes)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['class_name'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>✗ No classes found in database</strong></p>";
}

// 5. Test the exact query from assign-subjects.php
echo "<h3>5. Test Query from assign-subjects.php</h3>";
$test_query = "SELECT s.id, s.subject_name, c.class_name, c.id as class_id
               FROM subjects s
               JOIN classes c ON s.class_id = c.id
               ORDER BY c.class_name, s.subject_name";
$test_result = mysqli_query($conn, $test_query);
if($test_result) {
    $count = mysqli_num_rows($test_result);
    echo "<p>Query result: <strong>" . $count . " subjects found</strong></p>";
    if($count > 0) {
        echo "<p>Sample results:</p>";
        while($row = mysqli_fetch_assoc($test_result)) {
            echo "- " . $row['subject_name'] . " (Class: " . $row['class_name'] . ")<br>";
        }
    }
} else {
    echo "<p><strong>✗ Query Error:</strong> " . mysqli_error($conn) . "</p>";
}

// 6. Teacher info for teacher_id=5
echo "<h3>6. Teacher Info (ID=5)</h3>";
$teacher = mysqli_query($conn, "SELECT * FROM teachers WHERE id = 5");
if(mysqli_num_rows($teacher) > 0) {
    $row = mysqli_fetch_assoc($teacher);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p><strong>✗ Teacher with ID=5 not found</strong></p>";
}

?>
<style>
    body { font-family: Arial; padding: 20px; }
    h3 { color: #333; margin-top: 20px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    td, th { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #667eea; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    p { line-height: 1.6; }
</style>
