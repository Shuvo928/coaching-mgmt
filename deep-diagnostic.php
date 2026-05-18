<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 1400px; margin: 30px auto; background: white; border-radius: 10px;'>";
echo "<h1 style='color: #667eea;'>🔍 Deep Database Diagnostic</h1>";
echo "<hr>";

// ===== CHECK TABLE SCHEMA =====
echo "<h2>1. SUBJECTS TABLE SCHEMA</h2>";
$schema_query = "DESCRIBE subjects";
$schema_result = mysqli_query($conn, $schema_query);
echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 13px; margin-bottom: 20px;'>";
echo "<tr style='background: #667eea; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($col = mysqli_fetch_assoc($schema_result)) {
    echo "<tr>";
    echo "<td><strong>" . $col['Field'] . "</strong></td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . $col['Default'] . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// ===== CHECK CONSTRAINTS =====
echo "<h2>2. EXISTING INDEXES/CONSTRAINTS</h2>";
$indexes_query = "SHOW INDEXES FROM subjects";
$indexes_result = mysqli_query($conn, $indexes_query);
echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 13px; margin-bottom: 20px;'>";
echo "<tr style='background: #667eea; color: white;'><th>Key Name</th><th>Column</th><th>Non-Unique</th><th>Seq in Index</th></tr>";
while($idx = mysqli_fetch_assoc($indexes_result)) {
    $unique = $idx['Non_unique'] ? 'NO (UNIQUE)' : 'YES (allows dupes)';
    echo "<tr>";
    echo "<td><strong>" . $idx['Key_name'] . "</strong></td>";
    echo "<td>" . $idx['Column_name'] . "</td>";
    echo "<td>" . $unique . "</td>";
    echo "<td>" . $idx['Seq_in_index'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// ===== COUNT SUBJECTS =====
echo "<h2>3. SUBJECT COUNT ANALYSIS</h2>";
$count_query = "SELECT COUNT(*) as total FROM subjects";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
echo "<p><strong>Total subjects in database: " . $count_row['total'] . "</strong> (should be 36)</p>";

// Count by class_id
echo "<h3>Subjects by class_id:</h3>";
$by_class = "SELECT class_id, COUNT(*) as cnt FROM subjects GROUP BY class_id ORDER BY class_id";
$by_class_result = mysqli_query($conn, $by_class);
echo "<ul>";
while($row = mysqli_fetch_assoc($by_class_result)) {
    echo "<li>class_id = " . $row['class_id'] . ": <strong>" . $row['cnt'] . " subjects</strong></li>";
}
echo "</ul>";

// Count distinct subject names
echo "<h3>Distinct subject names:</h3>";
$distinct_query = "SELECT COUNT(DISTINCT subject_name) as cnt FROM subjects";
$distinct_result = mysqli_query($conn, $distinct_query);
$distinct_row = mysqli_fetch_assoc($distinct_result);
echo "<p>Total distinct names: <strong>" . $distinct_row['cnt'] . "</strong> (should be 12)</p>";

// ===== SHOW ALL DATA =====
echo "<h2>4. COMPLETE SUBJECT LIST</h2>";
$all_query = "SELECT id, subject_name, class_id, stream, subject_code FROM subjects ORDER BY subject_name, class_id";
$all_result = mysqli_query($conn, $all_query);
$all_count = mysqli_num_rows($all_result);

echo "<p><strong>Fetched: $all_count subjects</strong></p>";

if($all_count > 0) {
    echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr style='background: #667eea; color: white;'><th>ID</th><th>Subject Name</th><th>Class ID</th><th>Stream</th><th>Code</th></tr>";
    
    $subject_groups = [];
    while($row = mysqli_fetch_assoc($all_result)) {
        $key = $row['subject_name'];
        if(!isset($subject_groups[$key])) {
            $subject_groups[$key] = [];
        }
        $subject_groups[$key][] = $row;
    }
    
    foreach($subject_groups as $subject_name => $entries) {
        $row_count = count($entries);
        $expected = ($subject_name === 'Accounting' || $subject_name === 'Business Studies' || $subject_name === 'Finance & Banking') ? 2 : 3;
        $status = ($row_count === $expected) ? '✅' : '❌';
        
        echo "<tr style='background: #f9f9f9;'>";
        echo "<td colspan='5'><strong>$status $subject_name ($row_count entries, expected $expected)</strong></td>";
        echo "</tr>";
        
        foreach($entries as $entry) {
            $stream_color = '';
            $expected_per_stream = 1; // Default
            
            // Calculate expected entries per subject per stream
            if($entry['stream'] === 'Science') {
                $expected_per_stream = 3; // Science in all 3 classes
            } elseif($entry['stream'] === 'Commerce') {
                $expected_per_stream = 3; // Commerce in all 3 classes
            } else { // Humanities
                $expected_per_stream = 3; // Humanities in all 3 classes
            }
            
            if($entry['stream'] === 'Science') {
                $stream_color = 'style="background: #e3f2fd;"';
            } elseif($entry['stream'] === 'Commerce') {
                $stream_color = 'style="background: #fff3e0;"';
            } else {
                $stream_color = 'style="background: #f3e5f5;"';
            }
            
            echo "<tr $stream_color>";
            echo "<td>" . $entry['id'] . "</td>";
            echo "<td>" . $entry['subject_name'] . "</td>";
            echo "<td style='text-align: center;'>" . $entry['class_id'] . "</td>";
            echo "<td>" . $entry['stream'] . "</td>";
            echo "<td>" . $entry['subject_code'] . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>⚠️ No subjects found in database!</strong></p>";
}

// ===== CHECK CLASSES TABLE =====
echo "<h2>5. CLASSES TABLE</h2>";
$classes_query = "SELECT id, class_name FROM classes ORDER BY id";
$classes_result = mysqli_query($conn, $classes_query);
echo "<ul>";
while($cls = mysqli_fetch_assoc($classes_result)) {
    echo "<li>ID " . $cls['id'] . ": " . $cls['class_name'] . "</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='setup-stream-subjects.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-right: 10px;'>Re-run Setup Script</a>";
echo "<a href='admin/assign-subjects.php?teacher_id=6' style='background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Test Assign Subjects</a></p>";

echo "</div>";
?>
