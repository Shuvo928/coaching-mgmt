<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

function getRequestCurrentDueAmount($conn, $request) {
    $due_total = 0.0;

    if (!empty($request['student_id'])) {
        $student_id = (int)$request['student_id'];
        $fee_result = mysqli_query($conn, "SELECT SUM(expected_amount - paid_amount) AS due_total FROM fee_collections WHERE student_id = $student_id AND payment_status != 'paid'");
        $fee_row = mysqli_fetch_assoc($fee_result);
        $due_total += max(0, (float)($fee_row['due_total'] ?? 0));
    }

    if (!empty($request['parent_phone'])) {
        $phone = mysqli_real_escape_string($conn, $request['parent_phone']);
        $admission_result = mysqli_query($conn, "SELECT SUM(application_fee) AS due_total FROM admission_applications WHERE status = 'Approved' AND (transaction_id = '' OR transaction_id IS NULL) AND (mobile = '$phone' OR phone = '$phone')");
        $admission_row = mysqli_fetch_assoc($admission_result);
        $due_total += max(0, (float)($admission_row['due_total'] ?? 0));
    }

    return $due_total;
}

function removeParentAccount($conn, $parent_id) {
    $removed = false;
    if (parentTableExists($conn)) {
        mysqli_query($conn, "DELETE FROM parents WHERE id = $parent_id");
        if (mysqli_affected_rows($conn) > 0) {
            mysqli_query($conn, "UPDATE students SET parent_id = NULL WHERE parent_id = $parent_id");
            $removed = true;
        }
    }

    $admissionTableExists = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'admission_applications'")) > 0;
    if ($admissionTableExists) {
        mysqli_query($conn, "UPDATE admission_applications SET status = 'Deleted' WHERE id = $parent_id AND status = 'Approved'");
        if (mysqli_affected_rows($conn) > 0) {
            $removed = true;
        }
    }

    return $removed;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int) $_POST['request_id'];
    $action = $_POST['action'];
    $request = getParentDiscontinueRequestById($conn, $request_id);

    if (!$request) {
        $_SESSION['error'] = 'Discontinue request not found.';
        header('Location: parent-discontinue-requests.php');
        exit();
    }

    if ($request['status'] !== 'Pending') {
        $_SESSION['error'] = 'Only pending requests can be updated.';
        header('Location: parent-discontinue-requests.php');
        exit();
    }

    if ($action === 'approve') {
        $current_due = getRequestCurrentDueAmount($conn, $request);
        if ($current_due > 0) {
            $_SESSION['error'] = 'Cannot approve request while due payment exists: ৳' . number_format($current_due, 2) . '. Collect dues first.';
        } else {
            $removed = removeParentAccount($conn, $request['parent_id']);
            if ($removed) {
                updateParentDiscontinueRequestStatus($conn, $request_id, 'Approved', $_SESSION['user_id'], 'Admin approved and account removed.');
                $_SESSION['success'] = 'Request approved and parent account removed successfully.';
            } else {
                $_SESSION['error'] = 'Request approved, but parent account removal was not completed because no matching account record was found.';
                updateParentDiscontinueRequestStatus($conn, $request_id, 'Approved', $_SESSION['user_id'], 'Approved but no account record removed.');
            }
        }
    } elseif ($action === 'reject') {
        $note = 'Rejected by admin.';
        updateParentDiscontinueRequestStatus($conn, $request_id, 'Rejected', $_SESSION['user_id'], $note);
        $_SESSION['success'] = 'Discontinue request has been rejected.';
    }

    header('Location: parent-discontinue-requests.php');
    exit();
}

$requests = getAllParentDiscontinueRequests($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discontinue Requests - CoachingPro Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7fc; }
        .container { max-width: 1200px; margin: 40px auto; }
        .card { border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .badge-status-pending { background: #f59e0b; }
        .badge-status-approved { background: #10b981; }
        .badge-status-rejected { background: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Parent Discontinue Requests</h2>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card p-4 mb-4">
            <h5>Instructions</h5>
            <p class="mb-0">Review pending parent account discontinuation requests. Approve only after dues are cleared. Reject if the request cannot be processed.</p>
        </div>

        <div class="card p-4">
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Parent</th>
                            <th>Student</th>
                            <th>Requested At</th>
                            <th>Due Amount</th>
                            <th>Due Summary</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No discontinue requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <?php $current_due = getRequestCurrentDueAmount($conn, $request); ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['parent_name'] ?: 'Unknown'); ?></strong><br>
                                        <?php echo htmlspecialchars($request['parent_email']); ?><br>
                                        <?php echo htmlspecialchars($request['parent_phone']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['student_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($request['requested_at']); ?></td>
                                    <td>৳<?php echo number_format($current_due, 2); ?></td>
                                    <td><?php echo htmlspecialchars($request['due_summary']); ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <span class="badge badge-status-pending text-white">Pending</span>
                                        <?php elseif ($request['status'] === 'Approved'): ?>
                                            <span class="badge badge-status-approved text-white">Approved</span>
                                        <?php else: ?>
                                            <span class="badge badge-status-rejected text-white">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($request['note'])); ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <form method="POST" style="display:inline-block; margin-right: 4px;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" <?php echo $current_due > 0 ? 'disabled title="Clear dues before approving"' : ''; ?>>Approve</button>
                                            </form>
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
