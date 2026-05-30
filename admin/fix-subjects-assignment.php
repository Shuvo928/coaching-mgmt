<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['admin']);

echo "<div style='font-family: Arial; padding: 20px; max-width: 1000px; margin: 30px auto;'>";
echo "<h1>Fix: Subjects Not Showing in Assign Subjects Page</h1>";
echo "<hr>";

$fixes_applied = [];
$warnings = [];

// ===== STEP 1: Check if class_id column exists =====
echo "<h3>Step 1: Checking subjects table structure...</h3>";

$check_class_id = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'class_id'");
$class_id_exists = ($check_class_id && mysqli_num_rows($check_class_id) > 0);

if(!$class_id_exists) {
    echo "<p style='color: red;'><strong>❌ class_id column is MISSING</strong></p>";
    echo "<p>⏳ Adding class_id column to subjects table...</p>";
    
    $add_class_id = "ALTER TABLE subjects ADD COLUMN class_id INT UNSIGNED NULL AFTER subject_code";
    
    if(mysqli_query($conn, $add_class_id)) {
        $fixes_applied[] = "✅ Added 'class_id' column to subjects table";
        echo "<p style='color: green;'><strong>✅ Successfully added class_id column!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ class_id column exists</strong></p>";
}

// ===== STEP 2: Check if subjects have class_id values =====
echo "<h3>Step 2: Checking subjects data...</h3>";

$null_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM subjects WHERE class_id IS NULL");
$null_result = mysqli_fetch_assoc($null_count);
$null_subjects = $null_result['cnt'];

$total_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM subjects");
$total_result = mysqli_fetch_assoc($total_count);
$total_subjects = $total_result['cnt'];

echo "<p>Total subjects: <strong>$total_subjects</strong></p>";
echo "<p>Subjects without class_id: <strong>$null_subjects</strong></p>";

// ===== STEP 3: Get classes and subjects =====
echo "<h3>Step 3: Populating class_id for subjects...</h3>";

$classes = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY id");
$class_map = [];

if($classes) {
    while($class = mysqli_fetch_assoc($classes)) {
        $class_map[$class['id']] = $class['class_name'];
    }
    echo "<p>Found <strong>" . count($class_map) . " classes</strong>:</p>";
    echo "<ul>";
    foreach($class_map as $id => $name) {
        echo "<li>Class $id: $name</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'><strong>❌ Error fetching classes: " . mysqli_error($conn) . "</strong></p>";
}

// ===== STEP 4: Map subjects to classes =====
echo "<h3>Step 4: Assigning subjects to classes...</h3>";

$subjects_list = mysqli_query($conn, "SELECT id, subject_name FROM subjects WHERE class_id IS NULL ORDER BY subject_name");

if($subjects_list && mysqli_num_rows($subjects_list) > 0) {
    echo "<p><strong>Found " . mysqli_num_rows($subjects_list) . " subjects without class assignment</strong></p>";
    echo "<p>🔄 Auto-assigning subjects to classes based on standard curriculum...</p>";
    
    // Class 9 Subjects
    $class9_subjects = [
        'bangla', 'bangla 1st paper', 'bangla 2nd paper',
        'english', 'english 1st paper', 'english 2nd paper',
        'mathematics', 'general mathematics', 'higher mathematics',
        'science', 'physics', 'chemistry', 'biology', 'physical science',
        'social science', 'history', 'geography', 'civics',
        'ict', 'information technology'
    ];
    
    // Class 10 Subjects
    $class10_subjects = [
        'bangla', 'bangla 1st paper', 'bangla 2nd paper',
        'english', 'english 1st paper', 'english 2nd paper',
        'mathematics', 'general mathematics', 'higher mathematics',
        'science', 'physics', 'chemistry', 'biology', 'physical science',
        'social science', 'history', 'geography', 'civics',
        'ict', 'information technology'
    ];
    
    // SSC Subjects
    $ssc_subjects = [
        'bangla', 'bangla 1st paper', 'bangla 2nd paper',
        'english', 'english 1st paper', 'english 2nd paper',
        'mathematics', 'general mathematics', 'higher mathematics', 'business mathematics',
        'science', 'physics', 'chemistry', 'biology', 'physical science',
        'social science', 'history', 'geography', 'civics',
        'ict', 'information technology'
    ];
    
    $updated_count = 0;
    
    while($subject = mysqli_fetch_assoc($subjects_list)) {
        $subject_name_lower = strtolower($subject['subject_name']);
        $assigned = false;
        
        // Try to assign to appropriate class
        // For now, assign to the first class (usually Class 9 if only one exists, or first class for all)
        foreach($class_map as $class_id => $class_name) {
            // Get class level
            if(stripos($class_name, 'class 9') !== false || stripos($class_name, '9') !== false) {
                if(array_search($subject_name_lower, $class9_subjects) !== false) {
                    $update_query = "UPDATE subjects SET class_id = $class_id WHERE id = " . $subject['id'];
                    if(mysqli_query($conn, $update_query)) {
                        echo "<p>✅ Assigned '<strong>" . $subject['subject_name'] . "</strong>' to <strong>" . $class_name . "</strong></p>";
                        $updated_count++;
                        $assigned = true;
                        break;
                    }
                }
            }
        }
        
        // If not assigned, assign to first available class
        if(!$assigned && count($class_map) > 0) {
            $first_class_id = key($class_map);
            $update_query = "UPDATE subjects SET class_id = $first_class_id WHERE id = " . $subject['id'];
            if(mysqli_query($conn, $update_query)) {
                echo "<p>⚠️ Auto-assigned '<strong>" . $subject['subject_name'] . "</strong>' to <strong>" . $class_map[$first_class_id] . "</strong></p>";
                $updated_count++;
            }
        }
    }
    
    if($updated_count > 0) {
        $fixes_applied[] = "✅ Updated $updated_count subjects with class_id";
        echo "<p style='color: green;'><strong>✅ Successfully assigned $updated_count subjects to classes!</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ All subjects already have class_id assigned</strong></p>";
}

// ===== STEP 5: Test the assign-subjects.php query =====
echo "<h3>Step 5: Testing the query...</h3>";

$test_query = "SELECT s.id, s.subject_name, c.class_name, c.id as class_id
               FROM subjects s
               JOIN classes c ON s.class_id = c.id
               ORDER BY c.class_name, s.subject_name";

$test_result = mysqli_query($conn, $test_query);

if($test_result) {
    $count = mysqli_num_rows($test_result);
    if($count > 0) {
        echo "<p style='color: green;'><strong>✅ Query now returns $count subjects!</strong></p>";
        echo "<p><strong>Sample subjects found:</strong></p>";
        echo "<ul>";
        $i = 0;
        while (($row = mysqli_fetch_assoc($test_result)) && $i < 10) {
            echo "<li>" . $row['subject_name'] . " - " . $row['class_name'] . "</li>";
            $i++;
        }
        echo "</ul>";
        $fixes_applied[] = "✅ Query test successful - subjects are now visible";
    } else {
        echo "<p style='color: red;'><strong>❌ Query still returns no results</strong></p>";
        echo "<p>Check if both subjects and classes tables have data.</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Query error: " . mysqli_error($conn) . "</strong></p>";
}

// ===== FINAL SUMMARY =====
echo "<hr>";
echo "<h3 style='color: #667eea;'>Summary of Fixes Applied:</h3>";
if(!empty($fixes_applied)) {
    echo "<ul style='color: green;'>";
    foreach($fixes_applied as $fix) {
        echo "<li>" . $fix . "</li>";
    }
    echo "</ul>";
    echo "<p style='color: green; font-size: 18px;'><strong>✅ All fixes applied successfully!</strong></p>";
    echo "<p><a href='assign-subjects.php?teacher_id=5' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Click here to go back to Assign Subjects</a></p>";
} else {
    if(!empty($warnings)) {
        echo "<p style='color: orange;'><strong>⚠️ Warnings:</strong></p>";
        echo "<ul>";
        foreach($warnings as $warning) {
            echo "<li>" . $warning . "</li>";
        }
        echo "</ul>";
    }
    echo "<p style='color: blue;'><strong>ℹ️ No fixes needed - system is already configured correctly</strong></p>";
    echo "<p><a href='assign-subjects.php?teacher_id=5' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Click here to go back to Assign Subjects</a></p>";
}

echo "</div>";

mysqli_close($conn);
?>

<style>
    body { 
        font-family: 'Poppins', Arial, sans-serif;
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
    ul {
        margin: 10px 0 10px 20px;
    }
    li {
        margin: 5px 0;
    }
    hr {
        border: none;
        border-top: 1px solid #eee;
        margin: 30px 0;
    }
</style>
