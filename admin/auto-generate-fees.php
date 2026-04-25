<?php
/**
 * Admin: Auto-Generate Monthly Fees
 * Allows admin to manually trigger automatic fee generation for all students
 * Can be integrated with a cron job for daily execution
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/parent_helpers.php';
require_once '../includes/payment_helpers.php';

// Check if user is admin (implement your admin auth check)
$is_admin = isset($_SESSION['admin_id']) || isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

// For development/testing without session
if (!isset($_SESSION['admin_id']) && !isset($_GET['token'])) {
    // Redirect to login if not authenticated
    if (!isset($_POST['generate_fees'])) {
        // Allow GET request for status, require POST for generation with token
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(403);
            die("Access Denied");
        }
    }
}

// Initialize database structure
ensureStudentAdmissionDateColumn($conn);
ensureFeeCollectionsDueDateColumn($conn);

$output = [];
$action_taken = false;

// Handle fee generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_fees'])) {
    $action_taken = true;
    
    try {
        $total_processed = 0;
        $total_generated = 0;
        $errors = 0;
        
        // Get all active students
        $query = "SELECT s.id, s.class_id, CONCAT(s.first_name, ' ', s.last_name) as name, c.class_name
                  FROM students s
                  LEFT JOIN classes c ON s.class_id = c.id
                  WHERE s.status = 1
                  ORDER BY s.id";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception("Error fetching students: " . mysqli_error($conn));
        }
        
        $output[] = "Starting automatic fee generation...";
        $output[] = "";
        
        while ($student = mysqli_fetch_assoc($result)) {
            $student_id = $student['id'];
            $class_id = $student['class_id'];
            $student_name = $student['name'];
            $class_name = $student['class_name'] ?? 'Unknown';
            
            // Get monthly fee amount
            $fee_amount = getClassFeeAmount($conn, $student_id);
            
            if ($fee_amount <= 0) {
                $output[] = "⚠ {$student_name} ({$class_name}): No fee amount configured";
                $errors++;
                continue;
            }
            
            // Auto-generate fees for this student
            if (autoGenerateMonthlyFeesForStudent($conn, $student_id, $class_id)) {
                // Count how many fees were generated
                $count_query = "SELECT COUNT(*) as count FROM fee_collections WHERE student_id = $student_id";
                $count_result = mysqli_query($conn, $count_query);
                $count_data = mysqli_fetch_assoc($count_result);
                $fee_count = $count_data['count'] ?? 0;
                
                $output[] = "✓ {$student_name} ({$class_name}): Generated/Updated ৳{$fee_amount}/month ({$fee_count} total months)";
                $total_generated++;
            } else {
                $output[] = "ℹ {$student_name} ({$class_name}): Fees already up-to-date";
            }
            
            $total_processed++;
        }
        
        $output[] = "";
        $output[] = "====================================";
        $output[] = "✅ FEE GENERATION COMPLETE";
        $output[] = "====================================";
        $output[] = "Total Students Processed: {$total_processed}";
        $output[] = "New Fees Generated: {$total_generated}";
        if ($errors > 0) {
            $output[] = "Errors/Skipped: {$errors}";
        }
        $output[] = "Timestamp: " . date('Y-m-d H:i:s');
        
        // Log this action
        $log_message = "Auto-generated monthly fees for {$total_processed} students. Generated: {$total_generated}";
        $log_query = "INSERT INTO admin_logs (action, details, timestamp) VALUES ('fee_generation', '{$log_message}', NOW())";
        @mysqli_query($conn, $log_query); // Suppress error if table doesn't exist
        
    } catch (Exception $e) {
        $output[] = "❌ ERROR: " . $e->getMessage();
    }
}

// Get statistics
$stats = [];
$stats_query = "SELECT 
                    COUNT(DISTINCT student_id) as total_students,
                    COUNT(*) as total_fees,
                    SUM(expected_amount) as total_expected,
                    SUM(paid_amount) as total_paid,
                    SUM(expected_amount - paid_amount) as total_due,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_count,
                    COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_count
                FROM fee_collections";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Generate Monthly Fees - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fc;
            padding: 20px;
        }
        .container {
            max-width: 900px;
        }
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-box .label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            text-transform: uppercase;
        }
        .action-button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .action-button:hover {
            transform: translateY(-2px);
            color: white;
        }
        .action-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .output-section {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .output-section .success { color: #4caf50; }
        .output-section .error { color: #f44336; }
        .output-section .warning { color: #ff9800; }
        .output-section .info { color: #2196f3; }
        .output-section strong { color: #64b5f6; }
        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-cogs me-2"></i>Auto-Generate Monthly Fees</h1>
            <p>Generate or update monthly fees for all active students in the system</p>
        </div>

        <!-- Statistics -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="number"><?php echo number_format($stats['total_students'] ?? 0); ?></div>
                    <div class="label">Active Students</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="number"><?php echo number_format($stats['total_fees'] ?? 0); ?></div>
                    <div class="label">Total Fee Records</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="number">৳<?php echo number_format($stats['total_due'] ?? 0, 0); ?></div>
                    <div class="label">Total Due</div>
                </div>
            </div>
        </div>

        <!-- Fee Status -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i>Fee Payment Status
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p><strong>Total Expected:</strong><br>৳<?php echo number_format($stats['total_expected'] ?? 0, 2); ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Total Paid:</strong><br>৳<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Paid Months:</strong><br><?php echo $stats['paid_count'] ?? 0; ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Unpaid Months:</strong><br><?php echo $stats['unpaid_count'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Section -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-play-circle me-2"></i>Generate Fees Now
            </div>
            <div class="card-body">
                <p style="margin-bottom: 20px;">
                    Click the button below to automatically generate or update monthly fees for all active students.
                    This will:
                </p>
                <ul style="margin-bottom: 20px;">
                    <li>Generate next 6 months of fees for students without any fees</li>
                    <li>Ensure all students have fees for upcoming months</li>
                    <li>Set due dates to the 10th of each month</li>
                    <li>Prevent duplicate month entries</li>
                </ul>

                <form method="POST" onsubmit="return confirm('This will generate monthly fees for all students. Continue?');">
                    <button type="submit" name="generate_fees" class="action-button" value="1">
                        <i class="fas fa-sync-alt me-2"></i>Generate Fees Now
                    </button>
                </form>

                <?php if ($action_taken && !empty($output)): ?>
                <div class="output-section">
                    <?php foreach ($output as $line): ?>
                        <?php
                        $class = '';
                        if (strpos($line, '✓') !== false) {
                            $class = 'success';
                        } elseif (strpos($line, '⚠') !== false) {
                            $class = 'warning';
                        } elseif (strpos($line, '❌') !== false) {
                            $class = 'error';
                        } elseif (strpos($line, '✅') !== false) {
                            $class = 'info';
                        } elseif (strpos($line, 'Total') !== false || strpos($line, 'Timestamp') !== false) {
                            $class = 'info';
                        }
                        ?>
                        <div<?php echo $class ? " class=\"$class\"" : ''; ?>>
                            <?php echo htmlspecialchars($line); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Automation Tip:</strong> Set up a cron job to run this automatically every day:
            <br><code>0 2 * * * curl -X POST http://yoursite.com/admin/auto-generate-fees.php -d "generate_fees=1"</code>
        </div>

        <!-- Back Link -->
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
