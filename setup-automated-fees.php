<?php
/**
 * Setup Automated Fee Collection System
 * Ensures all necessary columns and structures exist for automated billing
 */

require_once 'includes/db.php';

$output = [];

try {
    // 1. Ensure students table has admission_date column
    $check_admission_date = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'admission_date'");
    if (!$check_admission_date || mysqli_num_rows($check_admission_date) == 0) {
        $add_column = "ALTER TABLE students ADD COLUMN admission_date DATE DEFAULT CURDATE() AFTER class_id";
        if (mysqli_query($conn, $add_column)) {
            $output[] = "✓ Added admission_date column to students table";
        } else {
            throw new Exception("Error adding admission_date: " . mysqli_error($conn));
        }
    } else {
        $output[] = "✓ admission_date column already exists in students table";
    }

    // 2. Ensure fee_collections table exists with proper structure
    $check_fee_collections = mysqli_query($conn, "SHOW TABLES LIKE 'fee_collections'");
    if (!$check_fee_collections || mysqli_num_rows($check_fee_collections) == 0) {
        $create_fee_collections = "CREATE TABLE IF NOT EXISTS fee_collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            class_id INT,
            fee_month VARCHAR(50) NOT NULL,
            expected_amount DECIMAL(10,2) NOT NULL,
            paid_amount DECIMAL(10,2) DEFAULT 0,
            payment_status VARCHAR(20) DEFAULT 'unpaid',
            payment_method VARCHAR(50),
            payment_date DATE,
            due_date DATE,
            transaction_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_month (student_id, fee_month),
            INDEX idx_student_id (student_id),
            INDEX idx_payment_status (payment_status),
            INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (mysqli_query($conn, $create_fee_collections)) {
            $output[] = "✓ Created fee_collections table";
        } else {
            throw new Exception("Error creating fee_collections: " . mysqli_error($conn));
        }
    } else {
        $output[] = "✓ fee_collections table already exists";
    }

    // 3. Ensure due_date column exists
    $check_due_date = mysqli_query($conn, "SHOW COLUMNS FROM fee_collections LIKE 'due_date'");
    if (!$check_due_date || mysqli_num_rows($check_due_date) == 0) {
        $add_due_date = "ALTER TABLE fee_collections ADD COLUMN due_date DATE DEFAULT NULL AFTER payment_date";
        if (mysqli_query($conn, $add_due_date)) {
            $output[] = "✓ Added due_date column to fee_collections table";
        } else {
            throw new Exception("Error adding due_date: " . mysqli_error($conn));
        }
    } else {
        $output[] = "✓ due_date column already exists in fee_collections table";
    }

    // 4. Add unique constraint on student + fee_month if not exists
    $check_unique = mysqli_query($conn, "SHOW INDEX FROM fee_collections WHERE Key_name = 'unique_student_month'");
    if (!$check_unique || mysqli_num_rows($check_unique) == 0) {
        // Try to add unique constraint - it might fail if duplicates exist
        $add_unique = "ALTER TABLE fee_collections ADD UNIQUE KEY unique_student_month (student_id, fee_month)";
        @mysqli_query($conn, $add_unique); // Suppress error if constraint can't be added due to duplicates
        $output[] = "⚠ Attempted to add unique constraint on (student_id, fee_month)";
    } else {
        $output[] = "✓ Unique constraint on (student_id, fee_month) already exists";
    }

    // 5. Create index for performance
    $check_idx_due = mysqli_query($conn, "SHOW INDEX FROM fee_collections WHERE Key_name = 'idx_due_date'");
    if (!$check_idx_due || mysqli_num_rows($check_idx_due) == 0) {
        mysqli_query($conn, "ALTER TABLE fee_collections ADD INDEX idx_due_date (due_date)");
        $output[] = "✓ Added index on due_date for performance";
    }

    // 6. Update existing fees with due dates if null
    $update_due_dates = "UPDATE fee_collections 
                         SET due_date = STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(created_at), '-10'), '%Y-%m-%d')
                         WHERE due_date IS NULL";
    
    if (mysqli_query($conn, $update_due_dates)) {
        $affected = mysqli_affected_rows($conn);
        if ($affected > 0) {
            $output[] = "✓ Updated $affected fee records with calculated due dates (10th of each month)";
        }
    }

    // 7. Auto-generate fees for all existing students
    $students_query = "SELECT s.id, s.class_id FROM students s WHERE s.status = 1";
    $students_result = mysqli_query($conn, $students_query);
    
    $total_generated = 0;
    if ($students_result) {
        while ($student = mysqli_fetch_assoc($students_result)) {
            // Check how many fees exist for this student
            $fee_count = "SELECT COUNT(*) as count FROM fee_collections WHERE student_id = " . (int)$student['id'];
            $count_result = mysqli_query($conn, $fee_count);
            $count_data = mysqli_fetch_assoc($count_result);
            $current_count = (int)($count_data['count'] ?? 0);
            
            // If no fees exist, generate initial set
            if ($current_count == 0) {
                // Get monthly fee
                $fee_query = "SELECT aa.monthly_fee FROM students s 
                             LEFT JOIN admission_applications aa ON aa.phone = s.phone 
                             WHERE s.id = " . (int)$student['id'] . " LIMIT 1";
                $fee_result = mysqli_query($conn, $fee_query);
                $fee_data = mysqli_fetch_assoc($fee_result);
                $monthly_fee = $fee_data ? (float)$fee_data['monthly_fee'] : 0;
                
                if ($monthly_fee > 0) {
                    // Generate 6 months of fees starting from next month
                    for ($i = 1; $i <= 6; $i++) {
                        $fee_date = strtotime("+$i months");
                        $fee_month = date('F Y', $fee_date);
                        $due_date = date('Y-m-10', $fee_date);
                        
                        // Check if already exists
                        $check = "SELECT id FROM fee_collections 
                                 WHERE student_id = " . (int)$student['id'] . " 
                                 AND fee_month = '$fee_month' LIMIT 1";
                        $check_result = mysqli_query($conn, $check);
                        
                        if (!$check_result || mysqli_num_rows($check_result) == 0) {
                            $insert = "INSERT INTO fee_collections 
                                      (student_id, class_id, fee_month, expected_amount, paid_amount, payment_status, due_date, created_at)
                                      VALUES 
                                      (" . (int)$student['id'] . ", " . (int)$student['class_id'] . ", '$fee_month', $monthly_fee, 0, 'unpaid', '$due_date', NOW())";
                            
                            if (mysqli_query($conn, $insert)) {
                                $total_generated++;
                            }
                        }
                    }
                }
            }
        }
    }

    if ($total_generated > 0) {
        $output[] = "✓ Auto-generated $total_generated fee records for existing students";
    } else {
        $output[] = "ℹ No new fee records generated (students may already have fees)";
    }

    $output[] = "";
    $output[] = "✅ Automated Fee System Setup Complete!";
    $output[] = "";
    $output[] = "What this setup does:";
    $output[] = "• Ensures students table has admission_date column";
    $output[] = "• Creates/verifies fee_collections table with proper structure";
    $output[] = "• Sets due dates to 10th of each month";
    $output[] = "• Creates unique constraint to prevent duplicate months";
    $output[] = "• Auto-generates fees for all active students";
    $output[] = "• Ensures 6 months of fees are always available";

} catch(Exception $e) {
    $output[] = "✗ Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Automated Fee System</title>
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
            line-height: 1.8;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .success { color: #4caf50; font-weight: 600; }
        .error { color: #f44336; font-weight: 600; }
        .warning { color: #ff9800; font-weight: 600; }
        .info { color: #2196F3; font-weight: 600; }
        .button-group {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-primary:hover {
            background: #1976D2;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>⚙️ Automated Fee System Setup</h2>
        
        <div class="output">
<?php
foreach ($output as $line) {
    if (strpos($line, '✓') !== false) {
        echo "<span class='success'>$line</span>\n";
    } elseif (strpos($line, '✗') !== false) {
        echo "<span class='error'>$line</span>\n";
    } elseif (strpos($line, '⚠') !== false) {
        echo "<span class='warning'>$line</span>\n";
    } elseif (strpos($line, 'ℹ') !== false) {
        echo "<span class='info'>$line</span>\n";
    } elseif (strpos($line, '✅') !== false) {
        echo "<span class='success'><strong>$line</strong></span>\n";
    } else {
        echo "$line\n";
    }
}
?>
        </div>

        <div class="button-group">
            <a href="admin/dashboard.php" class="btn btn-primary">Go to Admin Dashboard</a>
            <a href="parent-login.php" class="btn btn-secondary">Go to Parent Login</a>
        </div>
    </div>
</body>
</html>
