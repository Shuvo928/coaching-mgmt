<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/parent_helpers.php';

if (!isset($_SESSION['parent_id'])) {
    header('Location: parent-login.php');
    exit();
}

$parent_id = (int) $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'] ?? '';
$parent_email = $_SESSION['parent_email'] ?? '';
$parent_phone = $_SESSION['parent_phone'] ?? '';
$student_mobile = $_SESSION['student_mobile'] ?? '';
$student_name = $_SESSION['student_name'] ?? '';

$student_ids = getParentStudentIds($conn, $parent_id, $student_mobile);
$firstStudent = getFirstParentStudent($conn, $parent_id, $student_mobile);
$student_id = $firstStudent['id'] ?? 0;
$student_name = $student_name ?: trim(($firstStudent['first_name'] ?? '') . ' ' . ($firstStudent['last_name'] ?? ''));

if (empty($student_ids) && !empty($student_mobile)) {
    $student_query = "SELECT id, first_name, last_name FROM students WHERE phone = '" . mysqli_real_escape_string($conn, $student_mobile) . "' LIMIT 1";
    $student_result = mysqli_query($conn, $student_query);
    $student_data = mysqli_fetch_assoc($student_result);
    $student_id = $student_data['id'] ?? $student_id;
    if (empty($student_name) && !empty($student_data)) {
        $student_name = trim(($student_data['first_name'] ?? '') . ' ' . ($student_data['last_name'] ?? ''));
    }
}

$existingRequest = getParentDiscontinueRequestByParent($conn, $parent_id);
if ($existingRequest && $existingRequest['status'] === 'Pending') {
    $_SESSION['error'] = 'A discontinuation request is already pending admin approval.';
    header('Location: parent/dashboard.php');
    exit();
}

$monthly_due = 0;
$admission_due = 0;
$due_details = [];

if ($student_id > 0) {
    $fee_result = mysqli_query($conn, "SELECT SUM(expected_amount - paid_amount) AS due_total FROM fee_collections WHERE student_id = $student_id AND payment_status != 'paid'");
    $fee_row = mysqli_fetch_assoc($fee_result);
    $monthly_due = max(0, (float)($fee_row['due_total'] ?? 0));
}

$search_phone = !empty($student_mobile) ? $student_mobile : $parent_phone;
if (!empty($search_phone)) {
    $phone = mysqli_real_escape_string($conn, $search_phone);

    $hasMobile = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'")) > 0;
    $hasPhone = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'phone'")) > 0;
    $phoneConditions = [];

    if ($hasMobile) {
        $phoneConditions[] = "mobile = '$phone'";
    }
    if ($hasPhone) {
        $phoneConditions[] = "phone = '$phone'";
    }

    if (!empty($phoneConditions)) {
        $admission_result = mysqli_query($conn, "SELECT SUM(application_fee) AS due_total FROM admission_applications WHERE status = 'Approved' AND (transaction_id = '' OR transaction_id IS NULL) AND (" . implode(' OR ', $phoneConditions) . ")");
        $admission_row = mysqli_fetch_assoc($admission_result);
        $admission_due = max(0, (float)($admission_row['due_total'] ?? 0));
    }
}

if ($monthly_due > 0) {
    $due_details[] = 'Monthly fees due: ৳' . number_format($monthly_due, 2);
}
if ($admission_due > 0) {
    $due_details[] = 'Admission fees due: ৳' . number_format($admission_due, 2);
}
$due_amount = $monthly_due + $admission_due;
$due_summary = !empty($due_details) ? implode('; ', $due_details) : 'No pending fees found.';

$status = createParentDiscontinueRequest(
    $conn,
    $parent_id,
    $parent_name,
    $parent_email,
    $parent_phone,
    $student_id,
    $student_name,
    $due_amount,
    $due_summary
);

if ($status) {
    $_SESSION['success'] = 'Your account discontinuation request has been sent to the admin for approval.';
    if ($due_amount > 0) {
        $_SESSION['success'] .= ' The admin will check the due balance before approving the request.';
    }
} else {
    $_SESSION['error'] = 'Unable to create discontinuation request. Please try again later.';
}

header('Location: parent/dashboard.php');
exit();
