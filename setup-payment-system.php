<?php
require_once 'includes/db.php';
require_once 'includes/payment_helpers.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Database Setup - Payment System Initialization</h2>";

// Create payment history table
if(createPaymentHistoryTable($conn)) {
    echo "<p style='color: green;'><strong>✅ Payment History Table Created/Verified Successfully!</strong></p>";
} else {
    echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
}

echo "<p><strong>Payment system is ready to use!</strong></p>";
echo "<p><a href='parent-login.php'>← Back to Login</a></p>";
echo "</div>";
?>
