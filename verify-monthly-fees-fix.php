<?php
session_start();
require_once './includes/db.php';

echo "<h2 style='color: #2c3e50;'>Verification: Monthly Fees Fix</h2>";

// Get next month label (same as in fees-management.php)
$next_month_label = date('M Y', strtotime('first day of next month'));
echo "<p><strong>Checking for fees with month: {$next_month_label}</strong></p>";

// Test the corrected query (with LEFT JOINs instead of INNER JOINs)
echo "<h3>✅ Corrected Query Results (with LEFT JOINs):</h3>";
$corrected_query = "SELECT 
    fc.student_id as id, 
    CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
    c.class_name, 
    fc.fee_month,
    fc.expected_amount,
    fc.paid_amount,
    fc.payment_status
FROM fee_collections fc
LEFT JOIN students s ON fc.student_id = s.id
LEFT JOIN classes c ON s.class_id = c.id
WHERE fc.fee_month LIKE '%{$next_month_label}%'
ORDER BY fc.student_id";

$result = mysqli_query($conn, $corrected_query);
$count = mysqli_num_rows($result);
echo "<p style='color: green; font-weight: bold;'>Found {$count} student(s)</p>";

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background-color: #3498db; color: white;'>";
echo "<th style='padding: 10px;'>ID</th>";
echo "<th style='padding: 10px;'>Student Name</th>";
echo "<th style='padding: 10px;'>Class</th>";
echo "<th style='padding: 10px;'>Fee Month</th>";
echo "<th style='padding: 10px;'>Expected</th>";
echo "<th style='padding: 10px;'>Paid</th>";
echo "<th style='padding: 10px;'>Due</th>";
echo "<th style='padding: 10px;'>Status</th>";
echo "</tr>";

if($count > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $due = ($row['expected_amount'] - $row['paid_amount']);
        echo "<tr style='border-bottom: 1px solid #ddd;'>";
        echo "<td style='padding: 10px;'>{$row['id']}</td>";
        echo "<td style='padding: 10px;'>{$row['student_name']}</td>";
        echo "<td style='padding: 10px;'>{$row['class_name']}</td>";
        echo "<td style='padding: 10px;'>{$row['fee_month']}</td>";
        echo "<td style='padding: 10px; text-align: right;'>৳" . number_format($row['expected_amount'] ?? 0, 2) . "</td>";
        echo "<td style='padding: 10px; text-align: right;'>৳" . number_format($row['paid_amount'] ?? 0, 2) . "</td>";
        echo "<td style='padding: 10px; text-align: right;'>৳" . number_format($due, 2) . "</td>";
        echo "<td style='padding: 10px;'><strong>{$row['payment_status']}</strong></td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8' style='padding: 10px; text-align: center; color: #e74c3c;'>No students found for {$next_month_label}</td></tr>";
}
echo "</table>";

// Check admission applications with paid fees
echo "<h3>📋 Students with Paid Admission Fees:</h3>";
$adm_query = "SELECT a.id, a.first_name, a.last_name, a.class_id, a.application_fee, a.transaction_id, a.payment_method, a.application_date, s.id as student_id FROM admission_applications a LEFT JOIN students s ON a.phone = s.phone WHERE a.application_fee > 0 AND a.transaction_id <> '' ORDER BY a.application_date DESC";
$adm_result = mysqli_query($conn, $adm_query);
$adm_count = mysqli_num_rows($adm_result);
echo "<p style='color: #2980b9;'>Total admission payments: {$adm_count}</p>";

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background-color: #27ae60; color: white;'>";
echo "<th style='padding: 10px;'>App ID</th>";
echo "<th style='padding: 10px;'>Name</th>";
echo "<th style='padding: 10px;'>Class</th>";
echo "<th style='padding: 10px;'>Fee</th>";
echo "<th style='padding: 10px;'>Transaction ID</th>";
echo "<th style='padding: 10px;'>Student ID</th>";
echo "<th style='padding: 10px;'>Status</th>";
echo "</tr>";

if($adm_count > 0) {
    while($row = mysqli_fetch_assoc($adm_result)) {
        $status = !empty($row['student_id']) ? '✅ Linked' : '❌ Not Linked';
        $student_id_display = !empty($row['student_id']) ? $row['student_id'] : 'NULL';
        echo "<tr style='border-bottom: 1px solid #ddd;'>";
        echo "<td style='padding: 10px;'>{$row['id']}</td>";
        echo "<td style='padding: 10px;'>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td style='padding: 10px;'>{$row['class_id']}</td>";
        echo "<td style='padding: 10px; text-align: right;'>৳{$row['application_fee']}</td>";
        echo "<td style='padding: 10px; font-size: 12px;'>{$row['transaction_id']}</td>";
        echo "<td style='padding: 10px;'>{$student_id_display}</td>";
        echo "<td style='padding: 10px;'>{$status}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7' style='padding: 10px; text-align: center;'>No paid admissions found</td></tr>";
}
echo "</table>";

echo "<hr style='margin: 30px 0;'>";
echo "<p style='color: #7f8c8d; font-size: 12px;'>";
echo "<strong>Summary:</strong><br>";
echo "- Monthly fees showing: {$count} student(s)<br>";
echo "- Paid admissions: {$adm_count}<br>";
echo "- Issue: If monthly fees < paid admissions, check if student records were created properly<br>";
echo "</p>";
?>
