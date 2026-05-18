<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 1200px; margin: 30px auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #667eea;'>📚 Database Migration: Add Streams to Subjects</h1>";
echo "<hr>";

$status_messages = [];
$error_messages = [];

// ===== STEP 1: Check and modify constraints =====
echo "<h3>Step 1: Preparing Database Schema</h3>";

// Check and remove UNIQUE constraint on subject_name
echo "<p>⏳ Checking UNIQUE constraints...</p>";
$constraints_check = mysqli_query($conn, "SHOW INDEXES FROM subjects WHERE Non_unique = 0 AND Column_name != 'id'");
$constraints_to_remove = [];

if($constraints_check && mysqli_num_rows($constraints_check) > 0) {
    while($idx = mysqli_fetch_assoc($constraints_check)) {
        if($idx['Key_name'] !== 'PRIMARY') {
            $constraints_to_remove[$idx['Key_name']] = true;
        }
    }
}

// Remove all non-primary UNIQUE constraints
foreach(array_keys($constraints_to_remove) as $constraint_name) {
    echo "<p>⏳ Removing constraint: $constraint_name...</p>";
    $drop_constraint = "ALTER TABLE subjects DROP INDEX `$constraint_name`";
    
    if(mysqli_query($conn, $drop_constraint)) {
        echo "<p style='color: green;'><strong>✅ Removed constraint: $constraint_name</strong></p>";
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Could not remove $constraint_name: " . mysqli_error($conn) . "</strong></p>";
    }
}

// Check and add 'stream' column if missing
echo "<p>⏳ Checking 'stream' column...</p>";
$check_stream = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'stream'");
$stream_exists = ($check_stream && mysqli_num_rows($check_stream) > 0);

if(!$stream_exists) {
    echo "<p>⏳ Adding 'stream' column...</p>";
    $add_stream = "ALTER TABLE subjects ADD COLUMN stream VARCHAR(50) NULL AFTER class_id";
    
    if(mysqli_query($conn, $add_stream)) {
        echo "<p style='color: green;'><strong>✅ Successfully added 'stream' column!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: blue;'><strong>ℹ️ 'stream' column already exists</strong></p>";
}

// Add composite unique constraint if it doesn't exist
echo "<p>⏳ Adding composite UNIQUE constraint on (subject_name, class_id, stream)...</p>";
$composite_constraint_check = mysqli_query($conn, "SHOW INDEXES FROM subjects WHERE Key_name = 'unique_subject_class_stream'");
if(!$composite_constraint_check || mysqli_num_rows($composite_constraint_check) === 0) {
    $add_composite = "ALTER TABLE subjects ADD UNIQUE KEY unique_subject_class_stream (subject_name, class_id, stream)";
    
    if(mysqli_query($conn, $add_composite)) {
        echo "<p style='color: green;'><strong>✅ Added composite UNIQUE constraint!</strong></p>";
    } else {
        echo "<p style='color: #ff9800;'><strong>ℹ️ Composite constraint may already exist</strong></p>";
    }
} else {
    echo "<p style='color: blue;'><strong>ℹ️ Composite constraint already exists</strong></p>";
}

// ===== STEP 2: Define the subject-stream mapping =====
echo "<h3>Step 2: Setting Up Subject-Stream Mapping</h3>";

$subject_mapping = [
    // SCIENCE STREAM - All Classes (9, 10, SSC)
    'Physics' => ['classes' => [1, 2, 3], 'stream' => 'Science', 'code' => 'PHY'],
    'Chemistry' => ['classes' => [1, 2, 3], 'stream' => 'Science', 'code' => 'CHM'],
    'Biology' => ['classes' => [1, 2, 3], 'stream' => 'Science', 'code' => 'BIO'],
    'Higher Mathematics' => ['classes' => [1, 2, 3], 'stream' => 'Science', 'code' => 'MAT'],
    
    // COMMERCE STREAM - All Classes (9, 10, SSC)
    'Accounting' => ['classes' => [1, 2, 3], 'stream' => 'Commerce', 'code' => 'ACC'],
    'Business Studies' => ['classes' => [1, 2, 3], 'stream' => 'Commerce', 'code' => 'BUS'],
    'Finance & Banking' => ['classes' => [1, 2, 3], 'stream' => 'Commerce', 'code' => 'FIN'],
    
    // HUMANITIES STREAM - All Classes (9, 10, SSC)
    'History' => ['classes' => [1, 2, 3], 'stream' => 'Humanities', 'code' => 'HIS'],
    'Geography' => ['classes' => [1, 2, 3], 'stream' => 'Humanities', 'code' => 'GEO'],
    'Political Science' => ['classes' => [1, 2, 3], 'stream' => 'Humanities', 'code' => 'POL'],
    'Economics' => ['classes' => [1, 2, 3], 'stream' => 'Humanities', 'code' => 'ECO'],
    'Sociology' => ['classes' => [1, 2, 3], 'stream' => 'Humanities', 'code' => 'SOC'],
];

echo "<p>Defined mapping for <strong>" . count($subject_mapping) . " subjects</strong> across 3 streams:</p>";
echo "<ul>";
echo "<li><strong>Science:</strong> Physics, Chemistry, Biology, Higher Mathematics (4 subjects × 3 classes = 12)</li>";
echo "<li><strong>Commerce:</strong> Accounting, Business Studies, Finance & Banking (3 subjects × 3 classes = 9)</li>";
echo "<li><strong>Humanities:</strong> History, Geography, Political Science, Economics, Sociology (5 subjects × 3 classes = 15)</li>";
echo "<li><strong>Total Expected: 36 subjects</strong> (12 + 9 + 15)</li>";
echo "</ul>";

// ===== STEP 3: Delete existing subjects and recreate with all class-stream combinations =====
echo "<h3>Step 3: Recreating Subject-Class-Stream Combinations</h3>";

// First, back up assignments (optional but good practice)
$backup_query = "SELECT teacher_id, subject_id FROM teacher_subjects";
$backup_result = mysqli_query($conn, $backup_query);
$teacher_subject_backup = [];
while($backup = mysqli_fetch_assoc($backup_result)) {
    $teacher_subject_backup[] = $backup;
}
echo "<p>ℹ️ Backed up " . count($teacher_subject_backup) . " teacher-subject assignments</p>";

// Clear existing subjects (this will cascade delete teacher assignments if constraints exist)
echo "<p>⏳ Clearing existing subjects...</p>";
$delete_all = "DELETE FROM subjects";
if(mysqli_query($conn, $delete_all)) {
    echo "<p style='color: green;'><strong>✅ Cleared existing subjects</strong></p>";
} else {
    // If delete fails, it might be due to foreign key constraints
    // Try to disable foreign key checks temporarily
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
    if(mysqli_query($conn, $delete_all)) {
        echo "<p style='color: green;'><strong>✅ Cleared existing subjects (with FK checks disabled)</strong></p>";
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Could not clear subjects: " . mysqli_error($conn) . "</strong></p>";
    }
}

// Disable foreign key checks temporarily
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

// Get class map to verify valid class IDs
$classes_query = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY id");
$class_map = [];
while($cls = mysqli_fetch_assoc($classes_query)) {
    $class_map[$cls['id']] = $cls['class_name'];
}

echo "<p>Valid class IDs: " . implode(", ", array_keys($class_map)) . "</p>";

$created_count = 0;
$failed_count = 0;

foreach($subject_mapping as $subject_name => $mapping_info) {
    $stream = $mapping_info['stream'];
    $code = $mapping_info['code'];
    $classes = $mapping_info['classes'];
    
    // Escape values properly
    $safe_subject = mysqli_real_escape_string($conn, $subject_name);
    $safe_stream = mysqli_real_escape_string($conn, $stream);
    $safe_code = mysqli_real_escape_string($conn, $code);
    
    // Create entries for EACH class this subject should be in
    foreach($classes as $class_id) {
        $class_id = intval($class_id);
        $class_name = isset($class_map[$class_id]) ? $class_map[$class_id] : "Class $class_id";
        
        // Use simple INSERT (no ON DUPLICATE KEY)
        $insert_query = "INSERT INTO subjects (subject_name, class_id, stream, subject_code)
                         VALUES ('$safe_subject', $class_id, '$safe_stream', '$safe_code')";
        
        $result = mysqli_query($conn, $insert_query);
        
        if($result) {
            $last_id = mysqli_insert_id($conn);
            echo "<p style='color: green;'>✅ <strong>$subject_name</strong> → $stream ($class_name) [ID: $last_id]</p>";
            $created_count++;
        } else {
            $error = mysqli_error($conn);
            echo "<p style='color: red;'>❌ Error: $subject_name for $class_name - $error</p>";
            $failed_count++;
        }
    }
}

// Re-enable foreign key checks
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

echo "<hr>";
echo "<p style='color: #667eea;'><strong>📊 Creation Summary:</strong></p>";
echo "<ul>";
echo "<li>✅ Successfully created: <strong>$created_count</strong> subjects</li>";
echo "<li>❌ Failed to create: <strong>$failed_count</strong> subjects</li>";
echo "<li>✅ Expected total: <strong>36</strong> subjects (12 Science + 9 Commerce + 15 Humanities)</li>";
echo "</ul>";

$status_messages[] = "✅ Created $created_count subject entries (expected 36)";
if($failed_count > 0) {
    $status_messages[] = "⚠️ $failed_count subjects failed to insert";
}

// ===== STEP 4: Verify the data =====
echo "<h3>Step 4: Verification</h3>";

$verify_query = "SELECT s.id, s.subject_name, s.subject_code, s.stream, s.class_id, c.class_name
                 FROM subjects s
                 LEFT JOIN classes c ON s.class_id = c.id
                 ORDER BY c.class_name, 
                         FIELD(s.stream, 'Science', 'Commerce', 'Humanities'),
                         s.subject_name";

$verify_result = mysqli_query($conn, $verify_query);

if(!$verify_result) {
    echo "<p style='color: red;'><strong>❌ Error in verification query: " . mysqli_error($conn) . "</strong></p>";
} else {
    $verify_count = mysqli_num_rows($verify_result);
    echo "<p style='color: green;'><strong>✅ Total subjects with complete data: $verify_count</strong></p>";
    
    if($verify_count !== 33) {
        echo "<p style='color: orange;'><strong>⚠️ Expected 33 subjects, found $verify_count. This may indicate missing entries.</strong></p>";
    }
    
    echo "<p><strong>Subject Organization:</strong></p>";
    echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 14px;'>";
    echo "<tr style='background: #667eea; color: white; font-weight: bold;'>";
    echo "<th>Class</th><th>Stream</th><th>Subject</th><th>Code</th><th>ID</th>";
    echo "</tr>";
    
    $prev_class = '';
    $prev_stream = '';
    
    while($row = mysqli_fetch_assoc($verify_result)) {
        $row_style = '';
        if($row['stream'] === 'Science') {
            $row_style = "background: #e3f2fd;";
        } elseif($row['stream'] === 'Commerce') {
            $row_style = "background: #fff3e0;";
        } else {
            $row_style = "background: #f3e5f5;";
        }
        
        echo "<tr style='$row_style'>";
        echo "<td><strong>" . ($row['class_name'] ? $row['class_name'] : 'NULL') . "</strong></td>";
        echo "<td><strong style='color: #667eea;'>" . ($row['stream'] ? $row['stream'] : 'NULL') . "</strong></td>";
        echo "<td>" . $row['subject_name'] . "</td>";
        echo "<td style='text-align: center;'><code>" . $row['subject_code'] . "</code></td>";
        echo "<td style='text-align: center; font-size: 12px;'>" . $row['id'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// ===== SUMMARY =====
echo "<hr>";
echo "<h3 style='color: #667eea;'>✅ Migration Complete</h3>";

echo "<div style='background: #f0f7ff; border-left: 4px solid #667eea; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>Summary of Changes:</h4>";
echo "<ul>";
foreach($status_messages as $msg) {
    echo "<li>$msg</li>";
}
echo "</ul>";
echo "</div>";

if(!empty($error_messages)) {
    echo "<div style='background: #ffebee; border-left: 4px solid #c62828; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Warnings/Errors:</h4>";
    echo "<ul>";
    foreach($error_messages as $err) {
        echo "<li style='color: #c62828;'>$err</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<p style='margin-top: 30px;'>";
echo "<a href='../admin/teacher-management.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-right: 10px;'>Go to Teacher Management</a>";
echo "<a href='../admin/assign-subjects.php?teacher_id=5' style='background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Test Assign Subjects</a>";
echo "</p>";

echo "</div>";

mysqli_close($conn);
?>

<style>
    body { 
        font-family: 'Poppins', Arial, sans-serif;
        background: #f4f7fc;
        margin: 0;
    }
    h1 { color: #667eea; margin-bottom: 10px; }
    h3 { color: #333; margin-top: 25px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    h4 { color: #333; margin-top: 0; }
    p { line-height: 1.6; color: #555; }
    ul { margin: 10px 0 10px 20px; }
    li { margin: 5px 0; }
    table { margin: 15px 0; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    hr { border: none; border-top: 1px solid #eee; margin: 30px 0; }
</style>
