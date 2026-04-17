<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['student']);

$user = getCurrentUser($conn);

$student = null;
$branch_name = 'Not assigned';
$class_time = '2:20 PM - 6:45 PM (Saturday-Thursday)';
$class_routine = [];
$results = [];
$overall_stats = [
    'avg_percentage' => null,
    'total_results' => 0
];

function getBranchName($address) {
    $address = strtolower($address);
    if (strpos($address, 'dhanmondi') !== false) {
        return 'Dhanmondi Branch';
    }
    if (strpos($address, 'mirpur') !== false) {
        return 'Mirpur Branch';
    }
    if (strpos($address, 'uttara') !== false) {
        return 'Uttara Branch';
    }
    if (strpos($address, 'banani') !== false || strpos($address, 'gulshan') !== false || strpos($address, 'baridhara') !== false) {
        return 'Banani / Gulshan Branch';
    }
    return 'Nearest Branch';
}

function getPerformanceComment($percentage) {
    if ($percentage === null) {
        return 'No result yet';
    }
    if ($percentage >= 80) {
        return 'Excellent';
    }
    if ($percentage >= 70) {
        return 'Good';
    }
    if ($percentage >= 50) {
        return 'Average';
    }
    if ($percentage >= 40) {
        return 'Needs Improvement';
    }
    return 'Poor';
}

function formatTimeRange($start, $end) {
    if (empty($start) && empty($end)) {
        return 'TBA';
    }
    if (empty($start)) {
        return date('g:i A', strtotime($end));
    }
    if (empty($end)) {
        return date('g:i A', strtotime($start));
    }
    return date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
}

if (!empty($user['id'])) {
    $student_query = "SELECT s.*, c.class_name, c.section, aa.program, aa.`group` AS group_name, aa.monthly_fee, aa.transaction_id 
                      FROM students s 
                      LEFT JOIN classes c ON s.class_id = c.id
                      LEFT JOIN admission_applications aa ON s.phone = aa.mobile 
                      WHERE s.user_id = " . intval($user['id']) . " LIMIT 1";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);

    if ($student) {
        $branch_name = getBranchName($student['address'] ?? '');

        $routine_query = "SELECT er.*, et.exam_name, sub.subject_name 
                          FROM exam_routine er 
                          LEFT JOIN exam_types et ON er.exam_type_id = et.id 
                          LEFT JOIN subjects sub ON er.subject_id = sub.id 
                          WHERE er.class_id = " . intval($student['class_id']) . " 
                          ORDER BY er.exam_date ASC LIMIT 6";
        $routine_result = mysqli_query($conn, $routine_query);
        while ($routine = mysqli_fetch_assoc($routine_result)) {
            $class_routine[] = $routine;
        }

        $results_query = "SELECT r.*, et.exam_name, sub.subject_name 
                          FROM results r 
                          LEFT JOIN exam_types et ON r.exam_type_id = et.id 
                          LEFT JOIN subjects sub ON r.subject_id = sub.id 
                          WHERE r.student_id = " . intval($student['id']) . " 
                          ORDER BY r.id DESC LIMIT 6";
        $results_result = mysqli_query($conn, $results_query);
        while ($result = mysqli_fetch_assoc($results_result)) {
            $results[] = $result;
        }

        $stats_query = "SELECT 
                            AVG(percentage) as avg_percentage, 
                            COUNT(*) as total_results 
                        FROM results 
                        WHERE student_id = " . intval($student['id']);
        $stats_result = mysqli_query($conn, $stats_query);
        $overall_stats = mysqli_fetch_assoc($stats_result);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7fc; }
        .dashboard-container { max-width: 1100px; margin: 40px auto; padding: 20px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar h1 { margin: 0; font-size: 28px; }
        .card { border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .profile-card { padding: 30px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 20px; }
        .info-list dt { font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="topbar">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($user['first_name'] ?? 'Student'); ?></h1>
                <p class="text-muted">This is your student dashboard.</p>
            </div>
            <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>

        <div class="card mb-4 p-4 text-dark" style="background: #ffffff; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
            <p class="mb-0" style="font-size: 1rem; line-height: 1.8;">
                "Every number you see here tells a story — your attendance, your results, your progress. These are not just records; they are reflections of your effort, your discipline, and your growth. Every class you attend is a step forward. Every improvement, no matter how small, is a victory."
            </p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card profile-card text-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? '', 0, 1)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-0">Username: <?php echo htmlspecialchars($user['username']); ?></p>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card p-4">
                    <h5 class="mb-3">Enrollment Details</h5>
                    <?php if($student): ?>
                        <dl class="row info-list">
                            
                            <dt class="col-sm-4">Class</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Group</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Branch</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($branch_name); ?></dd>
                            <dt class="col-sm-4">Class Time</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($class_time); ?></dd>
                            
                            
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted">Student enrollment details are not yet available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-3">
            <div class="col-lg-6">
                <div class="card p-4">
                    <h5 class="mb-3">Class Routine</h5>
                    <?php if (!empty($class_routine)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_routine as $routine_item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d M, Y', strtotime($routine_item['exam_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($routine_item['exam_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($routine_item['subject_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(formatTimeRange($routine_item['start_time'], $routine_item['end_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No class routine available for your current class yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-4">
                    <h5 class="mb-3">Recent Results</h5>
                    <?php if (!empty($results)): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary me-2">Exams</span>
                            <span class="badge bg-secondary">Total: <?php echo intval($overall_stats['total_results']); ?></span>
                            <?php if ($overall_stats['avg_percentage'] !== null): ?>
                                <span class="badge bg-success">Avg: <?php echo number_format($overall_stats['avg_percentage'], 1); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <?php $percent = $result['percentage'] !== null ? floatval($result['percentage']) : null; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['exam_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($result['subject_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($result['marks_obtained'] . '/' . $result['total_marks']); ?></td>
                                            <td><?php echo htmlspecialchars(getPerformanceComment($percent)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No exam results have been added for you yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
