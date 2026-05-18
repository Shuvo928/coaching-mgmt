<?php
/**
 * Debug: Save All Marks - 500 Error Diagnosis
 * Run this file to identify the exact issue causing the 500 error
 */

require_once '../includes/db.php';
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug: Save All Marks 500 Error</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .error { border-left-color: #dc3545; }
        .success { border-left-color: #28a745; }
        .warning { border-left-color: #ffc107; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .match { color: green; }
        .mismatch { color: red; font-weight: bold; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<h1>🔍 Debug: Save All Marks 500 Error</h1>";

// 1. Check Results Table Schema
echo "<div class='section success'>
<h2>1. Results Table Schema</h2>";

$columns_query = "DESCRIBE results";
$columns_result = mysqli_query($conn, $columns_query);

if($columns_result) {
    echo "<p><strong>✅ Results table exists</strong></p>";
    echo "<table>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columns = [];
    while($row = mysqli_fetch_assoc($columns_result)) {
        echo "<tr>";
        echo "<td><code>" . $row['Field'] . "</code></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
        $columns[] = $row['Field'];
    }
    echo "</table>";
    echo "<p><strong>Columns found:</strong> " . implode(", ", $columns) . "</p>";
} else {
    echo "<p class='error'><strong>❌ Error querying results table: " . mysqli_error($conn) . "</strong></p>";
}

// 2. Check what save-teacher-marks.php is trying to use
echo "</div><div class='section warning'>
<h2>2. Column Names Expected by save-teacher-marks.php</h2>";

$expected_columns = [
    'student_id' => 'Should be in INSERT/UPDATE',
    'subject_id' => 'Should be in INSERT/UPDATE',
    'exam_type' => 'Used as exam_type in INSERT/UPDATE',
    'marks' => '⚠️ ISSUE: Code uses \"marks\" but table may have \"marks_obtained\"',
    'created_at' => 'Used in INSERT statement',
    'updated_at' => 'Used in UPDATE statement'
];

echo "<table>";
echo "<tr><th>Column</th><th>Status</th></tr>";
foreach($expected_columns as $col => $status) {
    $exists = isset($columns) && in_array($col, $columns) ? '✅ EXISTS' : '❌ MISSING';
    $style = strpos($exists, '❌') !== false ? "class='mismatch'" : "class='match'";
    echo "<tr><td><code>" . $col . "</code></td><td $style>" . $exists . "</td></tr>";
}
echo "</table>";

// 3. Check for alternative column names
echo "</div><div class='section warning'>
<h2>3. Alternative Column Names (in other files)</h2>";

$alternatives = [
    'marks_obtained' => 'Found in generate-tabulation.php, parent/results.php',
    'total_marks' => 'Found in parent/results.php',
];

echo "<p><strong>These columns are used in OTHER files (may indicate schema mismatch):</strong></p>";
echo "<table>";
echo "<tr><th>Column Name</th><th>Found In</th><th>In results table?</th></tr>";
foreach($alternatives as $col => $files) {
    $exists = isset($columns) && in_array($col, $columns) ? '✅ YES' : '❌ NO';
    $style = strpos($exists, '❌') !== false ? "class='mismatch'" : "class='match'";
    echo "<tr><td><code>" . $col . "</code></td><td>" . $files . "</td><td $style>" . $exists . "</td></tr>";
}
echo "</table>";

// 4. Sample record from results table
echo "</div><div class='section'>
<h2>4. Sample Data from Results Table</h2>";

$sample_query = "SELECT * FROM results LIMIT 1";
$sample_result = mysqli_query($conn, $sample_query);

if($sample_result && mysqli_num_rows($sample_result) > 0) {
    echo "<p><strong>Sample record:</strong></p>";
    $sample = mysqli_fetch_assoc($sample_result);
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    print_r($sample);
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No sample records found in results table (table may be empty)</p>";
}

// 5. Test INSERT syntax
echo "</div><div class='section warning'>
<h2>5. SQL Syntax Verification</h2>";

echo "<p><strong>Current INSERT statement in save-teacher-marks.php:</strong></p>";
echo "<code style='display: block; background: #f4f4f4; padding: 10px; margin: 10px 0;'>
INSERT INTO results (student_id, subject_id, exam_type, marks, created_at, updated_at) 
VALUES (1, 2, 'exam', 85.5, NOW(), NOW())
</code>";

echo "<p><strong>Problem Identified:</strong></p>";
if(isset($columns) && !in_array('marks', $columns)) {
    echo "<p class='mismatch'>❌ The column <code>marks</code> does NOT exist in the results table!</p>";
    
    if(in_array('marks_obtained', $columns)) {
        echo "<p class='match'>✅ Found <code>marks_obtained</code> instead - This is likely the correct column!</p>";
        echo "<p><strong>SOLUTION:</strong> Replace <code>marks</code> with <code>marks_obtained</code> in save-teacher-marks.php</p>";
    }
}

if(isset($columns) && !in_array('updated_at', $columns)) {
    echo "<p class='mismatch'>❌ The column <code>updated_at</code> does NOT exist!</p>";
    echo "<p><strong>SOLUTION:</strong> Either remove <code>updated_at</code> from the query or add the column to the table</p>";
}

// 6. Test actual INSERT
echo "</div><div class='section'>
<h2>6. Live Test of INSERT Statement</h2>";

echo "<p><strong>Testing INSERT with corrected column names...</strong></p>";

// Build the correct INSERT based on what columns exist
if(isset($columns)) {
    $test_insert = "INSERT INTO results (student_id, subject_id, exam_type, ";
    
    $insert_cols = [];
    if(in_array('marks_obtained', $columns)) {
        $insert_cols[] = 'marks_obtained';
    } elseif(in_array('marks', $columns)) {
        $insert_cols[] = 'marks';
    }
    
    if(in_array('created_at', $columns)) {
        $insert_cols[] = 'created_at';
    }
    
    if(in_array('updated_at', $columns)) {
        $insert_cols[] = 'updated_at';
    }
    
    $test_insert .= implode(", ", $insert_cols);
    $test_insert .= ") VALUES (1, 1, 'exam', ";
    
    $value_cols = [];
    foreach($insert_cols as $col) {
        if($col === 'marks_obtained' || $col === 'marks') {
            $value_cols[] = "85.5";
        } else {
            $value_cols[] = "NOW()";
        }
    }
    
    $test_insert .= implode(", ", $value_cols) . ")";
    
    echo "<p><strong>Corrected SQL:</strong></p>";
    echo "<code style='display: block; background: #f4f4f4; padding: 10px; margin: 10px 0;'>" . htmlspecialchars($test_insert) . "</code>";
}

echo "</div>";

// 7. Summary
echo "<div class='section success'>
<h2>Summary & Next Steps</h2>";

if(isset($columns)) {
    $issues = [];
    
    if(!in_array('marks', $columns) && !in_array('marks_obtained', $columns)) {
        $issues[] = "❌ Neither 'marks' nor 'marks_obtained' column exists";
    } elseif(!in_array('marks', $columns) && in_array('marks_obtained', $columns)) {
        $issues[] = "❌ Column is 'marks_obtained' but code uses 'marks' → COLUMN NAME MISMATCH";
    }
    
    if(!in_array('updated_at', $columns)) {
        $issues[] = "❌ Column 'updated_at' doesn't exist but code tries to use it";
    }
    
    if(empty($issues)) {
        echo "<p style='color: green;'><strong>✅ No obvious column mismatches found</strong></p>";
        echo "<p>The 500 error may be caused by other issues. Check:</p>";
        echo "<ul>
            <li>PHP error logs: <code>C:\\xampp\\apache\\logs\\error.log</code></li>
            <li>Browser console for detailed error messages</li>
            <li>Database permissions for the user account</li>
        </ul>";
    } else {
        echo "<h3>⚠️ Issues Found:</h3>";
        echo "<ul>";
        foreach($issues as $issue) {
            echo "<li>" . $issue . "</li>";
        }
        echo "</ul>";
    }
}

echo "</div>";
echo "</body></html>";
?>
