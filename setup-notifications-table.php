<?php
session_start();
require_once 'includes/db.php';

$page_title = "Setup Notifications Table";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .container { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; }
        h1 { color: #667eea; margin-bottom: 30px; font-weight: 700; }
        .alert { border-radius: 10px; border: none; }
        .btn { border-radius: 8px; padding: 10px 25px; font-weight: 600; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell me-2"></i>Setup Notifications System</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
            // Check if notifications table already exists
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
            if ($check_table && mysqli_num_rows($check_table) > 0) {
                echo '<div class="alert alert-info">✓ Notifications table already exists.</div>';
            } else {
                // Create notifications table
                $create_sql = "
                    CREATE TABLE notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        recipient_id INT NOT NULL,
                        recipient_role VARCHAR(50) NOT NULL COMMENT 'admin, teacher, student, parent',
                        type VARCHAR(100) NOT NULL COMMENT 'approval, routine, result, fees, etc',
                        title VARCHAR(255) NOT NULL,
                        message TEXT,
                        related_id INT COMMENT 'ID of related entity (class_id, result_id, fee_id, etc)',
                        action_url VARCHAR(255) COMMENT 'Link to action page',
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP NULL COMMENT 'Auto-delete after this date',
                        INDEX idx_recipient (recipient_id, recipient_role),
                        INDEX idx_created (created_at DESC),
                        INDEX idx_expires (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";

                if (mysqli_query($conn, $create_sql)) {
                    echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>✓ Notifications table created successfully!</div>';
                } else {
                    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>✗ Error creating table: ' . mysqli_error($conn) . '</div>';
                }
            }

            // Verify table structure
            $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM notifications");
            if ($check_columns && mysqli_num_rows($check_columns) > 0) {
                $columns = [];
                while ($col = mysqli_fetch_assoc($check_columns)) {
                    $columns[] = $col['Field'];
                }
                
                if (!in_array('is_read', $columns)) {
                    $add_column = "ALTER TABLE notifications ADD COLUMN is_read BOOLEAN DEFAULT FALSE";
                    mysqli_query($conn, $add_column);
                }
                if (!in_array('expires_at', $columns)) {
                    $add_column = "ALTER TABLE notifications ADD COLUMN expires_at TIMESTAMP NULL";
                    mysqli_query($conn, $add_column);
                }
            }

            echo '<div class="alert alert-success mt-4"><i class="fas fa-info-circle me-2"></i><strong>Next Steps:</strong> Notifications system is ready. Bell icons will appear on dashboards.</div>';
            echo '<a href="admin/dashboard.php" class="btn btn-primary rounded-pill mt-3"><i class="fas fa-arrow-right me-2"></i>Go to Admin Dashboard</a>';
        } else {
            ?>
            <p class="text-muted mb-4">This setup creates the notifications table and enables automatic notifications for:</p>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-check me-2"></i>Features:</strong>
                <ul class="mb-0 mt-2">
                    <li>✓ Automatic notifications for admissions, results, fees, routines</li>
                    <li>✓ Role-based filtering (admin, teacher, student, parent)</li>
                    <li>✓ Unread/read status tracking</li>
                    <li>✓ Auto-delete after 7 days</li>
                    <li>✓ Bell icon with unread count badge</li>
                </ul>
            </div>

            <div class="card bg-light p-3 mb-4">
                <strong>Database Schema:</strong>
                <pre style="font-size: 0.85rem; margin-top: 10px;"><code>TABLE: notifications
├── id (PK)
├── recipient_id (user/admin id)
├── recipient_role (admin/teacher/student/parent)
├── type (approval/routine/result/fees)
├── title (notification subject)
├── message (notification body)
├── related_id (entity id)
├── action_url (link to action)
├── is_read (status)
├── created_at
└── expires_at (auto-delete)</code></pre>
            </div>

            <form method="POST">
                <button type="submit" name="create_table" class="btn btn-success btn-lg w-100">
                    <i class="fas fa-database me-2"></i>Create Notifications Table
                </button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>
