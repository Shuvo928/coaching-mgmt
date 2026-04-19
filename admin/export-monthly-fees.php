<?php
session_start();
require_once '../includes/db.php';

// Check authentication
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if classes table has section column
$sectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
$sectionSelect = ($sectionColumn && mysqli_num_rows($sectionColumn) > 0) ? 'c.section' : "'' AS section";

// Get month filter
$month_filter = isset($_GET['month']) ? mysqli_real_escape_string($conn, $_GET['month']) : '';

// Build query
$query = "SELECT fc.*, s.first_name, s.last_name, s.student_id, c.class_name, $sectionSelect, fh.fee_name
          FROM fee_collections fc
          LEFT JOIN students s ON fc.student_id = s.id
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN fees_head fh ON fc.fee_head_id = fh.id
          WHERE fh.fee_name != 'Admission Fee'
          AND fc.student_id IS NOT NULL";

if($month_filter) {
    $query .= " AND MONTH(fc.payment_date) = '$month_filter'";
}

$query .= " ORDER BY fc.payment_date DESC";

$result = mysqli_query($conn, $query);

// Create CSV
$filename = "Monthly_Fees_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Write header
fputcsv($output, array('Student ID', 'Student Name', 'Class', 'Fee Type', 'Payment Date', 'Amount', 'Paid', 'Due', 'Status', 'Payment Method'));

// Write data
while($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, array(
        $row['student_id'] ?? '-',
        ($row['first_name'] && $row['last_name']) ? $row['first_name'] . ' ' . $row['last_name'] : '-',
        isset($row['class_name']) ? $row['class_name'] . ' - ' . $row['section'] : '-',
        $row['fee_name'] ?? '-',
        $row['payment_date'] ? date('d-m-Y', strtotime($row['payment_date'])) : 'N/A',
        $row['amount'],
        $row['paid_amount'],
        $row['due_amount'],
        $row['status'],
        $row['payment_method'] ?? '-'
    ));
}

fclose($output);
exit();
?>
