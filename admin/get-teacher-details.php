<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if(!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['error' => 'Invalid teacher ID']);
    exit();
}

$teacher_id = intval($_POST['id']);

// Get teacher details
$teacher_query = "SELECT * FROM teachers WHERE id = $teacher_id";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

if(!$teacher) {
    echo json_encode(['error' => 'Teacher not found']);
    exit();
}

// Get assigned subjects with class and stream info
$subjects_query = "SELECT s.id, s.subject_name, s.stream, c.class_name, c.id as class_id
                   FROM teacher_subjects ts
                   JOIN subjects s ON ts.subject_id = s.id
                   JOIN classes c ON s.class_id = c.id
                   WHERE ts.teacher_id = $teacher_id
                   ORDER BY c.id, 
                           FIELD(s.stream, 'Science', 'Commerce', 'Humanities'),
                           s.subject_name";

$subjects_result = mysqli_query($conn, $subjects_query);
$subjects = [];
$class_stream_map = [];

while($subject = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $subject;
    $key = $subject['class_id'] . '_' . $subject['stream'];
    if(!isset($class_stream_map[$key])) {
        $class_stream_map[$key] = [
            'class_name' => $subject['class_name'],
            'stream' => $subject['stream'],
            'subjects' => []
        ];
    }
    $class_stream_map[$key]['subjects'][] = $subject['subject_name'];
}

// Get teacher's class assignment
$class_name = $teacher['class_name'] ?? 'N/A';

// Format the response
$response = [
    'id' => $teacher['id'],
    'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
    'teacher_id' => $teacher['teacher_id'] ?? 'TCH' . $teacher['id'],
    'email' => $teacher['email'] ?? 'N/A',
    'phone' => $teacher['phone'] ?? 'N/A',
    'class' => $class_name,
    'qualification' => $teacher['qualification'] ?? 'N/A',
    'status' => $teacher['status'] ? 'Active' : 'Inactive',
    'joining_date' => $teacher['joining_date'] ?? 'N/A',
    'address' => $teacher['address'] ?? 'N/A',
    'photo' => $teacher['photo'] ?? null,
    'subjects_count' => count($subjects),
    'subjects' => $subjects,
    'class_stream_map' => $class_stream_map
];

echo json_encode($response);
mysqli_close($conn);
?>
