<?php
require_once '../includes/db.php';

if(isset($_POST['class_id'])) {
    $class_id = $_POST['class_id'];
    
    $query = "SELECT * FROM subjects WHERE class_id = $class_id ORDER BY subject_name";
    $result = mysqli_query($conn, $query);
    
    $options = '<option value="">Select Subject</option>';
    while($row = mysqli_fetch_assoc($result)) {
        $options .= '<option value="' . $row['id'] . '">' . $row['subject_name'] . ' (' . $row['subject_code'] . ')</option>';
    }
    
    echo $options;
}
?>