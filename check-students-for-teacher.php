<?php
session_start();
require_once 'includes/db.php';

// Check if logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: admin/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

echo "<h2>Teacher Diagnostic Report</h2>";
echo "<p>Teacher ID: $teacher_id</p>";

// Check 1: Teacher's assigned subjects
echo "<h3>Step 1: Teacher's Assigned Subjects</h3>";
$subjects_query = "SELECT ts.subject_id, s.subject_name, s.class_id, c.class_name
                    FROM teacher_subjects ts
                    JOIN subjects s ON ts.subject_id = s.id
                    JOIN classes c ON s.class_id = c.id
                    WHERE ts.teacher_id = $teacher_id";
$subjects_result = mysqli_query($conn, $subjects_query);
$class_ids = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    echo "✓ Class: " . htmlspecialchars($row['class_name']) . " | Subject: " . htmlspecialchars($row['subject_name']) . "<br>";
    $class_ids[] = $row['class_id'];
}

if (empty($class_ids)) {
    echo "<span style='color: red;'>❌ No subjects assigned to this teacher</span>";
    exit;
}

// Check 2: Students in those classes
echo "<h3>Step 2: Students in Classes " . implode(", ", $class_ids) . "</h3>";
$class_ids_str = implode(',', $class_ids);
$students_query = "SELECT s.id, s.first_name, s.last_name, s.class_id, s.group_id, 
                          c.class_name, g.group_name
                   FROM students s
                   JOIN classes c ON s.class_id = c.id
                   LEFT JOIN `groups` g ON s.group_id = g.id
                   WHERE s.class_id IN ($class_ids_str)
                   ORDER BY c.class_name, g.group_name, s.roll_number";
$students_result = mysqli_query($conn, $students_query);

if (mysqli_num_rows($students_result) == 0) {
    echo "<span style='color: red;'>❌ No students found in these classes!</span><br>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Class</th><th>Group ID</th><th>Group Name</th></tr>";
    while ($row = mysqli_fetch_assoc($students_result)) {
        $group_name = $row['group_name'] ?: '<span style="color: orange;">NULL (Unassigned)</span>';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['class_name']) . "</td>";
        echo "<td>" . ($row['group_id'] ?: 'NULL') . "</td>";
        echo "<td>" . $group_name . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check 3: Test the exact query that teacher-dashboard.php uses
echo "<h3>Step 3: Test Dashboard Query</h3>";
$test_query = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.roll_number, s.class_id, s.group_id,
                      c.class_name, COALESCE(g.group_name, 'Unassigned') AS group_name
               FROM students s
               JOIN classes c ON s.class_id = c.id
               LEFT JOIN `groups` g ON s.group_id = g.id
               WHERE s.class_id IN ($class_ids_str)
               ORDER BY c.class_name, COALESCE(g.group_name, 'Unassigned'), s.roll_number";
$test_result = mysqli_query($conn, $test_query);

if (!$test_result) {
    echo "<span style='color: red;'>❌ Query Error: " . mysqli_error($conn) . "</span>";
} else {
    $count = mysqli_num_rows($test_result);
    echo "✓ Query returned <strong>$count</strong> students<br>";
    if ($count > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Name</th><th>Roll</th><th>Class</th><th>Group</th></tr>";
        while ($row = mysqli_fetch_assoc($test_result)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . $row['roll_number'] . "</td>";
            echo "<td>" . htmlspecialchars($row['class_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['group_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<br><br>";
echo "<a href='admin/teacher-dashboard.php'>← Back to Teacher Dashboard</a>";
?>
