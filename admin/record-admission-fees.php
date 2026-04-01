<?php
require_once '../includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; background: #f5f5f5;'>";
echo "<h2>Recording Fees for Approved Admissions</h2>";

// First, ensure "Admission Fee" head exists
$fee_head_query = "SELECT id FROM fees_head WHERE fee_name = 'Admission Fee' LIMIT 1";
$fee_head_result = mysqli_query($conn, $fee_head_query);

if(mysqli_num_rows($fee_head_result) > 0) {
    $fee_head = mysqli_fetch_assoc($fee_head_result);
    $fee_head_id = $fee_head['id'];
    echo "<p style='color: green;'><strong>✅ Found 'Admission Fee' with ID: $fee_head_id</strong></p>";
} else {
    // Create Admission Fee head
    $create_fee_head = "INSERT INTO fees_head (fee_name, description, is_mandatory) VALUES ('Admission Fee', 'One-time admission fee', 1)";
    if(mysqli_query($conn, $create_fee_head)) {
        $fee_head_id = mysqli_insert_id($conn);
        echo "<p style='color: green;'><strong>✅ Created 'Admission Fee' with ID: $fee_head_id</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error creating fee head: " . mysqli_error($conn) . "</strong></p>";
        exit;
    }
}

// Get all approved admissions
$approved_query = "SELECT id, full_name, application_fee, payment_method FROM admission_applications WHERE status = 'Approved'";
$approved_result = mysqli_query($conn, $approved_query);
$approved_count = mysqli_num_rows($approved_result);

echo "<p><strong>Found $approved_count approved admissions</strong></p>";

$recorded = 0;
$skipped = 0;

while($app = mysqli_fetch_assoc($approved_result)) {
    // Check if fee already recorded
    $check_fee = "SELECT id FROM fee_collections WHERE receipt_no LIKE 'RCP%' AND status = 'Paid' LIMIT 1";
    $check_result = mysqli_query($conn, $check_fee);
    
    // Generate a receipt number
    $receipt_no = 'RCP' . date('Ymd') . str_pad($app['id'], 4, '0', STR_PAD_LEFT);
    
    // Check if this specific receipt already exists
    $check_specific = "SELECT id FROM fee_collections WHERE receipt_no = '$receipt_no' LIMIT 1";
    $check_spec_result = mysqli_query($conn, $check_specific);
    
    if(mysqli_num_rows($check_spec_result) > 0) {
        echo "<p style='color: orange;'><strong>⊘ Skipped: " . $app['full_name'] . " (fee already recorded)</strong></p>";
        $skipped++;
    } else {
        $payment_method = !empty($app['payment_method']) ? $app['payment_method'] : 'Cash';
        
        $fee_insert = "INSERT INTO fee_collections (student_id, fee_head_id, amount, paid_amount, due_amount, payment_date, payment_method, receipt_no, status, created_at) 
                       VALUES (NULL, $fee_head_id, " . floatval($app['application_fee']) . ", " . floatval($app['application_fee']) . ", 0, CURDATE(), '$payment_method', '$receipt_no', 'Paid', NOW())";
        
        if(mysqli_query($conn, $fee_insert)) {
            echo "<p style='color: green;'><strong>✅ Recorded fee for: " . $app['full_name'] . " (Amount: " . $app['application_fee'] . ", Receipt: $receipt_no)</strong></p>";
            $recorded++;
        } else {
            echo "<p style='color: red;'><strong>❌ Error recording fee for " . $app['full_name'] . ": " . mysqli_error($conn) . "</strong></p>";
        }
    }
}

echo "<h3 style='margin-top: 20px;'>Summary:</h3>";
echo "<p><strong>✅ Successfully recorded: $recorded fees</strong></p>";
echo "<p><strong>⊘ Skipped (already recorded): $skipped</strong></p>";

// Show the fees in the system
echo "<h3 style='margin-top: 20px;'>Fees Now in System:</h3>";
$all_fees = mysqli_query($conn, "SELECT fc.id, fc.receipt_no, fc.amount, fc.status, fh.fee_name FROM fee_collections fc JOIN fees_head fh ON fc.fee_head_id = fh.id ORDER BY fc.created_at DESC LIMIT 10");

if(mysqli_num_rows($all_fees) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
    echo "<tr><th>Receipt No</th><th>Fee Type</th><th>Amount</th><th>Status</th></tr>";
    while($row = mysqli_fetch_assoc($all_fees)) {
        echo "<tr>";
        echo "<td>" . $row['receipt_no'] . "</td>";
        echo "<td>" . $row['fee_name'] . "</td>";
        echo "<td>৳" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No fees found in system</p>";
}

echo "</div>";
?>
