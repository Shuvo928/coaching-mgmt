<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 1200px; margin: 30px auto; background: white; border-radius: 10px;'>";
echo "<h1 style='color: #667eea;'>📊 Database Subject Audit</h1>";
echo "<hr>";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM subjects";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total = $count_row['total'];

echo "<h2>Total Subjects in Database: <strong style='color: #667eea;'>$total</strong></h2>";
echo "<hr>";

// Get all subjects with their class_id
$all_query = "SELECT s.id, s.subject_name, s.class_id, s.stream, c.class_name 
              FROM subjects s
              LEFT JOIN classes c ON s.class_id = c.id
              ORDER BY s.subject_name, s.class_id, s.stream";

$all_result = mysqli_query($conn, $all_query);

if(!$all_result) {
    echo "<p style='color: red;'><strong>Error: " . mysqli_error($conn) . "</strong></p>";
} else {
    echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 14px;'>";
    echo "<tr style='background: #667eea; color: white; font-weight: bold;'>";
    echo "<th>ID</th><th>Subject Name</th><th>Class ID</th><th>Class Name</th><th>Stream</th>";
    echo "</tr>";
    
    $prev_subject = '';
    while($row = mysqli_fetch_assoc($all_result)) {
        $bg_color = ($row['subject_name'] !== $prev_subject) ? 'background: #f0f0f0;' : '';
        $prev_subject = $row['subject_name'];
        
        echo "<tr style='$bg_color'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td><strong>" . $row['subject_name'] . "</strong></td>";
        echo "<td style='text-align: center;'>" . $row['class_id'] . "</td>";
        echo "<td>" . ($row['class_name'] ? $row['class_name'] : '<span style="color: red;">NULL</span>') . "</td>";
        echo "<td>" . ($row['stream'] ? $row['stream'] : '<span style="color: red;">NULL</span>') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<h3>Subject Count by Class:</h3>";

$by_class = "SELECT c.id, c.class_name, COUNT(s.id) as subject_count
             FROM classes c
             LEFT JOIN subjects s ON c.id = s.class_id
             GROUP BY c.id, c.class_name
             ORDER BY c.id";

$by_class_result = mysqli_query($conn, $by_class);

echo "<ul>";
while($row = mysqli_fetch_assoc($by_class_result)) {
    echo "<li><strong>" . $row['class_name'] . ":</strong> " . $row['subject_count'] . " subjects</li>";
}
echo "</ul>";

echo "<h3>Subject Count by Stream:</h3>";

$by_stream = "SELECT stream, COUNT(DISTINCT CONCAT(subject_name, '_', class_id)) as unique_combinations
              FROM subjects
              WHERE stream IS NOT NULL
              GROUP BY stream
              ORDER BY stream";

$by_stream_result = mysqli_query($conn, $by_stream);

echo "<ul>";
while($row = mysqli_fetch_assoc($by_stream_result)) {
    echo "<li><strong>" . $row['stream'] . ":</strong> " . $row['unique_combinations'] . " combinations</li>";
}
echo "</ul>";

echo "</div>";
?>
