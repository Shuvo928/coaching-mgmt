<?php
/**
 * Setup Monthly Fees from Admission Data
 * This pulls monthly fees from admission_applications and updates monthly_fees table
 */

require_once 'includes/db.php';

$output = [];

try {
    // Get all students with their admission monthly fees
    $students_query = "SELECT s.id as student_id, s.class_id, aa.monthly_fee
                      FROM students s
                      INNER JOIN admission_applications aa ON s.phone = aa.mobile
                      WHERE s.status = 1 AND aa.status = 'Approved'";
    
    $students_result = mysqli_query($conn, $students_query);

    if(!$students_result) {
        throw new Exception("Error fetching students: " . mysqli_error($conn));
    }

    $updated = 0;
    $errors = 0;

    while($student = mysqli_fetch_assoc($students_result)) {
        $student_id = $student['student_id'];
        $monthly_fee = $student['monthly_fee'];

        if($monthly_fee <= 0) {
            $errors++;
            continue;
        }

        // Update all unpaid monthly fees for this student with their admission fee
        $update_fee = "UPDATE monthly_fees 
                      SET tuition_fee = $monthly_fee, 
                          due_amount = $monthly_fee
                      WHERE student_id = $student_id AND status = 'Unpaid'";

        if(mysqli_query($conn, $update_fee)) {
            $updated += mysqli_affected_rows($conn);
        }
    }

    $output[] = "✓ Updated $updated unpaid monthly fees with admission fees";
    if($errors > 0) {
        $output[] = "⚠ Skipped $errors students with invalid monthly fees";
    }

} catch(Exception $e) {
    $output[] = "✗ Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Monthly Fees from Admission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-top: 0;
            border-bottom: 3px solid #4caf50;
            padding-bottom: 15px;
        }
        .output {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            font-family: monospace;
            line-height: 2;
        }
        .success { color: #4caf50; font-weight: 600; }
        .error { color: #f44336; font-weight: 600; }
        .warning { color: #ff9800; font-weight: 600; }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
            color: #1565c0;
        }
        .note {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            border-left: 4px solid #ff9800;
            color: #856404;
        }
        a {
            color: #4caf50;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>💰 Monthly Fees Setup (from Admission)</h2>
        
        <div class="info">
            <strong>ℹ️ What This Does:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Pulls monthly fee amounts from <strong>admission_applications</strong> for each student</li>
                <li>Updates all unpaid monthly fees with the student's admission monthly fee</li>
                <li>Each student pays their specific monthly fee (set during admission)</li>
                <li>Parents will see their exact monthly fee when they login</li>
            </ul>
        </div>

        <div class="output">
            <?php foreach($output as $msg): ?>
                <div class="<?php 
                    if(strpos($msg, '✓') !== false) echo 'success';
                    elseif(strpos($msg, '✗') !== false) echo 'error';
                    else echo 'warning';
                ?>">
                    <?php echo $msg; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="note">
            <strong>📌 How It Works:</strong>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>When parent applies for admission → Sets monthly fee (e.g., ৳5,000)</li>
                <li>Admin approves admission → Student record created</li>
                <li>This script reads that monthly fee from admission_applications</li>
                <li>Updates monthly_fees table for that student</li>
                <li>Parent sees exactly what they agreed to pay 💯</li>
            </ol>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="admin/dashboard.php">← Return to Dashboard</a>
        </p>
    </div>
</body>
</html>
