<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';
require_once '../includes/payment_helpers.php';
require_once '../includes/sslcommerz_config.php';

ensureFeeCollectionsReceiptColumn($conn);

$parent_id = $_SESSION['parent_id'] ?? null;
$student_mobile = $_SESSION['student_mobile'] ?? '';
$student_ids = [];

$getRequestValue = function(string $key) {
    return trim($_POST[$key] ?? $_GET[$key] ?? '');
};

$student_id = intval($getRequestValue('value_a'));
$monthly_fee_id = intval($getRequestValue('value_b'));
$payment_method = trim($getRequestValue('value_c'));
$tran_id = trim($getRequestValue('tran_id'));

// Fallback to pending transaction data if gateway did not return value_a/value_b.
if (($student_id <= 0 || $monthly_fee_id <= 0) && !empty($tran_id)) {
    if (!empty($_SESSION['sslcz_pending'][$tran_id])) {
        $pending = $_SESSION['sslcz_pending'][$tran_id];
    } else {
        $escapedTranId = mysqli_real_escape_string($conn, $tran_id);
        $pendingResult = mysqli_query($conn, "SELECT id AS monthly_fee_id, student_id, payment_method FROM fee_collections WHERE transaction_id = '$escapedTranId' LIMIT 1");
        $pending = $pendingResult ? mysqli_fetch_assoc($pendingResult) : null;
    }

    if (!empty($pending)) {
        $student_id = $student_id > 0 ? $student_id : intval($pending['student_id'] ?? 0);
        $monthly_fee_id = $monthly_fee_id > 0 ? $monthly_fee_id : intval($pending['monthly_fee_id'] ?? 0);
        $payment_method = $payment_method !== '' ? $payment_method : trim($pending['payment_method'] ?? '');
    }
}

if (!$tran_id || $student_id <= 0 || $monthly_fee_id <= 0) {
    file_put_contents(__DIR__ . '/sslcz_success_debug.log', "[" . date('Y-m-d H:i:s') . "] Missing callback values: " . print_r([
        'POST' => $_POST,
        'GET' => $_GET,
        'student_id' => $student_id,
        'monthly_fee_id' => $monthly_fee_id,
        'tran_id' => $tran_id,
        'pending' => $_SESSION['sslcz_pending'][$tran_id] ?? null,
    ], true), FILE_APPEND);
    $_SESSION['error'] = 'Payment response is invalid. Please try again.';
    header('Location: fees.php');
    exit();
}

$hasValidPaymentContext = false;
if ($parent_id) {
    $student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
    if (in_array($student_id, $student_ids, true)) {
        $hasValidPaymentContext = true;
    }
}

if (!$hasValidPaymentContext && !empty($tran_id)) {
    $escapedTranId = mysqli_real_escape_string($conn, $tran_id);
    $checkPending = mysqli_query($conn, "SELECT id FROM fee_collections WHERE id = $monthly_fee_id AND transaction_id = '$escapedTranId' LIMIT 1");
    if ($checkPending && mysqli_num_rows($checkPending) > 0) {
        $hasValidPaymentContext = true;
    }
}

if (!$hasValidPaymentContext) {
    $_SESSION['error'] = 'Unauthorized payment response.';
    header('Location: fees.php');
    exit();
}

$validation = sslcommerzValidateTransaction($tran_id);

$validationStatus = '';
if (is_array($validation)) {
    if (!empty($validation['status'])) {
        $validationStatus = strtoupper(trim($validation['status']));
    } elseif (!empty($validation['element'][0]['status'])) {
        $validationStatus = strtoupper(trim($validation['element'][0]['status']));
    }
}

if ($validationStatus !== 'VALID') {
    file_put_contents(__DIR__ . '/sslcz_success_debug.log', "[" . date('Y-m-d H:i:s') . "] Validation failed for tran_id={$tran_id}, status={$validationStatus}, response=" . print_r($validation, true) . PHP_EOL, FILE_APPEND);
    $_SESSION['error'] = 'Transaction verification failed. Please contact support.';
    header('Location: fees.php');
    exit();
}

$amount_paid = floatval(
    $validation['amount'] ??
    $validation['element'][0]['amount'] ??
    floatval($getRequestValue('amount')) ?:
    floatval($getRequestValue('value_d')) ?:
    0
);
$amount_paid = max(0, $amount_paid);

$fee_result = mysqli_query($conn, "SELECT * FROM fee_collections WHERE id = $monthly_fee_id AND student_id = $student_id LIMIT 1");
$fee = $fee_result ? mysqli_fetch_assoc($fee_result) : null;

if (!$fee) {
    $_SESSION['error'] = 'Unable to locate the fee record for this payment.';
    header('Location: fees.php');
    exit();
}

$due_amount = max(0, floatval($fee['expected_amount']) - floatval($fee['paid_amount']));
if ($amount_paid <= 0 || $due_amount <= 0) {
    $_SESSION['error'] = 'Payment amount is invalid or fee already paid.';
    header('Location: fees.php');
    exit();
}

$recorded_amount = min($amount_paid, $due_amount);
$new_paid = floatval($fee['paid_amount']) + $recorded_amount;
$new_due = max(0, floatval($fee['expected_amount']) - $new_paid);
$new_status = $new_due <= 0 ? 'paid' : 'partial';

$receipt_no = generateReceiptNumber();
// Use actual payment method instead of hardcoding to SSLCommerz
$actual_payment_method = trim($payment_method) ?: 'SSLCommerz';
$status_capitalized = $new_status === 'paid' ? 'Paid' : 'Partial';
$update_fee = "UPDATE fee_collections SET paid_amount = $new_paid, due_amount = $new_due, payment_status = '$new_status', status = '$status_capitalized', payment_method = '$actual_payment_method', payment_date = CURDATE(), transaction_id = '$tran_id', receipt_no = '$receipt_no' WHERE id = $monthly_fee_id";

$paymentSaved = mysqli_query($conn, $update_fee);
if ($paymentSaved) {
    recordPaymentHistory($conn, $student_id, $fee['class_id'] ?? 0, $monthly_fee_id, $tran_id, $receipt_no, $actual_payment_method, $recorded_amount, 'Monthly Fee', $fee['fee_month'] ?? 'N/A');
    unset($_SESSION['sslcz_pending'][$tran_id]);

    $receipt_details = [
        'receipt_no' => $receipt_no,
        'payment_date' => date('Y-m-d H:i:s'),
        'student_name' => '',
        'student_code' => '',
        'class_name' => '',
        'group_name' => '',
        'fee_type' => 'Monthly Fee',
        'month_name' => $fee['fee_month'] ?? 'N/A',
        'payment_method' => $actual_payment_method,
        'transaction_id' => $tran_id,
        'amount_paid' => $recorded_amount,
    ];

    $student_result = mysqli_query($conn, "SELECT s.first_name, s.last_name, s.student_id AS student_code, c.class_name, aa.`group` AS group_name FROM students s LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN admission_applications aa ON s.phone = aa.phone WHERE s.id = $student_id LIMIT 1");
    if ($student_result && mysqli_num_rows($student_result) > 0) {
        $student_row = mysqli_fetch_assoc($student_result);
        $receipt_details['student_name'] = trim(($student_row['first_name'] ?? '') . ' ' . ($student_row['last_name'] ?? ''));
        $receipt_details['student_code'] = $student_row['student_code'] ?? '';
        $receipt_details['class_name'] = $student_row['class_name'] ?? '';
        $receipt_details['group_name'] = $student_row['group_name'] ?? '';
    }

    if ($parent_id) {
        $_SESSION['receipt_no'] = $receipt_no;
        $_SESSION['success'] = "Monthly fee payment successful. Amount ৳" . number_format($recorded_amount, 2) . " has been verified automatically.";
        header('Location: fees.php');
        exit();
    }

    // Parent session is missing, show the receipt directly.
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Receipt</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Poppins', sans-serif; background: #f3f6fb; }
            .receipt-container { max-width: 760px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 18px 60px rgba(15, 23, 42, 0.08); }
            .receipt-header { text-align: center; margin-bottom: 28px; }
            .receipt-header h2 { margin-bottom: 8px; }
            .receipt-header p { color: #64748b; }
            .receipt-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #e2e8f0; }
            .receipt-row:last-child { border-bottom: none; }
            .receipt-label { color: #475569; font-weight: 500; }
            .receipt-value { color: #0f172a; font-weight: 600; }
            .receipt-row.total .receipt-label { font-size: 1rem; }
            .receipt-row.total .receipt-value { font-size: 1.1rem; }
            .receipt-footer { margin-top: 22px; padding-top: 18px; border-top: 1px solid #e2e8f0; color: #475569; }
            .print-controls { display: flex; justify-content: center; gap: 12px; margin-top: 24px; }
            .print-btn, .back-btn { width: 180px; }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <h2><i class="fas fa-receipt"></i> Payment Receipt</h2>
                <p>Thank you for your payment</p>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Receipt Number</span>
                <span class="receipt-value"><?php echo htmlspecialchars($receipt_details['receipt_no']); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Payment Date</span>
                <span class="receipt-value"><?php echo date('d M, Y H:i A', strtotime($receipt_details['payment_date'])); ?></span>
            </div>
            <div class="receipt-row" style="border-bottom: none; display: block; background: #f8fafc; padding: 18px; border-radius: 12px; margin: 20px 0;">
                <div class="receipt-row" style="justify-content: space-between; border: none; padding: 6px 0;"><span class="receipt-label">Student Name</span><span class="receipt-value"><?php echo htmlspecialchars($receipt_details['student_name']); ?></span></div>
                <div class="receipt-row" style="justify-content: space-between; border: none; padding: 6px 0;"><span class="receipt-label">Student ID</span><span class="receipt-value"><?php echo htmlspecialchars($receipt_details['student_code']); ?></span></div>
                <div class="receipt-row" style="justify-content: space-between; border: none; padding: 6px 0;"><span class="receipt-label">Class</span><span class="receipt-value"><?php echo htmlspecialchars($receipt_details['class_name']); ?></span></div>
                <div class="receipt-row" style="justify-content: space-between; border: none; padding: 6px 0;"><span class="receipt-label">Group</span><span class="receipt-value"><?php echo htmlspecialchars($receipt_details['group_name']); ?></span></div>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Fee Type</span>
                <span class="receipt-value"><?php echo htmlspecialchars($receipt_details['fee_type']); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Month/Period</span>
                <span class="receipt-value"><?php echo htmlspecialchars($receipt_details['month_name']); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Payment Method</span>
                <span class="receipt-value"><?php echo htmlspecialchars($receipt_details['payment_method']); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Transaction ID</span>
                <span class="receipt-value"><?php echo htmlspecialchars($receipt_details['transaction_id']); ?></span>
            </div>
            <div class="receipt-row total">
                <span class="receipt-label">Amount Paid</span>
                <span class="receipt-value">৳<?php echo number_format($receipt_details['amount_paid'], 2); ?></span>
            </div>
            <div class="receipt-footer">
                <p><strong>Payment Confirmation</strong></p>
                <p>This is an automated receipt. Please keep it for your records.</p>
                <p>For any queries, contact us at the office.</p>
            </div>
            <div class="print-controls">
                <button class="btn btn-primary print-btn" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Receipt</button>
                <a class="btn btn-secondary back-btn" href="../parent-login.php">Back to Login</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
} else {
    $_SESSION['error'] = 'Payment recorded but updating fee status failed. Please notify admin.';
    header('Location: fees.php');
    exit();
}
