<?php
require_once 'includes/db.php';

echo "<div style='font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Setting Up Fee Heads</h2>";

// Check if Admission Fee exists
$check_query = "SELECT id FROM fees_head WHERE fee_name = 'Admission Fee' LIMIT 1";
$result = mysqli_query($conn, $check_query);

if(mysqli_num_rows($result) == 0) {
    // Create Admission Fee
    $insert = "INSERT INTO fees_head (fee_name, description, is_mandatory) 
               VALUES ('Admission Fee', 'One-time admission fee for new students', 1)";
    
    if(mysqli_query($conn, $insert)) {
        echo "<p style='color: green;'><strong>✅ Created 'Admission Fee' fee head!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error: " . mysqli_error($conn) . "</strong></p>";
    }
} else {
    echo "<p style='color: green;'><strong>✅ 'Admission Fee' already exists!</strong></p>";
}

// Create other common fee heads
$fee_heads = [
    ['Monthly Fee', 'Monthly tuition fee'],
    ['Exam Fee', 'Examination fee'],
    ['Development Fee', 'Infrastructure and development fee'],
];

foreach($fee_heads as $fee) {
    $check = "SELECT id FROM fees_head WHERE fee_name = '" . $fee[0] . "' LIMIT 1";
    $check_result = mysqli_query($conn, $check);
    
    if(mysqli_num_rows($check_result) == 0) {
        $insert = "INSERT INTO fees_head (fee_name, description, is_mandatory) 
                   VALUES ('" . $fee[0] . "', '" . $fee[1] . "', 1)";
        
        if(mysqli_query($conn, $insert)) {
            echo "<p style='color: green;'><strong>✅ Created '" . $fee[0] . "' fee head!</strong></p>";
        }
    } else {
        echo "<p style='color: green;'><strong>✅ '" . $fee[0] . "' already exists!</strong></p>";
    }
}

echo "<p><strong>Fee heads setup is complete!</strong></p>";
echo "</div>";
?>
