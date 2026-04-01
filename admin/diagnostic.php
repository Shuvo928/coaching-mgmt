<?php
require_once '../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; background: #f5f5f5;'>";
echo "<h2>Database Diagnostic Report</h2>";

// Check admission_applications
echo "<h3>1. Approved Admissions:</h3>";
$apps = mysqli_query($conn, "SELECT id, full_name, status, application_fee, payment_method FROM admission_applications WHERE status = 'Approved' LIMIT 5");
echo "<pre>";
while($row = mysqli_fetch_assoc($apps)) {
    print_r($row);
}
echo "</pre>";

// Check fee_collections
echo "<h3>2. Fee Collections Records:</h3>";
$fees = mysqli_query($conn, "SELECT id, student_id, fee_head_id, amount, paid_amount, receipt_no, status, created_at FROM fee_collections LIMIT 5");
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>";
echo "<th>ID</th><th>Student ID</th><th>Fee Head ID</th><th>Amount</th><th>Paid</th><th>Receipt</th><th>Status</th><th>Created</th>";
echo "</tr>";
if(mysqli_num_rows($fees) > 0) {
    while($row = mysqli_fetch_assoc($fees)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['student_id'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['fee_head_id'] . "</td>";
        echo "<td>" . $row['amount'] . "</td>";
        echo "<td>" . $row['paid_amount'] . "</td>";
        echo "<td>" . $row['receipt_no'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8' style='text-align: center;'>No records found</td></tr>";
}
echo "</table>";

// Check fee_heads
echo "<h3>3. Fee Heads (List):</h3>";
$heads = mysqli_query($conn, "SELECT id, fee_name FROM fees_head");
echo "<pre>";
while($row = mysqli_fetch_assoc($heads)) {
    echo "ID: " . $row['id'] . " - " . $row['fee_name'] . "\n";
}
echo "</pre>";

// Test the query from fees-management.php
echo "<h3>4. Testing fees-management.php Query:</h3>";
$test_query = "SELECT fc.*, s.first_name, s.last_name, s.student_id, fh.fee_name
               FROM fee_collections fc
               LEFT JOIN students s ON fc.student_id = s.id
               JOIN fees_head fh ON fc.fee_head_id = fh.id
               ORDER BY fc.created_at DESC LIMIT 10";
$test_result = mysqli_query($conn, $test_query);

if($test_result) {
    if(mysqli_num_rows($test_result) > 0) {
        echo "<p style='color: green;'><strong>✅ Query returned " . mysqli_num_rows($test_result) . " records</strong></p>";
        echo "<pre>";
        while($row = mysqli_fetch_assoc($test_result)) {
            print_r($row);
        }
        echo "</pre>";
    } else {
        echo "<p style='color: red;'><strong>❌ Query returned 0 records!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Query Error: " . mysqli_error($conn) . "</strong></p>";
}

echo "</div>";
?>
