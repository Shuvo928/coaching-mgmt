<?php

/**
 * Payment Helper Functions
 * Handles payment processing, history tracking, and receipts
 */

function createPaymentHistoryTable($conn) {
    $query = "CREATE TABLE IF NOT EXISTS payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT,
        fee_collection_id INT,
        transaction_id VARCHAR(100) NOT NULL,
        receipt_no VARCHAR(50) NOT NULL UNIQUE,
        payment_method VARCHAR(50) NOT NULL,
        amount_paid DECIMAL(10, 2) NOT NULL,
        fee_type VARCHAR(100),
        month_name VARCHAR(50),
        payment_status VARCHAR(20) DEFAULT 'completed',
        payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id),
        INDEX idx_receipt_no (receipt_no),
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_payment_date (payment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return mysqli_query($conn, $query);
}

function recordPaymentHistory($conn, $student_id, $class_id, $fee_collection_id, $transaction_id, 
                             $receipt_no, $payment_method, $amount_paid, $fee_type, $month_name) {
    $student_id = (int)$student_id;
    $class_id = (int)$class_id;
    $fee_collection_id = (int)$fee_collection_id;
    $transaction_id = mysqli_real_escape_string($conn, $transaction_id);
    $receipt_no = mysqli_real_escape_string($conn, $receipt_no);
    $payment_method = mysqli_real_escape_string($conn, $payment_method);
    $amount_paid = (float)$amount_paid;
    $fee_type = mysqli_real_escape_string($conn, $fee_type);
    $month_name = mysqli_real_escape_string($conn, $month_name);

    $query = "INSERT INTO payment_history 
              (student_id, class_id, fee_collection_id, transaction_id, receipt_no, 
               payment_method, amount_paid, fee_type, month_name, payment_status) 
              VALUES 
              ($student_id, $class_id, $fee_collection_id, '$transaction_id', '$receipt_no', 
               '$payment_method', $amount_paid, '$fee_type', '$month_name', 'completed')";

    return mysqli_query($conn, $query);
}

function getPaymentHistory($conn, $student_ids = []) {
    if (empty($student_ids)) {
        return [];
    }

    $ids_list = implode(',', array_map('intval', $student_ids));
    $query = "SELECT ph.*, 
                     CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
                     s.phone AS student_phone,
                     c.class_name,
                     a.`group` AS group_name
              FROM payment_history ph
              LEFT JOIN students s ON ph.student_id = s.id
              LEFT JOIN classes c ON ph.class_id = c.id
              LEFT JOIN admission_applications a ON s.phone = a.phone
              WHERE ph.student_id IN ($ids_list)
              ORDER BY ph.payment_date DESC";

    $result = mysqli_query($conn, $query);
    $payments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }

    return $payments;
}

function getAdmissionFeeHistory($conn, $student_ids = []) {
    if (empty($student_ids)) {
        return [];
    }

    $ids_list = implode(',', array_map('intval', $student_ids));
    $query = "SELECT 
                     a.id,
                     CONCAT(a.first_name, ' ', a.last_name) AS student_name,
                     a.phone AS student_phone,
                     c.class_name,
                     a.`group` AS group_name,
                     'Admission Fee' AS fee_type,
                     CAST(a.application_fee AS DECIMAL(10,2)) AS amount_paid,
                     a.payment_method AS payment_method,
                     a.transaction_id AS transaction_id,
                     a.application_date AS payment_date,
                     CONCAT('ADM', LPAD(a.id, 6, '0')) AS receipt_no,
                     'Admission' AS month_name
              FROM admission_applications a
              LEFT JOIN students s ON a.phone = s.phone
              LEFT JOIN classes c ON a.class_id = c.id
              WHERE s.id IN ($ids_list)
                AND a.application_fee > 0
                AND a.transaction_id <> ''
              ORDER BY a.application_date DESC";

    $result = mysqli_query($conn, $query);
    $payments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }

    return $payments;
}

function getPaymentReceiptDetails($conn, $receipt_no, $student_id = null) {
    $receipt_no = mysqli_real_escape_string($conn, $receipt_no);
    
    // Build query with optional student_id filter for data isolation
    $student_filter = '';
    if ($student_id !== null) {
        $student_id = (int)$student_id;
        $student_filter = " AND ph.student_id = $student_id";
    }
    
    $query = "SELECT ph.*, 
                     CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
                     s.phone AS student_phone,
                     s.student_id AS student_code,
                     c.class_name,
                     a.`group` AS group_name,
                     aa.monthly_fee
              FROM payment_history ph
              LEFT JOIN students s ON ph.student_id = s.id
              LEFT JOIN classes c ON ph.class_id = c.id
              LEFT JOIN admission_applications a ON s.phone = a.phone
              LEFT JOIN admission_applications aa ON aa.phone = s.phone
              WHERE ph.receipt_no = '$receipt_no'
              {$student_filter}
              LIMIT 1";

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return getAdmissionFeeReceiptDetails($conn, $receipt_no, $student_id);
}

function getAdmissionFeeReceiptDetails($conn, $receipt_no, $student_id = null) {
    $receipt_no = mysqli_real_escape_string($conn, $receipt_no);
    
    // Build query with optional student_id filter for data isolation
    $student_filter = '';
    if ($student_id !== null) {
        $student_id = (int)$student_id;
        $student_filter = " AND s.id = $student_id";
    }
    
    $query = "SELECT 
                     a.id,
                     CONCAT(a.first_name, ' ', a.last_name) AS student_name,
                     a.phone AS student_phone,
                     c.class_name,
                     a.`group` AS group_name,
                     'Admission Fee' AS fee_type,
                     CAST(a.application_fee AS DECIMAL(10,2)) AS amount_paid,
                     a.payment_method AS payment_method,
                     a.transaction_id AS transaction_id,
                     a.application_date AS payment_date,
                     CONCAT('ADM', LPAD(a.id, 6, '0')) AS receipt_no,
                     'Admission' AS month_name
              FROM admission_applications a
              LEFT JOIN classes c ON a.class_id = c.id
              LEFT JOIN students s ON a.phone = s.phone
              WHERE CONCAT('ADM', LPAD(a.id, 6, '0')) = '$receipt_no'
                AND a.application_fee > 0
                {$student_filter}
              LIMIT 1";

    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getStudentPaymentsSummary($conn, $student_id) {
    $student_id = (int)$student_id;
    $query = "SELECT 
                COUNT(*) AS total_payments,
                SUM(amount_paid) AS total_paid,
                MAX(payment_date) AS last_payment_date,
                GROUP_CONCAT(DISTINCT payment_method ORDER BY payment_method SEPARATOR ', ') AS payment_methods
              FROM payment_history
              WHERE student_id = $student_id";

    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function generateReceiptNumber() {
    return 'RCP' . date('Ymd') . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
}

function getStudentClassInfo($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return null;
    }
    $query = "SELECT s.*, c.class_name, a.`group` AS group_name, a.monthly_fee, a.application_fee
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN admission_applications a ON s.phone = a.phone
              WHERE s.id = $student_id
              LIMIT 1";

    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getClassWiseFeesByStudent($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return null;
    }
    $query = "SELECT 
                c.id,
                c.class_name,
                aa.monthly_fee,
                aa.application_fee,
                aa.`group` AS group_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.id
              LEFT JOIN admission_applications aa ON aa.phone = s.phone
              WHERE s.id = $student_id
              LIMIT 1";

    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function validatePaymentAmount($conn, $student_id, $amount) {
    $student_id = (int)$student_id;
    $amount = (float)$amount;

    // Get class-wise fees
    $fee_info = getClassWiseFeesByStudent($conn, $student_id);
    if (!$fee_info) {
        return false;
    }

    $monthly_fee = (float)$fee_info['monthly_fee'];
    
    // Allow payment up to 10 months at a time
    $max_allowed = $monthly_fee * 10;
    
    return ($amount > 0 && $amount <= $max_allowed);
}

function getOutstandingBalance($conn, $student_id) {
    $student_id = (int)$student_id;
    $query = "SELECT 
                SUM(fc.expected_amount - fc.paid_amount) AS balance
              FROM fee_collections fc
              WHERE fc.student_id = $student_id
              AND fc.payment_status IN ('pending', 'partial')";

    $result = mysqli_query($conn, $query);
    $data = ($result) ? mysqli_fetch_assoc($result) : ['balance' => 0];
    return (float)($data['balance'] ?? 0);
}

// Add due_date column to fee_collections if it doesn't exist
function ensureFeeCollectionsDueDateColumn($conn) {
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM fee_collections LIKE 'due_date'");
    
    if (!$check_column || mysqli_num_rows($check_column) == 0) {
        $add_column = "ALTER TABLE fee_collections ADD COLUMN due_date DATE DEFAULT NULL AFTER payment_date";
        return mysqli_query($conn, $add_column);
    }
    return true;
}

// Auto-generate monthly fees for the next 6 months when student is admitted
function autoGenerateMonthlyFees($conn, $student_id, $class_id, $monthly_fee, $start_month_offset = 1) {
    $student_id = (int)$student_id;
    $class_id = (int)$class_id;
    $monthly_fee = (float)$monthly_fee;
    
    if ($student_id <= 0 || $class_id <= 0 || $monthly_fee <= 0) {
        return false;
    }
    
    // Generate fees for next 6 months (from start_month_offset)
    $generated = 0;
    for ($i = $start_month_offset; $i <= 6; $i++) {
        $fee_date = strtotime("+$i months");
        $fee_month = date('F Y', $fee_date);
        $due_date = date('Y-m-10', $fee_date); // Due by 10th of each month
        
        // Check if fee already exists
        $check = "SELECT id FROM fee_collections WHERE student_id = $student_id AND fee_month = '$fee_month' LIMIT 1";
        $check_result = mysqli_query($conn, $check);
        
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            // Insert new fee
            $insert = "INSERT INTO fee_collections 
                      (student_id, class_id, fee_month, expected_amount, paid_amount, payment_status, due_date, created_at)
                      VALUES 
                      ($student_id, $class_id, '$fee_month', $monthly_fee, 0, 'unpaid', '$due_date', NOW())";
            
            if (mysqli_query($conn, $insert)) {
                $generated++;
            }
        }
    }
    
    return $generated > 0;
}

// Get student fees with due date information and days remaining
function getStudentFeesWithDueInfo($conn, $student_id) {
    $student_id = (int)$student_id;
    
    if ($student_id <= 0) {
        return [];
    }
    
    $query = "SELECT 
                    fc.id,
                    fc.fee_month,
                    fc.expected_amount,
                    fc.paid_amount,
                    (fc.expected_amount - fc.paid_amount) as due_amount,
                    fc.payment_status,
                    fc.due_date,
                    fc.payment_date,
                    fc.created_at,
                    DATEDIFF(fc.due_date, CURDATE()) as days_remaining,
                    CASE 
                        WHEN CURDATE() > fc.due_date AND fc.payment_status != 'paid' THEN 'overdue'
                        WHEN DATEDIFF(fc.due_date, CURDATE()) <= 5 AND DATEDIFF(fc.due_date, CURDATE()) >= 0 AND fc.payment_status != 'paid' THEN 'due-soon'
                        ELSE 'normal'
                    END as fee_status
              FROM fee_collections fc
              WHERE fc.student_id = $student_id
              ORDER BY fc.due_date ASC, fc.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $fees = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fees[] = $row;
        }
    }
    
    return $fees;
}

// Get payment history for a specific student (not multiple)
function getPaymentHistoryByStudent($conn, $student_id) {
    $student_id = (int)$student_id;
    
    if ($student_id <= 0) {
        return [];
    }
    
    $query = "SELECT ph.*, 
                     CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
                     s.phone AS student_phone,
                     c.class_name,
                     a.`group` AS group_name
              FROM payment_history ph
              LEFT JOIN students s ON ph.student_id = s.id
              LEFT JOIN classes c ON ph.class_id = c.id
              LEFT JOIN admission_applications a ON s.phone = a.phone
              WHERE ph.student_id = $student_id
              ORDER BY ph.payment_date DESC";

    $result = mysqli_query($conn, $query);
    $payments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }

    return $payments;
}

// Get admission fee history for a specific student - STRICT filtering by student_id only
function getAdmissionFeeHistoryByStudent($conn, $student_id) {
    $student_id = (int)$student_id;
    
    if ($student_id <= 0) {
        return [];
    }
    
    // Get the specific student's phone to ensure we match ONLY their admission record
    $student_phone_query = "SELECT phone FROM students WHERE id = $student_id LIMIT 1";
    $student_phone_result = mysqli_query($conn, $student_phone_query);
    
    if (!$student_phone_result || mysqli_num_rows($student_phone_result) === 0) {
        return [];
    }
    
    $student_data = mysqli_fetch_assoc($student_phone_result);
    $student_phone = mysqli_real_escape_string($conn, $student_data['phone']);
    
    $query = "SELECT 
                     a.id,
                     CONCAT(a.first_name, ' ', a.last_name) AS student_name,
                     a.phone AS student_phone,
                     c.class_name,
                     a.`group` AS group_name,
                     'Admission Fee' AS fee_type,
                     CAST(a.application_fee AS DECIMAL(10,2)) AS amount_paid,
                     a.payment_method AS payment_method,
                     a.transaction_id AS transaction_id,
                     a.application_date AS payment_date,
                     CONCAT('ADM', LPAD(a.id, 6, '0')) AS receipt_no,
                     'Admission' AS month_name
              FROM admission_applications a
              LEFT JOIN classes c ON a.class_id = c.id
              WHERE a.phone = '$student_phone'
                AND a.application_fee > 0
                AND a.transaction_id <> ''
              ORDER BY a.application_date DESC";

    $result = mysqli_query($conn, $query);
    $payments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }

    return $payments;
}

/**
 * ========================================
 * NEW: AUTOMATED FEE SYSTEM FUNCTIONS
 * ========================================
 */

/**
 * Ensure students table has admission_date column
 * This stores the date when student was admitted to the system
 */
function ensureStudentAdmissionDateColumn($conn) {
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'admission_date'");
    
    if (!$check_column || mysqli_num_rows($check_column) == 0) {
        $add_column = "ALTER TABLE students ADD COLUMN admission_date DATE DEFAULT CURDATE() AFTER class_id";
        return mysqli_query($conn, $add_column);
    }
    return true;
}

/**
 * Get current billing month for a student
 * Returns the month that is currently due for payment
 * Example: If admitted April 15, first billing month is May 10
 */
function getCurrentBillingMonth($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return null;
    }
    
    // Get the first unpaid or partial fee (earliest due date)
    $query = "SELECT 
                fc.id,
                fc.fee_month,
                fc.expected_amount,
                fc.paid_amount,
                (fc.expected_amount - fc.paid_amount) as due_amount,
                fc.payment_status,
                fc.due_date,
                DATEDIFF(fc.due_date, CURDATE()) as days_until_due
              FROM fee_collections fc
              WHERE fc.student_id = $student_id
                AND fc.payment_status IN ('unpaid', 'partial')
              ORDER BY fc.due_date ASC
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

/**
 * Get the next billing month to be generated
 * Based on admission date, calculate which month should come next
 */
function getNextBillingMonth($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return null;
    }
    
    // Get last generated fee month
    $query = "SELECT MAX(fee_month) as last_month, MAX(due_date) as last_due_date
              FROM fee_collections
              WHERE student_id = $student_id";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if ($data && $data['last_due_date']) {
        // Next month is 1 month after the last due date
        return [
            'month_label' => date('F Y', strtotime($data['last_due_date'] . ' +1 month')),
            'due_date' => date('Y-m-10', strtotime($data['last_due_date'] . ' +1 month')),
            'next_timestamp' => strtotime($data['last_due_date'] . ' +1 month')
        ];
    }
    
    return null;
}

/**
 * Get class fee amount for a student
 * Fetches fee from admission_applications (primary source)
 * This ensures parent sees correct fee from their application
 */
function getClassFeeAmount($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return 0;
    }
    
    $query = "SELECT aa.monthly_fee
              FROM students s
              LEFT JOIN admission_applications aa ON aa.phone = s.phone
              WHERE s.id = $student_id
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    $data = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    
    return $data ? (float)$data['monthly_fee'] : 0;
}

/**
 * Auto-generate monthly fees for a specific student
 * Called when student is admitted or when checking fees
 * Generates fees from next billing month onwards (6 months ahead)
 */
function autoGenerateMonthlyFeesForStudent($conn, $student_id, $class_id) {
    $student_id = (int)$student_id;
    $class_id = (int)$class_id;
    
    if ($student_id <= 0 || $class_id <= 0) {
        return false;
    }
    
    // Get monthly fee amount
    $monthly_fee = getClassFeeAmount($conn, $student_id);
    if ($monthly_fee <= 0) {
        return false;
    }
    
    // Get the latest fee month to determine where to start generating
    $latest_query = "SELECT fee_month FROM fee_collections 
                     WHERE student_id = $student_id 
                     ORDER BY due_date DESC LIMIT 1";
    $latest_result = mysqli_query($conn, $latest_query);
    $latest_data = mysqli_fetch_assoc($latest_result);
    $latest_month = $latest_data['fee_month'] ?? null;
    
    // Determine how many months ahead to generate (at least 6 months)
    $months_to_generate = 6;
    
    $generated = 0;
    
    // Generate fees for the next X months
    for ($i = 1; $i <= $months_to_generate; $i++) {
        $fee_date = strtotime("+$i months");
        $fee_month = date('F Y', $fee_date);
        $due_date = date('Y-m-10', $fee_date); // Due by 10th of each month
        
        // Check if fee already exists for this month
        $check = "SELECT id FROM fee_collections 
                  WHERE student_id = $student_id 
                  AND fee_month = '$fee_month' 
                  LIMIT 1";
        $check_result = mysqli_query($conn, $check);
        
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            // Insert new fee
            $insert = "INSERT INTO fee_collections 
                      (student_id, class_id, fee_month, expected_amount, paid_amount, payment_status, due_date, created_at)
                      VALUES 
                      ($student_id, $class_id, '$fee_month', $monthly_fee, 0, 'unpaid', '$due_date', NOW())";
            
            if (mysqli_query($conn, $insert)) {
                $generated++;
            }
        }
    }
    
    return $generated > 0;
}

/**
 * Auto-generate monthly fees for ALL students
 * Run this as a daily cron job or manually to ensure all students have up-to-date fees
 * Prevents manual generation and ensures automation
 */
function autoGenerateMonthlyFeesForAllStudents($conn) {
    $query = "SELECT s.id, s.class_id 
              FROM students s
              WHERE s.status = 1
              ORDER BY s.id";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        return false;
    }
    
    $processed = 0;
    while ($student = mysqli_fetch_assoc($result)) {
        if (autoGenerateMonthlyFeesForStudent($conn, $student['id'], $student['class_id'])) {
            $processed++;
        }
    }
    
    return $processed;
}

/**
 * Get current and next 3 months of fees for a student
 * Used for dashboard display
 */
function getUpcomingFeesForStudent($conn, $student_id, $limit = 2) {
    $student_id = (int)$student_id;
    $limit = (int)$limit;
    
    if ($student_id <= 0 || $limit <= 0) {
        return [];
    }
    
    $query = "SELECT 
                fc.id,
                fc.fee_month,
                fc.expected_amount,
                fc.paid_amount,
                (fc.expected_amount - fc.paid_amount) as due_amount,
                fc.payment_status,
                fc.due_date,
                DATEDIFF(fc.due_date, CURDATE()) as days_remaining,
                CASE 
                    WHEN fc.payment_status = 'paid' THEN 'paid'
                    WHEN CURDATE() > fc.due_date AND fc.payment_status != 'paid' THEN 'overdue'
                    WHEN DATEDIFF(fc.due_date, CURDATE()) <= 5 AND DATEDIFF(fc.due_date, CURDATE()) >= 0 AND fc.payment_status != 'paid' THEN 'due-soon'
                    ELSE 'upcoming'
                END as fee_status
              FROM fee_collections fc
              WHERE fc.student_id = $student_id
              AND STR_TO_DATE(CONCAT('01 ', fc.fee_month), '%d %M %Y') >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              ORDER BY STR_TO_DATE(CONCAT('01 ', fc.fee_month), '%d %M %Y') ASC
              LIMIT $limit";
    
    $result = mysqli_query($conn, $query);
    $fees = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fees[] = $row;
        }
    }
    
    return $fees;
}
?>
