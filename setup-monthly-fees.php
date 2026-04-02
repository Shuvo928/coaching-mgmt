<?php
/**
 * Setup Monthly Fees Table
 * Run this once to create the monthly_fees table and initialize monthly fees for all students
 */

require_once 'includes/db.php';

$output = [];

try {
    // 1. Create monthly_fees table
    $create_table = "CREATE TABLE IF NOT EXISTS monthly_fees (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        month VARCHAR(20) NOT NULL,
        year INT NOT NULL,
        tuition_fee DECIMAL(10,2) NOT NULL,
        paid_amount DECIMAL(10,2) DEFAULT 0,
        due_amount DECIMAL(10,2) NOT NULL,
        status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
        payment_date DATE,
        payment_method VARCHAR(50),
        receipt_no VARCHAR(50),
        transaction_id VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_month (student_id, month, year)
    )";

    if(mysqli_query($conn, $create_table)) {
        $output[] = "✓ monthly_fees table created successfully";
    } else {
        throw new Exception("Error creating monthly_fees table: " . mysqli_error($conn));
    }

    // 2. Get all active students and create monthly fees for current and next month
    $current_month = date('F');
    $current_year = date('Y');
    $next_month = date('F', strtotime('+1 month'));
    $next_year = date('Y', strtotime('+1 month'));

    $students = "SELECT s.id, s.class_id FROM students s WHERE s.status = 1";
    $student_result = mysqli_query($conn, $students);

    if(!$student_result) {
        throw new Exception("Error fetching students: " . mysqli_error($conn));
    }

    $inserted = 0;
    $skipped = 0;

    while($student = mysqli_fetch_assoc($student_result)) {
        $student_id = $student['id'];
        $class_id = $student['class_id'];

        // Get class monthly fee
        $class_fee_query = "SELECT COALESCE(SUM(cf.amount), 0) as monthly_fee 
                           FROM class_fees cf
                           WHERE cf.class_id = $class_id 
                           AND cf.fee_head_id IN (
                               SELECT id FROM fees_head WHERE fee_name LIKE '%Tuition%' OR fee_name LIKE '%Monthly%'
                           )";
        
        $fee_result = mysqli_query($conn, $class_fee_query);
        $fee_data = mysqli_fetch_assoc($fee_result);
        $monthly_fee = $fee_data['monthly_fee'] ?: 0;

        // Insert current month fee if not exists
        $check_current = "SELECT id FROM monthly_fees 
                         WHERE student_id = $student_id AND month = '$current_month' AND year = $current_year";
        $check_result = mysqli_query($conn, $check_current);

        if(mysqli_num_rows($check_result) == 0) {
            $insert_current = "INSERT INTO monthly_fees (student_id, class_id, month, year, tuition_fee, due_amount) 
                             VALUES ($student_id, $class_id, '$current_month', $current_year, $monthly_fee, $monthly_fee)";
            if(mysqli_query($conn, $insert_current)) {
                $inserted++;
            }
        } else {
            $skipped++;
        }

        // Insert next month fee if not exists
        $check_next = "SELECT id FROM monthly_fees 
                      WHERE student_id = $student_id AND month = '$next_month' AND year = $next_year";
        $check_result = mysqli_query($conn, $check_next);

        if(mysqli_num_rows($check_result) == 0) {
            $insert_next = "INSERT INTO monthly_fees (student_id, class_id, month, year, tuition_fee, due_amount) 
                          VALUES ($student_id, $class_id, '$next_month', $next_year, $monthly_fee, $monthly_fee)";
            if(mysqli_query($conn, $insert_next)) {
                $inserted++;
            }
        } else {
            $skipped++;
        }
    }

    $output[] = "✓ Initialized monthly fees: $inserted records created, $skipped already exist";

} catch(Exception $e) {
    $output[] = "✗ Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Monthly Fees</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
        }
        .container {
            background: #f5f5f5;
            padding: 25px;
            border-radius: 8px;
            border-left: 5px solid #4caf50;
        }
        .output {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-family: monospace;
            line-height: 1.8;
        }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        h2 { color: #333; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Monthly Fees Setup</h2>
        <div class="output">
            <?php foreach($output as $msg): ?>
                <div class="<?php echo strpos($msg, '✓') !== false ? 'success' : 'error'; ?>">
                    <?php echo $msg; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 20px; text-align: center;">
            <a href="admin/dashboard.php" style="color: #4caf50; text-decoration: none;">← Return to Dashboard</a>
        </p>
    </div>
</body>
</html>
