<?php
require_once '../includes/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="due_list_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, ['Student ID', 'Student Name', 'Class', 'Fee Type', 'Total Amount', 'Paid', 'Due', 'Due Date', 'Status']);

// Check if classes table has section column
$sectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
$sectionSelect = ($sectionColumn && mysqli_num_rows($sectionColumn) > 0) ? 'c.section' : "'' AS section";

// Get due list
$due_list = mysqli_query($conn, "SELECT fc.*, s.first_name, s.last_name, s.student_id, 
                                   c.class_name, $sectionSelect, fh.fee_name
                                  FROM fee_collections fc
                                  JOIN students s ON fc.student_id = s.id
                                  JOIN classes c ON s.class_id = c.id
                                  JOIN fees_head fh ON fc.fee_head_id = fh.id
                                  WHERE fc.status != 'Paid'
                                  ORDER BY fc.due_date ASC");

while($row = mysqli_fetch_assoc($due_list)) {
    $class_display = $row['class_name'];
    if(!empty($row['section'])) {
        $class_display .= ' - ' . $row['section'];
    }
    fputcsv($output, [
        $row['student_id'],
        $row['first_name'] . ' ' . $row['last_name'],
        $class_display,
        $row['fee_name'],
        $row['amount'],
        $row['paid_amount'],
        $row['due_amount'],
        $row['due_date'] ? date('d-m-Y', strtotime($row['due_date'])) : 'N/A',
        $row['status']
    ]);
}

fclose($output);
?>