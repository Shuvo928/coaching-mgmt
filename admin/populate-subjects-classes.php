<?php
require_once '../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 1000px; margin: 30px auto;'>";
echo "<h1>Fix: Populate Subjects with Class IDs</h1>";
echo "<hr>";

// Get all subjects
$subjects = mysqli_query($conn, "SELECT id, subject_name FROM subjects WHERE class_id IS NULL");
$subject_count = mysqli_num_rows($subjects);

echo "<p>Found <strong>$subject_count subjects</strong> without class assignment.</p>";

// Define subject mappings for each class
$subject_mappings = [
    'Physics' => [1, 2, 3],           // Class 9, 10, SSC
    'Chemistry' => [1, 2, 3],         // Class 9, 10, SSC
    'Biology' => [1, 2, 3],           // Class 9, 10, SSC
    'Higher Mathematics' => [1, 2, 3], // Class 9, 10, SSC
    'History' => [1, 2, 3],           // Class 9, 10, SSC
    'Geography' => [1, 2, 3],         // Class 9, 10, SSC
    'Political Science' => [1, 2, 3], // Class 9, 10, SSC
    'Economics' => [2, 3],            // Class 10, SSC (usually Commerce stream)
    'Accounting' => [2, 3],           // Class 10, SSC (usually Commerce stream)
    'Finance & Banking' => [2, 3],    // Class 10, SSC (usually Commerce stream)
    'Business Studies' => [2, 3],     // Class 10, SSC (usually Commerce stream)
    'Sociology' => [1, 2, 3],         // Class 9, 10, SSC
];

echo "<h3>Assigning subjects to classes...</h3>";
echo "<ul>";

$updated = 0;

// For each subject without class_id, assign it to all relevant classes
$subjects_reset = mysqli_query($conn, "SELECT id, subject_name FROM subjects WHERE class_id IS NULL ORDER BY subject_name");

while($subject = mysqli_fetch_assoc($subjects_reset)) {
    $subject_name = $subject['subject_name'];
    $subject_id = $subject['id'];
    
    // Get classes from mapping or default to all classes
    $classes_to_assign = isset($subject_mappings[$subject_name]) 
        ? $subject_mappings[$subject_name] 
        : [1, 2, 3]; // Default to all classes if not in mapping
    
    // For simplicity, assign to the first class (Class 9) for single assignment
    // Or if you want multiple: create separate entries for each class
    $class_id = $classes_to_assign[0]; // Assign to first applicable class
    
    $update_query = "UPDATE subjects SET class_id = $class_id WHERE id = $subject_id";
    
    if(mysqli_query($conn, $update_query)) {
        $class_name = $class_id == 1 ? 'Class 9' : ($class_id == 2 ? 'Class 10' : 'SSC Batch');
        echo "<li style='color: green;'>✅ <strong>$subject_name</strong> assigned to <strong>$class_name</strong></li>";
        $updated++;
    } else {
        echo "<li style='color: red;'>❌ <strong>$subject_name</strong> - Error: " . mysqli_error($conn) . "</li>";
    }
}

echo "</ul>";

echo "<h3 style='color: green;'>✅ Updated <strong>$updated subjects</strong> successfully!</h3>";

// ===== Test the query again =====
echo "<h3>Test: Verify the fix</h3>";

$test_query = "SELECT s.id, s.subject_name, c.class_name, c.id as class_id
               FROM subjects s
               JOIN classes c ON s.class_id = c.id
               ORDER BY c.class_name, s.subject_name";

$test_result = mysqli_query($conn, $test_query);
$count = mysqli_num_rows($test_result);

if($count > 0) {
    echo "<p style='color: green;'><strong>✅ Query now returns " . $count . " subjects!</strong></p>";
    echo "<p><strong>All subjects found:</strong></p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #667eea; color: white;'><th>Subject</th><th>Class</th></tr>";
    while($row = mysqli_fetch_assoc($test_result)) {
        echo "<tr><td>" . $row['subject_name'] . "</td><td>" . $row['class_name'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>❌ Query still returns 0 subjects</strong></p>";
}

echo "<hr>";
echo "<p><a href='assign-subjects.php?teacher_id=5' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Go to Assign Subjects (Teacher ID 5)</a></p>";

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
    }
    h3 { 
        color: #333;
        margin-top: 20px;
        border-bottom: 2px solid #667eea;
        padding-bottom: 10px;
    }
    ul {
        margin: 10px 0 10px 20px;
    }
    li {
        margin: 8px 0;
        line-height: 1.6;
    }
    table {
        margin: 10px 0;
        width: 100%;
    }
    td, th {
        padding: 10px;
        text-align: left;
    }
    tr:nth-child(even) {
        background: #f9f9f9;
    }
</style>
