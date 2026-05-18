<?php
session_start();
require_once 'includes/db.php';

// Simple admin check for setup purposes
$page_title = "Setup Announcements Table";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; }
        .container { margin-top: 40px; }
        .setup-card { border-radius: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card setup-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i><?= $page_title ?></h5>
            </div>
            <div class="card-body">
                <?php
                // Check if announcements table exists
                $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
                $table_exists = mysqli_num_rows($check_table) > 0;

                if ($table_exists) {
                    echo '<div class="alert alert-info">✓ Announcements table already exists.</div>';
                } else {
                    // Create announcements table
                    $create_table = "
                    CREATE TABLE announcements (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        teacher_id INT NOT NULL,
                        class_id INT NOT NULL,
                        group_id INT,
                        title VARCHAR(255) NOT NULL,
                        message LONGTEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
                        INDEX idx_class_group (class_id, group_id),
                        INDEX idx_teacher (teacher_id),
                        INDEX idx_created_at (created_at DESC)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ";
                    
                    if (mysqli_query($conn, $create_table)) {
                        echo '<div class="alert alert-success success">✓ Announcements table created successfully!</div>';
                    } else {
                        echo '<div class="alert alert-danger error">✗ Error creating table: ' . mysqli_error($conn) . '</div>';
                    }
                }

                // Check for updated_at column
                $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM announcements");
                $has_updated_at = false;
                while ($row = mysqli_fetch_assoc($check_columns)) {
                    if ($row['Field'] === 'updated_at') {
                        $has_updated_at = true;
                        break;
                    }
                }

                if (!$has_updated_at) {
                    $add_column = "ALTER TABLE announcements ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                    if (mysqli_query($conn, $add_column)) {
                        echo '<div class="alert alert-success success mt-2">✓ Added updated_at column.</div>';
                    }
                }
                ?>

                <hr>
                
                <h6 class="mt-4">Table Structure:</h6>
                <pre style="background: #f8fafc; padding: 15px; border-radius: 0.5rem; overflow-x: auto;">
id              INT (PRIMARY KEY)
teacher_id      INT (FOREIGN KEY → teachers)
class_id        INT (FOREIGN KEY → classes)
group_id        INT (FOREIGN KEY → groups) [NULLABLE]
title           VARCHAR(255)
message         LONGTEXT
created_at      TIMESTAMP
updated_at      TIMESTAMP
                </pre>

                <div class="mt-4">
                    <a href="admin/teacher-announcements.php" class="btn btn-primary rounded-pill">
                        <i class="fas fa-arrow-right me-2"></i>Go to Announcements Management
                    </a>
                    <a href="index.php" class="btn btn-secondary rounded-pill">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
