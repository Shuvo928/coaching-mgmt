<?php
require_once '../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 1000px; margin: 30px auto;'>";
echo "<h1>Coaching Management - Database Diagnostic</h1>";
echo "<hr>";

// ===== 1. Check subjects table structure =====
echo "<h3>1. Subjects Table Structure</h3>";

$check_class_id = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'class_id'");
$class_id_exists = ($check_class_id && mysqli_num_rows($check_class_id) > 0);

if($class_id_exists) {
    echo "<p style='color: green;'><strong>✅ class_id column EXISTS</strong></p>";
} else {
    echo "<p style='color: red;'><strong>❌ class_id column is MISSING - This is the problem!</strong></p>";
}

// Show all columns
$columns = mysqli_query($conn, "SHOW COLUMNS FROM subjects");
echo "<p><strong>All columns in subjects table:</strong></p>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #667eea; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while($col = mysqli_fetch_assoc($columns)) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// ===== 2. Check classes table =====
echo "<h3>2. Classes in Database</h3>";

$classes = mysqli_query($conn, "SELECT id, class_name FROM classes");
$class_count = mysqli_num_rows($classes);

if($class_count > 0) {
    echo "<p style='color: green;'><strong>✅ Found " . $class_count . " classes</strong></p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #667eea; color: white;'><th>ID</th><th>Class Name</th></tr>";
    while($class = mysqli_fetch_assoc($classes)) {
        echo "<tr><td>" . $class['id'] . "</td><td>" . $class['class_name'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>❌ No classes found in database</strong></p>";
}

// ===== 3. Check subjects data =====
echo "<h3>3. Subjects Data</h3>";

$subjects = mysqli_query($conn, "SELECT * FROM subjects");
$subject_count = mysqli_num_rows($subjects);

if($subject_count > 0) {
    echo "<p style='color: green;'><strong>✅ Found " . $subject_count . " subjects</strong></p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #667eea; color: white;'><th>ID</th><th>Subject Name</th><th>Subject Code</th>";
    if($class_id_exists) echo "<th>Class ID</th>";
    echo "</tr>";
    
    $null_class_id_count = 0;
    while($subject = mysqli_fetch_assoc($subjects)) {
        echo "<tr>";
        echo "<td>" . $subject['id'] . "</td>";
        echo "<td>" . $subject['subject_name'] . "</td>";
        echo "<td>" . ($subject['subject_code'] ?? 'N/A') . "</td>";
        if($class_id_exists) {
            $class_id_val = $subject['class_id'] ?? 'NULL';
            if($class_id_val === 'NULL' || $class_id_val === '') $null_class_id_count++;
            echo "<td style='background: " . (($class_id_val === 'NULL' || $class_id_val === '') ? '#ffcccc' : '#ccffcc') . ";'>" . $class_id_val . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    if($class_id_exists) {
        echo "<p><strong>Subjects with NULL class_id: " . $null_class_id_count . "</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ No subjects found in database</strong></p>";
}

// ===== 4. Test the query from assign-subjects.php =====
echo "<h3>4. Test Query from assign-subjects.php</h3>";

if($class_id_exists) {
    $test_query = "SELECT s.id, s.subject_name, c.class_name, c.id as class_id
                   FROM subjects s
                   JOIN classes c ON s.class_id = c.id
                   ORDER BY c.class_name, s.subject_name";
    
    $test_result = mysqli_query($conn, $test_query);
    
    if($test_result) {
        $count = mysqli_num_rows($test_result);
        if($count > 0) {
            echo "<p style='color: green;'><strong>✅ Query returns " . $count . " subjects</strong></p>";
            echo "<p><strong>Sample results:</strong></p>";
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background: #667eea; color: white;'><th>Subject ID</th><th>Subject Name</th><th>Class Name</th><th>Class ID</th></tr>";
            $i = 0;
            while($row = mysqli_fetch_assoc($test_result) && $i < 10) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['subject_name'] . "</td>";
                echo "<td>" . $row['class_name'] . "</td>";
                echo "<td>" . $row['class_id'] . "</td>";
                echo "</tr>";
                $i++;
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'><strong>❌ Query returns 0 subjects</strong></p>";
            echo "<p><strong>Reason:</strong> All subjects have NULL class_id values or no JOIN matches</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>❌ Query error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Cannot test query - class_id column doesn't exist</strong></p>";
}

echo "<hr>";
echo "<h3 style='color: #667eea;'>Action Required:</h3>";

if(!$class_id_exists) {
    echo "<p style='color: red;'><strong>Step 1: Add class_id column to subjects table</strong></p>";
    echo "<p>Run this SQL command:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>ALTER TABLE subjects ADD COLUMN class_id INT UNSIGNED NULL;</pre>";
    echo "<p>Or click the link to run the automated setup script:</p>";
    echo "<p><a href='../setup-subjects-class-id.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Run Migration Script</a></p>";
}

echo "<p><strong>Step 2: Populate class_id for all subjects</strong></p>";
echo "<p><a href='fix-subjects-assignment.php' style='background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Run Auto-Fix Script (requires authentication)</a></p>";

echo "<p style='margin-top: 20px;'><a href='assign-subjects.php?teacher_id=5' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Back to Assign Subjects</a></p>";

echo "</div>";

mysqli_close($conn);
?>

<style>
    body { 
        font-family: Arial, sans-serif;
        background: #f4f7fc;
        margin: 0;
    }
    div {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h1 { 
        color: #667eea;
        margin-bottom: 10px;
    }
    h3 { 
        color: #333;
        margin-top: 20px;
        border-bottom: 2px solid #667eea;
        padding-bottom: 10px;
    }
    p { 
        line-height: 1.6;
        color: #555;
    }
    table {
        margin: 10px 0;
        width: 100%;
    }
    td, th {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    tr:nth-child(even) {
        background: #f9f9f9;
    }
    hr {
        border: none;
        border-top: 1px solid #eee;
        margin: 30px 0;
    }
    pre {
        overflow-x: auto;
    }
</style>
