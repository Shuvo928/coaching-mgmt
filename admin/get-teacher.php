<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT t.*, u.username 
              FROM teachers t 
              LEFT JOIN users u ON t.user_id = u.id 
              WHERE t.id = $id";
    
    $result = mysqli_query($conn, $query);
    $teacher = mysqli_fetch_assoc($result);

    $class_ids = [];
    if (!empty($teacher['class_name'])) {
        $names = array_filter(array_map('trim', explode(',', $teacher['class_name'])));
        if (!empty($names)) {
            $escapedNames = array_map(function($name) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $name) . "'";
            }, $names);
            $nameList = implode(',', $escapedNames);
            $ids_result = mysqli_query($conn, "SELECT id FROM classes WHERE class_name IN ($nameList)");
            while ($row = mysqli_fetch_assoc($ids_result)) {
                $class_ids[] = intval($row['id']);
            }
        }
    }

    $teacher['class_ids'] = $class_ids;

    // Preserve the teacher's raw assigned_subjects text if available.
    $teacher['assigned_subjects'] = trim($teacher['assigned_subjects'] ?? '');

    // If the teacher record does not have a raw assigned_subjects value,
    // fall back to subject names from the teacher_subjects mapping.
    if ($teacher['assigned_subjects'] === '') {
        $assigned_subjects = [];
        $subject_query = "SELECT DISTINCT s.subject_name
                          FROM teacher_subjects ts
                          JOIN subjects s ON ts.subject_id = s.id
                          WHERE ts.teacher_id = $id
                          ORDER BY s.subject_name";
        $subject_result = mysqli_query($conn, $subject_query);
        if ($subject_result) {
            while ($subject_row = mysqli_fetch_assoc($subject_result)) {
                $assigned_subjects[] = $subject_row['subject_name'];
            }
        }
        $teacher['assigned_subjects'] = implode(', ', $assigned_subjects);
    }

    echo json_encode($teacher);
}
?>