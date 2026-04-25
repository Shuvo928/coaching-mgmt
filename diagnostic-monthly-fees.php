<?php
session_start();
require_once './includes/db.php';

echo "<h2>Diagnostic: Monthly Fees Issue</h2>";

// Check admission applications where fees were paid
echo "<h3>Admission Applications (Paid Fees)</h3>";
$adm_query = "SELECT id, first_name, last_name, class_id, application_fee, transaction_id, payment_method, application_date FROM admission_applications WHERE application_fee > 0 AND transaction_id <> '' ORDER BY application_date DESC";
$adm_result = mysqli_query($conn, $adm_query);
echo "<p>Total records: " . mysqli_num_rows($adm_result) . "</p>";
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Class</th><th>Fee</th><th>Transaction</th><th>Method</th><th>Date</th></tr>";
while($row = mysqli_fetch_assoc($adm_result)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['class_id']}</td><td>{$row['application_fee']}</td><td>{$row['transaction_id']}</td><td>{$row['payment_method']}</td><td>{$row['application_date']}</td></tr>";
}
echo "</table>";

// Check fee_collections for May 2026
echo "<h3>Fee Collections (Fee Month = 'May 2026')</h3>";
$fee_query = "SELECT fc.id, fc.student_id, s.first_name, s.last_name, fc.fee_month, fc.expected_amount, fc.paid_amount, fc.payment_status FROM fee_collections fc LEFT JOIN students s ON fc.student_id = s.id WHERE fc.fee_month LIKE '%May 2026%' ORDER BY fc.student_id";
$fee_result = mysqli_query($conn, $fee_query);
echo "<p>Total records: " . mysqli_num_rows($fee_result) . "</p>";
echo "<table border='1'><tr><th>ID</th><th>Student ID</th><th>Student Name</th><th>Fee Month</th><th>Expected</th><th>Paid</th><th>Status</th></tr>";
while($row = mysqli_fetch_assoc($fee_result)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['student_id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['fee_month']}</td><td>{$row['expected_amount']}</td><td>{$row['paid_amount']}</td><td>{$row['payment_status']}</td></tr>";
}
echo "</table>";

// Check all fee_collections to see fee_month formats
echo "<h3>All Fee Collections (Check fee_month formats)</h3>";
$all_fee_query = "SELECT DISTINCT fc.fee_month FROM fee_collections ORDER BY fc.fee_month DESC LIMIT 20";
$all_fee_result = mysqli_query($conn, $all_fee_query);
echo "<p>Unique fee_month formats:</p>";
while($row = mysqli_fetch_assoc($all_fee_result)) {
    echo "- " . htmlspecialchars($row['fee_month']) . "<br>";
}

// Check the current month logic
echo "<h3>Current System Date</h3>";
echo "Date: " . date('Y-m-d H:i:s') . "<br>";
echo "Current Month: " . date('m') . "<br>";
echo "Current Year: " . date('Y') . "<br>";
$next_month_label = date('M Y', strtotime('first day of next month'));
echo "Next Month Label: " . htmlspecialchars($next_month_label) . "<br>";

// Check students table
echo "<h3>Students Table</h3>";
$students_query = "SELECT id, first_name, last_name, admission_date FROM students ORDER BY id";
$students_result = mysqli_query($conn, $students_query);
echo "<p>Total students: " . mysqli_num_rows($students_result) . "</p>";
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Admission Date</th></tr>";
while($row = mysqli_fetch_assoc($students_result)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['admission_date']}</td></tr>";
}
echo "</table>";
?>
