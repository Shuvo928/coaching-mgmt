<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once '../includes/payment_helpers.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if admin
if($_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Function to send SMS
function sendSMS($mobile, $message) {
    $api_key = "K6uCeGByYLJRtIIZRzQ";
    $mobile = "88" . preg_replace('/^0/', '', $mobile); // Format: 880XXXXXXXXXX
    
    $url = "http://bulksmsbd.net/api/smsapi";
    
    $data = [
        "api_key" => $api_key,
        "type" => "text",
        "number" => $mobile,
        "senderid" => "8809601000500",
        "message" => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return true;
}

function ensureAdmissionFeeRecordedColumn($conn) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'fee_recorded'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE admission_applications ADD COLUMN fee_recorded TINYINT(1) DEFAULT 0 AFTER application_fee");
    }
}

function ensureAdmissionCredentialColumns($conn) {
    $checkUsername = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'username'");
    if ($checkUsername && mysqli_num_rows($checkUsername) === 0) {
        mysqli_query($conn, "ALTER TABLE admission_applications ADD COLUMN username VARCHAR(100) NULL AFTER parent_phone");
    }
    $checkPassword = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'password_hash'");
    if ($checkPassword && mysqli_num_rows($checkPassword) === 0) {
        mysqli_query($conn, "ALTER TABLE admission_applications ADD COLUMN password_hash VARCHAR(255) NULL AFTER username");
    }
}

ensureAdmissionFeeRecordedColumn($conn);
ensureAdmissionCredentialColumns($conn);

function admissionColumnExists($conn, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function getAdmissionColumnMap($conn) {
    $hasFullName = admissionColumnExists($conn, 'full_name');
    $hasFirstName = admissionColumnExists($conn, 'first_name');
    $hasLastName = admissionColumnExists($conn, 'last_name');
    $hasMobile = admissionColumnExists($conn, 'mobile');
    $hasPhone = admissionColumnExists($conn, 'phone');

    $nameField = $hasFullName
        ? 'full_name'
        : ($hasFirstName && $hasLastName ? "CONCAT(first_name, ' ', last_name)" : "''");
    $phoneField = $hasMobile ? 'mobile' : ($hasPhone ? 'phone' : "''");

    return [
        'hasFullName' => $hasFullName,
        'hasFirstName' => $hasFirstName,
        'hasLastName' => $hasLastName,
        'hasMobile' => $hasMobile,
        'hasPhone' => $hasPhone,
        'nameField' => $nameField,
        'phoneField' => $phoneField,
    ];
}

$admissionColumns = getAdmissionColumnMap($conn);

function generateStudentID($conn) {
    $prefix = 'STU';
    $year = date('Y');
    $pattern = $prefix . $year . '%';

    $query = "SELECT student_id FROM students WHERE student_id LIKE '$pattern' ORDER BY student_id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastId = $row['student_id'];
        $lastNumber = intval(substr($lastId, strlen($prefix . $year)));
        $count = $lastNumber + 1;
    } else {
        $count = 1;
    }

    do {
        $newId = $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        $checkQuery = "SELECT id FROM students WHERE student_id = '$newId' LIMIT 1";
        $checkResult = mysqli_query($conn, $checkQuery);
        $count++;
    } while($checkResult && mysqli_num_rows($checkResult) > 0);

    return $newId;
}

function createStudentUserFromAdmission($conn, $app) {
    $username = trim($app['username'] ?? '');
    $password_hash = trim($app['password_hash'] ?? '');
    $email = mysqli_real_escape_string($conn, $app['email']);
    $mobile = mysqli_real_escape_string($conn, $app['mobile']);
    $gender = mysqli_real_escape_string($conn, $app['gender']);
    $address = mysqli_real_escape_string($conn, $app['address']);
    $full_name = trim($app['full_name']);
    $program = mysqli_real_escape_string($conn, $app['program'] ?? '');
    $group_name = mysqli_real_escape_string($conn, $app['group'] ?? '');

    // Get class_id and group_id
    $class_id = NULL;
    $group_id = NULL;
    if(!empty($program)) {
        $class_query = mysqli_query($conn, "SELECT id FROM classes WHERE class_name = '$program' LIMIT 1");
        if($class_query && mysqli_num_rows($class_query) > 0) {
            $class_id = mysqli_fetch_assoc($class_query)['id'];
        }
    }
    if(!empty($group_name)) {
        $group_query = mysqli_query($conn, "SELECT id FROM groups WHERE group_name = '$group_name' LIMIT 1");
        if($group_query && mysqli_num_rows($group_query) > 0) {
            $group_id = mysqli_fetch_assoc($group_query)['id'];
        }
    }

    // Generate a username if none exists
    if(empty($username)) {
        $base = '';
        if(!empty($app['email'])) {
            $base = strtolower(preg_replace('/[^a-z0-9]/', '', strstr($app['email'], '@', true)));
        }
        if(empty($base) && !empty($full_name)) {
            $base = strtolower(preg_replace('/[^a-z0-9]/', '', $full_name));
        }
        if(empty($base)) {
            $base = 'student';
        }

        $candidate = substr($base, 0, 20);
        $suffix = 1;
        while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username = '$candidate' LIMIT 1")) > 0) {
            $candidate = substr($base, 0, 18) . $suffix;
            $suffix++;
        }

        $username = mysqli_real_escape_string($conn, $candidate);
    } else {
        $username = mysqli_real_escape_string($conn, $username);
    }

    // Generate a password hash if none exists
    $plain_password = null;
    if(empty($password_hash)) {
        $plain_password = 'Stud' . mt_rand(1000, 9999);
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
    }
    $password_hash = mysqli_real_escape_string($conn, $password_hash);
    $nameParts = explode(' ', $full_name);
    $first_name = mysqli_real_escape_string($conn, array_shift($nameParts));
    $last_name = mysqli_real_escape_string($conn, trim(implode(' ', $nameParts)));

    if(empty($last_name)) {
        $last_name = '';
    }

    $inserted_new_user = false;
    $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' LIMIT 1");
    if($check_user && mysqli_num_rows($check_user) > 0) {
        $existing_user = mysqli_fetch_assoc($check_user);
        $user_id = intval($existing_user['id']);

        $student_check = mysqli_query($conn, "SELECT id FROM students WHERE user_id = $user_id LIMIT 1");
        if($student_check && mysqli_num_rows($student_check) > 0) {
            return true;
        }
    } else {
        $user_query = "INSERT INTO users (username, password, email, role, status) VALUES ('$username', '$password_hash', '$email', 'student', 1)";
        if(!mysqli_query($conn, $user_query)) {
            return false;
        }

        $user_id = mysqli_insert_id($conn);
        $inserted_new_user = true;
    }

    $student_unique_id = generateStudentID($conn);

    $insert_student = "INSERT INTO students (user_id, student_id, first_name, last_name, father_name, mother_name, email, phone, dob, gender, address, photo, class_id, group_id, admission_date, status) 
                      VALUES ($user_id, '$student_unique_id', '$first_name', '$last_name', '', '', '$email', '$mobile', NULL, '$gender', '$address', NULL, " . ($class_id ? $class_id : 'NULL') . ", " . ($group_id ? $group_id : 'NULL') . ", NOW(), 1)";

    if(!mysqli_query($conn, $insert_student)) {
        if($inserted_new_user) {
            mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
        }
        return false;
    }

    return true;
}

// Handle approval
if(isset($_POST['approve'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);

    // Get application details
    $nameField = $admissionColumns['hasFullName'] ? 'full_name' : "CONCAT(first_name, ' ', last_name)";
    $phoneField = $admissionColumns['hasMobile'] ? 'mobile' : ($admissionColumns['hasPhone'] ? 'phone' : "''");
    $app_query = "SELECT $nameField AS full_name, email, $phoneField AS mobile, parent_name, parent_email, parent_phone, username, password_hash, gender, address FROM admission_applications WHERE id = $id";
    $app_result = mysqli_query($conn, $app_query);
    $app = mysqli_fetch_assoc($app_result);

    if($app) {
        $query = "UPDATE admission_applications SET status = 'Approved' WHERE id = $id";
        if(mysqli_query($conn, $query)) {
        if(createStudentUserFromAdmission($conn, $app)) {
            // Get the student_id that was just created for fee generation
            $appPhoneField = 'phone'; // Adjust if needed
            $student_query = "SELECT s.id, s.class_id FROM students s 
                             LEFT JOIN admission_applications aa ON aa." . $appPhoneField . " = s.phone 
                             WHERE aa.id = $id LIMIT 1";
            $student_result = mysqli_query($conn, $student_query);
            
            if($student_result && mysqli_num_rows($student_result) > 0) {
                $student = mysqli_fetch_assoc($student_result);
                // Auto-generate monthly fees for the newly admitted student
                autoGenerateMonthlyFeesForStudent($conn, $student['id'], $student['class_id']);
            }
            $student_message = "Dear " . $app['full_name'] . ",\n\nYour admission application has been approved. Welcome to CoachingPro!\n\nThank you.\nCoachingPro Admin";
            sendSMS($app['mobile'], $student_message);

            // Send approval SMS to parent
            $parent_message = "Dear " . $app['parent_name'] . ",\n\nWe are pleased to inform you that your ward's admission application has been approved.\n\nThank you.\nCoachingPro Admin";
            sendSMS($app['parent_phone'], $parent_message);

            // Send approval email to student
            $approval_subject = 'Admission Application Approved';
            $approval_body_student = "Dear " . $app['full_name'] . ",\n\nYour admission application has been approved. Your login username and password will be assigned by the administration. Please contact the office to receive your credentials.\n\nThank you.\nCoachingPro Administration";
            sendEmail($app['email'], $approval_subject, $approval_body_student);

            // Send approval email to parent
            $approval_body_parent = "Dear " . $app['parent_name'] . ",\n\nWe are pleased to inform you that your ward's admission application has been approved. Login credentials will be provided by the administration. Please contact the office for next steps.\n\nThank you.\nCoachingPro Administration";
            sendEmail($app['parent_email'], $approval_subject, $approval_body_parent);

            $_SESSION['success'] = "Application approved successfully. Notifications sent to student and parent.";
        } else {
            $_SESSION['error'] = "Failed to approve application. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Application not found.";
    }
    }
}

// Handle rejection
if(isset($_POST['reject'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Get application details
    $nameField = $admissionColumns['hasFullName'] ? 'full_name' : "CONCAT(first_name, ' ', last_name)";
    $phoneField = $admissionColumns['hasMobile'] ? 'mobile' : ($admissionColumns['hasPhone'] ? 'phone' : "''");
    $app_query = "SELECT $nameField AS full_name, email, $phoneField AS mobile, parent_name, parent_email, parent_phone FROM admission_applications WHERE id = $id";
    $app_result = mysqli_query($conn, $app_query);
    $app = mysqli_fetch_assoc($app_result);

    if($app) {
        // Update status
        $query = "UPDATE admission_applications SET status = 'Rejected' WHERE id = $id";
        if(mysqli_query($conn, $query)) {
            // Send rejection SMS to student
            $student_message = "Dear " . $app['full_name'] . ",\n\nWe regret to inform you that your admission application has been rejected. Please contact us for more information.\n\nCoachingPro Admin";
            sendSMS($app['mobile'], $student_message);
            
            // Send rejection SMS to parent
            $parent_message = "Dear " . $app['parent_name'] . ",\n\nWe regret to inform you that the admission application has been rejected. Please contact us for more information.\n\nCoachingPro Admin";
            sendSMS($app['parent_phone'], $parent_message);

            // Send rejection email to student
            $rejection_subject = 'Admission Application Update';
            $rejection_body_student = "Dear " . $app['full_name'] . ",\n\nWe regret to inform you that your admission application was not approved.\n\nFor more details, please contact the administration.\n\nThank you.\nCoachingPro Administration";
            sendEmail($app['email'], $rejection_subject, $rejection_body_student);

            // Send rejection email to parent
            $rejection_body_parent = "Dear " . $app['parent_name'] . ",\n\nWe regret to inform you that your ward's admission application was not approved.\n\nFor more details, please contact the administration.\n\nThank you.\nCoachingPro Administration";
            sendEmail($app['parent_email'], $rejection_subject, $rejection_body_parent);
            
            $_SESSION['success'] = "Application rejected! Notifications sent to student and parent.";
        }
    }
}

// Handle edit application
if(isset($_POST['edit_admission'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
    $parent_email = mysqli_real_escape_string($conn, $_POST['parent_email']);
    $parent_phone = mysqli_real_escape_string($conn, $_POST['parent_phone']);
    $program = mysqli_real_escape_string($conn, $_POST['program']);
    $group = mysqli_real_escape_string($conn, $_POST['group']);
    $monthly_fee = floatval($_POST['monthly_fee']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id']);
    $sender_number = mysqli_real_escape_string($conn, $_POST['sender_number']);

    $setClauses = [];
    if($admissionColumns['hasFullName']) {
        $setClauses[] = "full_name = '$full_name'";
    } elseif($admissionColumns['hasFirstName'] && $admissionColumns['hasLastName']) {
        $nameParts = explode(' ', $full_name);
        $firstNameValue = mysqli_real_escape_string($conn, array_shift($nameParts));
        $lastNameValue = mysqli_real_escape_string($conn, trim(implode(' ', $nameParts)));
        $setClauses[] = "first_name = '$firstNameValue'";
        $setClauses[] = "last_name = '$lastNameValue'";
    }

    if($admissionColumns['hasMobile']) {
        $setClauses[] = "mobile = '$mobile'";
    } elseif($admissionColumns['hasPhone']) {
        $setClauses[] = "phone = '$mobile'";
    }

    $setClauses[] = "gender = '$gender'";
    $setClauses[] = "email = '$email'";
    $setClauses[] = "address = '$address'";
    $setClauses[] = "parent_name = '$parent_name'";
    $setClauses[] = "parent_email = '$parent_email'";
    $setClauses[] = "parent_phone = '$parent_phone'";
    $setClauses[] = "program = '$program'";
    $setClauses[] = "`group` = '$group'";
    $setClauses[] = "monthly_fee = $monthly_fee";
    $setClauses[] = "payment_method = '$payment_method'";
    $setClauses[] = "transaction_id = '$transaction_id'";
    $setClauses[] = "sender_number = '$sender_number'";

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if($username !== '') {
        $username = mysqli_real_escape_string($conn, $username);
        $setClauses[] = "username = '$username'";
    }

    if($password !== '') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $password_hash = mysqli_real_escape_string($conn, $password_hash);
        $setClauses[] = "password_hash = '$password_hash'";
    }

    $update_query = "UPDATE admission_applications SET " . implode(", ", $setClauses) . " WHERE id = $id";

    if(mysqli_query($conn, $update_query)) {
        $nameField = $admissionColumns['hasFullName'] ? 'full_name' : "CONCAT(first_name, ' ', last_name)";
        $phoneField = $admissionColumns['hasMobile'] ? 'mobile' : ($admissionColumns['hasPhone'] ? 'phone' : "''");
        $app_query = "SELECT $nameField AS full_name, email, $phoneField AS mobile, parent_name, parent_email, parent_phone, username, password_hash, gender, address, status, program, `group` FROM admission_applications WHERE id = $id";
        $app_row = mysqli_fetch_assoc(mysqli_query($conn, $app_query));
        if($app_row && $app_row['status'] === 'Approved' && !empty($app_row['username']) && !empty($app_row['password_hash'])) {
            $success = createStudentUserFromAdmission($conn, $app_row);
            if(!$success) {
                $_SESSION['error'] = "Application updated but failed to create student record.";
            }
        }
        $_SESSION['success'] = "Application updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update application. Please try again.";
    }
}

// Delete application
if(isset($_POST['delete'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $query = "DELETE FROM admission_applications WHERE id = $id";
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Application deleted successfully!";
    }
}

// Get filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$nameSelect = $admissionColumns['hasFullName']
    ? 'full_name'
    : ($admissionColumns['hasFirstName'] && $admissionColumns['hasLastName'] ? "CONCAT(first_name, ' ', last_name) AS full_name" : "'' AS full_name");
$phoneSelect = $admissionColumns['hasMobile']
    ? 'mobile AS phone, mobile AS mobile'
    : ($admissionColumns['hasPhone'] ? 'phone AS phone, phone AS mobile' : "'' AS phone, '' AS mobile");

// Build query
$where = "1=1";
if($filter_status != 'all') {
    $where .= " AND status = '$filter_status'";
}
if($search) {
    $nameSearchField = $admissionColumns['hasFullName']
        ? 'full_name'
        : ($admissionColumns['hasFirstName'] && $admissionColumns['hasLastName'] ? "CONCAT(first_name, ' ', last_name)" : "''");
    $phoneSearchField = $admissionColumns['hasMobile'] ? 'mobile' : ($admissionColumns['hasPhone'] ? 'phone' : "''");

    $where .= " AND ((" . $nameSearchField . " LIKE '%$search%') OR email LIKE '%$search%' OR " . $phoneSearchField . " LIKE '%$search%' OR parent_name LIKE '%$search%' OR `group` LIKE '%$search%')";
}

$query = "SELECT *,
                 $nameSelect,
                 $phoneSelect,
                 CASE
                     WHEN TRIM(COALESCE(program, '')) <> '' THEN program
                     ELSE 'Unknown'
                 END AS program,
                 `group` AS `group`,
                 CONCAT(
                     CASE
                         WHEN TRIM(COALESCE(program, '')) <> '' THEN program
                         ELSE 'Unknown'
                     END,
                     CASE WHEN TRIM(COALESCE(`group`, '')) <> '' THEN CONCAT(' - ', `group`) ELSE '' END
                 ) AS program_with_group,
                 COALESCE(monthly_fee, 0) AS monthly_fee,
                 transaction_id,
                 sender_number
          FROM admission_applications
          WHERE $where
          ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$applications = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get statistics
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM admission_applications WHERE status = 'Pending'"))['total'];
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM admission_applications WHERE status = 'Approved'"))['total'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM admission_applications WHERE status = 'Rejected'"))['total'];
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM admission_applications"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Applications - Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fc;
        }

        .wrapper {
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            min-height: 100vh;
            color: white;
            position: fixed;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #64b5f6;
        }

        .sidebar-menu i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.total .icon {
            background: #e3f2fd;
            color: #1976d2;
        }

        .stat-card.pending .icon {
            background: #fff3e0;
            color: #f57c00;
        }

        .stat-card.approved .icon {
            background: #e8f5e9;
            color: #388e3c;
        }

        .stat-card.rejected .icon {
            background: #ffebee;
            color: #d32f2f;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .filter-section .row {
            align-items: center;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #eee;
        }

        .table tbody tr:hover {
            background: #f9f9f9;
        }

        .badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .badge.pending {
            background: #fff3e0;
            color: #e65100;
        }

        .badge.approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge.rejected {
            background: #ffebee;
            color: #c62828;
        }

        .btn-group-sm {
            gap: 5px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .table {
                font-size: 13px;
            }

            .table th,
            .table td {
                padding: 10px !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Coaching<span style="color: #64b5f6;">Pro</span></h2>
                <small>Admin Panel</small>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="student-management.php"><i class="fas fa-users"></i>Student Management</a></li>
                <li><a href="teacher-management.php"><i class="fas fa-chalkboard-user"></i>Teacher Management</a></li>
                <li><a href="admission-management.php" class="active"><i class="fas fa-file-alt"></i>Admissions</a></li>
                <li><a href="attendance.php"><i class="fas fa-clipboard-list"></i>Attendance</a></li>
                <li><a href="result-system.php"><i class="fas fa-chart-bar"></i>Result System</a></li>
                <li><a href="fees-management.php"><i class="fas fa-money-bill"></i>Fees Management</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-file-alt me-2"></i>Admission Applications</h1>
                <p class="text-muted">Manage student admission applications</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="icon"><i class="fas fa-list"></i></div>
                    <div class="value"><?php echo $total; ?></div>
                    <div class="label">Total Applications</div>
                </div>
                <div class="stat-card pending">
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="value"><?php echo $pending; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-card approved">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="value"><?php echo $approved; ?></div>
                    <div class="label">Approved</div>
                </div>
                <div class="stat-card rejected">
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                    <div class="value"><?php echo $rejected; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="GET" class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, email, phone..." 
                                   value="<?php echo $search; ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" onchange="window.location='?status=' + this.value + '&search=<?php echo $search; ?>'">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Applications Table -->
           
            <div class="table-container">
                <?php if(!empty($applications)): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Program</th>
                                <th>Group</th>
                                <th>Monthly Fee</th>
                                <th>Transaction ID</th>
                                <th>Parent Name</th>
                                <th>Parent Email</th>
                                <th>Txn Sender</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($applications as $index => $app): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($app['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($app['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                                    <td><?php echo htmlspecialchars($app['program_with_group']); ?></td>
                                    <td><?php echo htmlspecialchars($app['group']); ?></td>
                                    <td>৳<?php echo number_format((float)($app['monthly_fee'] ?? 0), 2); ?></td>
                                    <td><?php echo htmlspecialchars($app['transaction_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['parent_name']); ?></td>
                                    <td><small><?php echo htmlspecialchars($app['parent_email']); ?></small></td>
                                    <td><?php echo htmlspecialchars($app['sender_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($app['status']); ?>">
                                            <?php echo $app['status']; ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo date('d M Y', strtotime($app['created_at'])); ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $app['id']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $app['id']; ?>" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if(strtolower($app['status']) === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                    <button type="submit" name="approve" class="btn btn-success" 
                                                            title="Approve" onclick="return confirm('Approve this application?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                    <button type="submit" name="reject" class="btn btn-warning" 
                                                            title="Reject" onclick="return confirm('Reject this application?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger" 
                                                        title="Delete" onclick="return confirm('Delete this application?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View Modal -->
                                <div class="modal fade" id="viewModal<?php echo $app['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Application Details - <?php echo htmlspecialchars($app['full_name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <h6 class="text-muted">Student Information</h6>
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($app['full_name']); ?></p>
                                                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($app['gender']); ?></p>
                                                        <p><strong>Mobile:</strong> <?php echo htmlspecialchars($app['mobile']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>
                                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($app['address']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="text-muted">Parent Information</h6>
                                                        <p><strong>Parent Name:</strong> <?php echo htmlspecialchars($app['parent_name']); ?></p>
                                                        <p><strong>Parent Email:</strong> <?php echo htmlspecialchars($app['parent_email']); ?></p>
                                                        <p><strong>Parent Phone:</strong> <?php echo htmlspecialchars($app['parent_phone']); ?></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6 class="text-muted">Program Details</h6>
                                                        <p><strong>Program:</strong> <?php echo htmlspecialchars($app['program_with_group']); ?></p>
                                                        <p><strong>Group:</strong> <?php echo htmlspecialchars($app['group']); ?></p>
                                                        <p><strong>Monthly Fee:</strong> ৳<?php echo number_format((float)($app['monthly_fee'] ?? 0), 2); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="text-muted">Payment Details</h6>
                                                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($app['payment_method']); ?></p>
                                                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($app['transaction_id']); ?></p>
                                                        <p><strong>Sender Number:</strong> <?php echo htmlspecialchars($app['sender_number'] ?? 'N/A'); ?></p>
                                                        <p><strong>Application Fee:</strong> ৳<?php echo number_format($app['application_fee'], 2); ?></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge <?php echo strtolower($app['status']); ?>">
                                                        <?php echo $app['status']; ?>
                                                    </span>
                                                </p>
                                                <p><strong>Applied Date:</strong> <?php echo date('d M Y H:i', strtotime($app['created_at'])); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $app['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Application - <?php echo htmlspecialchars($app['full_name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="edit_admission" value="1">
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Full Name</label>
                                                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($app['full_name']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Gender</label>
                                                            <select class="form-select" name="gender" required>
                                                                <option value="Male" <?php echo $app['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                                <option value="Female" <?php echo $app['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                                <option value="Other" <?php echo $app['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Mobile</label>
                                                            <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($app['mobile']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($app['email']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Address</label>
                                                        <textarea class="form-control" name="address" rows="2" required><?php echo htmlspecialchars($app['address']); ?></textarea>
                                                    </div>
                                                    <hr>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Parent Name</label>
                                                            <input type="text" class="form-control" name="parent_name" value="<?php echo htmlspecialchars($app['parent_name']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Parent Email</label>
                                                            <input type="email" class="form-control" name="parent_email" value="<?php echo htmlspecialchars($app['parent_email']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Parent Phone</label>
                                                        <input type="text" class="form-control" name="parent_phone" value="<?php echo htmlspecialchars($app['parent_phone']); ?>" required>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Username</label>
                                                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($app['username'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Password</label>
                                                            <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password">
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Program</label>
                                                            <input type="text" class="form-control" name="program" value="<?php echo htmlspecialchars($app['program']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Group</label>
                                                            <input type="text" class="form-control" name="group" value="<?php echo htmlspecialchars($app['group']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Monthly Fee</label>
                                                            <input type="number" step="0.01" class="form-control" name="monthly_fee" value="<?php echo htmlspecialchars($app['monthly_fee']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Payment Method</label>
                                                            <input type="text" class="form-control" name="payment_method" value="<?php echo htmlspecialchars($app['payment_method']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Transaction ID</label>
                                                            <input type="text" class="form-control" name="transaction_id" value="<?php echo htmlspecialchars($app['transaction_id']); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Sender Number</label>
                                                            <input type="text" class="form-control" name="sender_number" value="<?php echo htmlspecialchars($app['sender_number']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No Applications Found</h5>
                        <p>There are no admission applications to display.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
