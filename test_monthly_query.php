<?php
require_once 'includes/db.php';
$next_month_label = date('F Y', strtotime('first day of next month'));
echo 'Next month label: ' . $next_month_label . PHP_EOL;
$query = "SELECT fc.student_id as id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, c.class_name, fc.fee_month, fc.expected_amount, fc.paid_amount, fc.payment_status FROM fee_collections fc LEFT JOIN students s ON fc.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE fc.fee_month LIKE '%{$next_month_label}%' ORDER BY s.id";
$result = mysqli_query($conn, $query);
echo 'Number of rows: ' . mysqli_num_rows($result) . PHP_EOL;
while($row = mysqli_fetch_assoc($result)) {
    echo json_encode($row) . PHP_EOL;
}
?>