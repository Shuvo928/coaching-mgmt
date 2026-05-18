<?php
/** @var \mysqli|null $conn */
$conn = null;
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    header('Content-Type: application/json');
    
    $sectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
    $sectionSelect = ($sectionColumn && mysqli_num_rows($sectionColumn) > 0) ? 'c.section' : "''";
    $admissionPhoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
    $phoneCondition = ($admissionPhoneColumn && mysqli_num_rows($admissionPhoneColumn) > 0)
        ? "(phone = s.phone OR mobile = s.phone)"
        : "phone = s.phone";

    $query = "SELECT s.*, u.username, CONCAT_WS(' - ', c.class_name, $sectionSelect) AS class_label,
              (SELECT parent_name FROM admission_applications WHERE $phoneCondition ORDER BY id DESC LIMIT 1) AS parent_name,
              (SELECT `group` FROM admission_applications WHERE $phoneCondition ORDER BY id DESC LIMIT 1) AS admission_group
              FROM students s 
              LEFT JOIN users u ON s.user_id = u.id 
              LEFT JOIN classes c ON s.class_id = c.id 
              WHERE s.id = $id";
    
    $result = mysqli_query($conn, $query);
    $student = $result ? mysqli_fetch_assoc($result) : null;
    
    echo json_encode($student ?: []);
}
?>