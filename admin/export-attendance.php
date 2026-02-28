<?php
require_once '../includes/db.php';

if(isset($_GET['from']) && isset($_GET['to'])) {
    $from_date = $_GET['from'];
    $to_date = $_GET['to'];
    $class_id = $_GET['class'] ?? '';
    
    $class_filter = $class_id ? "AND s.class_id = $class_id" : "";
    
    // Get attendance data
    $query = "SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                c.class_name,
                DATE(a.date) as date,
                a.status,
                TIME(a.created_at) as time
              FROM students s
              JOIN classes c ON s.class_id = c.id
              LEFT JOIN attendance a ON s.id = a.student_id 
                  AND a.date BETWEEN '$from_date' AND '$to_date'
              WHERE s.status = 1 $class_filter
              ORDER BY a.date DESC, s.roll_number";
    
    $result = mysqli_query($conn, $query);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $from_date . '_to_' . $to_date . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Student ID', 'Name', 'Class', 'Date', 'Status', 'Time']);
    
    // Add data rows
    while($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['student_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['class_name'],
            $row['date'] ? date('d-m-Y', strtotime($row['date'])) : 'N/A',
            $row['status'] ?? 'No Record',
            $row['time'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
}
?>