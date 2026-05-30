<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';
require_once '../includes/payment_helpers.php';
require_once '../includes/sslcommerz_config.php';

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../parent-login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];
$student_mobile = $_SESSION['student_mobile'] ?? '';
$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$student_id = getFirstParentStudent($conn, $parent_id, $student_mobile)['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fees.php');
    exit();
}

$monthly_fee_id = intval($_POST['monthly_fee_id'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? 'SSLCommerz');
$payment_amount = floatval($_POST['payment_amount'] ?? 0);

if ($monthly_fee_id <= 0 || $payment_amount <= 0) {
    $_SESSION['error'] = 'Invalid payment request. Please choose a fee month and amount.';
    header('Location: fees.php');
    exit();
}

$student_ids_list = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';
$fee_result = mysqli_query($conn, "SELECT id, expected_amount, paid_amount, fee_month, student_id FROM fee_collections WHERE id = $monthly_fee_id AND student_id IN ($student_ids_list) LIMIT 1");
$fee = $fee_result ? mysqli_fetch_assoc($fee_result) : null;

if (!$fee) {
    $_SESSION['error'] = 'Unable to locate the selected fee record.';
    header('Location: fees.php');
    exit();
}

$due_amount = max(0, floatval($fee['expected_amount']) - floatval($fee['paid_amount']));
if ($payment_amount > $due_amount) {
    $payment_amount = $due_amount;
}

if ($payment_amount <= 0) {
    $_SESSION['error'] = 'This fee is already paid or the amount is invalid.';
    header('Location: fees.php');
    exit();
}

$student_info = getStudentClassInfo($conn, $student_id);
$tran_id = 'COACHING-' . time() . '-' . rand(1000, 9999);

// Store pending transaction details server-side so we can recover if callback values are missing.
$_SESSION['sslcz_pending'][$tran_id] = [
    'student_id' => $student_id,
    'monthly_fee_id' => $monthly_fee_id,
    'payment_method' => $payment_method,
    'amount' => number_format($payment_amount, 2, '.', ''),
];

// Persist the transaction id on the fee row so callback processing can recover the payment even if the parent session is lost.
$escapedTranId = mysqli_real_escape_string($conn, $tran_id);
mysqli_query($conn, "UPDATE fee_collections SET transaction_id = '$escapedTranId' WHERE id = $monthly_fee_id AND student_id IN ($student_ids_list)");

$post_data = [
    'store_id' => SSLCOMMERZ_STORE_ID,
    'store_passwd' => SSLCOMMERZ_STORE_PASSWD,
    'total_amount' => number_format($payment_amount, 2, '.', ''),
    'currency' => 'BDT',
    'tran_id' => $tran_id,
    'success_url' => SSLCOMMERZ_SUCCESS_URL,
    'fail_url' => SSLCOMMERZ_FAIL_URL,
    'cancel_url' => SSLCOMMERZ_CANCEL_URL,
    'emi_option' => 0,
    'cus_name' => trim(($student_info['first_name'] ?? '') . ' ' . ($student_info['last_name'] ?? '')) ?: 'Parent',
    'cus_email' => trim($student_info['email'] ?? '') ?: 'no-reply@shuvobd.com',
    'cus_add1' => trim($student_info['group_name'] ?? ''),
    'cus_add2' => trim($student_info['class_name'] ?? ''),
    'cus_city' => 'Dhaka',
    'cus_state' => 'Dhaka',
    'cus_postcode' => '1000',
    'cus_country' => 'Bangladesh',
    'cus_phone' => trim($student_info['phone'] ?? $student_mobile),
    'product_name' => 'Monthly Fee',
    'product_category' => 'Education',
    'product_profile' => 'general',
    'ship_name' => trim($student_info['first_name'] ?? '') ?: 'Parent',
    'ship_add1' => trim($student_info['group_name'] ?? ''),
    'ship_add2' => trim($student_info['class_name'] ?? ''),
    'ship_city' => 'Dhaka',
    'ship_state' => 'Dhaka',
    'ship_postcode' => '1000',
    'ship_country' => 'Bangladesh',
    'value_a' => $student_id,
    'value_b' => $monthly_fee_id,
    'value_c' => $payment_method,
    'value_d' => number_format($payment_amount, 2, '.', ''),
];

$direct_api_url = SSLCOMMERZ_SANDBOX
    ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
    : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, $direct_api_url);
curl_setopt($handle, CURLOPT_TIMEOUT, 30);
curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($handle, CURLOPT_POST, 1);
curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

$content = curl_exec($handle);
$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if ($code == 200 && !curl_errno($handle)) {
    curl_close($handle);
    $sslcommerzResponse = $content;
} else {
    curl_close($handle);
    $_SESSION['error'] = 'FAILED TO CONNECT WITH SSLCOMMERZ API';
    header('Location: fees.php');
    exit();
}

$sslcz = json_decode($sslcommerzResponse, true);

if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != '') {
    echo "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($sslcz['GatewayPageURL'], ENT_QUOTES, 'UTF-8') . "'>";
    exit();
} else {
    $_SESSION['error'] = 'JSON Data parsing error!';
    header('Location: fees.php');
    exit();
}
