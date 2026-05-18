<?php
session_start();
require_once 'includes/db.php';

// Only allow admin access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

// Set header for proper output
header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Student Groups - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7fc; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .btn-large { padding: 12px 30px; font-size: 16px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .progress-item { padding: 10px; margin: 10px 0; border-radius: 8px; background: #f8f9fa; border-left: 4px solid #007bff; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-fix"></i> Fix Student Groups</h4>
            <p class="mb-0 mt-2">Automatically assign groups to students based on their admission information</p>
        </div>
        <div class="card-body">

            <?php
            // Check if fix action is requested
            if(isset($_POST['fix_groups'])) {
                echo '<div class="alert alert-info"><i class="fas fa-cog"></i> Processing...</div>';
                
                $updated = 0;
                $errors = [];
                
                // Get students with NULL group_id
                $query = "SELECT s.id, a.`group` 
                         FROM students s
                         LEFT JOIN admission_applications a ON s.email = a.email
                         WHERE (s.group_id IS NULL OR s.group_id = 0) AND a.`group` IS NOT NULL";
                
                $result = mysqli_query($conn, $query);
                
                if($result && mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                        $student_id = $row['id'];
                        $group_name = mysqli_real_escape_string($conn, trim($row['group']));
                        
                        if(!empty($group_name)) {
                            // Find group_id from groups table
                            $group_query = mysqli_query($conn, "SELECT id FROM `groups` WHERE group_name = '$group_name' LIMIT 1");
                            
                            if($group_query && mysqli_num_rows($group_query) > 0) {
                                $group_row = mysqli_fetch_assoc($group_query);
                                $group_id = $group_row['id'];
                                
                                // Update student
                                $update_sql = "UPDATE students SET group_id = $group_id WHERE id = $student_id";
                                if(mysqli_query($conn, $update_sql)) {
                                    $updated++;
                                    echo '<div class="progress-item"><span class="success"><i class="fas fa-check-circle"></i> Student ID ' . $student_id . ' → Group: ' . htmlspecialchars($group_name) . '</span></div>';
                                } else {
                                    $errors[] = "Failed to update student $student_id";
                                }
                            } else {
                                $errors[] = "Group '$group_name' not found in groups table for student $student_id";
                            }
                        }
                    }
                    
                    echo '<div class="alert alert-success mt-4">';
                    echo '<i class="fas fa-check"></i> <strong>Update Complete!</strong><br>';
                    echo 'Updated: <span class="success"><strong>' . $updated . '</strong></span> student(s)<br>';
                    if(count($errors) > 0) {
                        echo 'Errors: <span class="error"><strong>' . count($errors) . '</strong></span><br>';
                        echo '<div class="mt-2 p-2 bg-light rounded" style="max-height: 300px; overflow-y: auto;">';
                        foreach($errors as $err) {
                            echo '<div class="error"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($err) . '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-info"><i class="fas fa-info-circle"></i> All students already have groups assigned!</div>';
                }
            }
            ?>

            <!-- Statistics -->
            <div class="row mt-4 mb-4">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>Students Without Groups</h6>
                        <p class="h4 mb-0">
                            <?php 
                            $count_query = "SELECT COUNT(*) as cnt FROM students WHERE group_id IS NULL OR group_id = 0";
                            $count_result = mysqli_fetch_assoc(mysqli_query($conn, $count_query));
                            echo $count_result['cnt'] ?? 0;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>Available Groups</h6>
                        <p class="h4 mb-0">
                            <?php 
                            $groups_count = "SELECT COUNT(*) as cnt FROM `groups`";
                            $groups_result = mysqli_fetch_assoc(mysqli_query($conn, $groups_count));
                            echo $groups_result['cnt'] ?? 0;
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Available Groups List -->
            <div class="alert alert-info">
                <h6><i class="fas fa-list"></i> Available Groups in Database:</h6>
                <div class="mt-2">
                    <?php 
                    $groups = mysqli_query($conn, "SELECT id, group_name FROM `groups` ORDER BY group_name");
                    if(mysqli_num_rows($groups) > 0) {
                        echo '<ul class="mb-0">';
                        while($g = mysqli_fetch_assoc($groups)) {
                            echo '<li>' . htmlspecialchars($g['group_name']) . ' (ID: ' . $g['id'] . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p class="warning"><i class="fas fa-exclamation"></i> <strong>No groups found!</strong> Please create groups first in the Groups management section.</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Fix Button -->
            <form method="POST">
                <button type="submit" name="fix_groups" class="btn btn-primary btn-large">
                    <i class="fas fa-magic"></i> Auto-Assign Groups Now
                </button>
                <a href="admin/student-management.php" class="btn btn-secondary btn-large">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </form>

            <!-- Instructions -->
            <div class="alert alert-warning mt-4">
                <h6><i class="fas fa-info-circle"></i> How This Works:</h6>
                <ol class="mb-0">
                    <li>This script finds all students with missing group assignments</li>
                    <li>It matches the group name from their admission record with the groups table</li>
                    <li>It automatically updates each student's group_id in the database</li>
                    <li>Groups must be created in the <strong>Groups Management</strong> section first</li>
                </ol>
            </div>

        </div>
    </div>
</div>

</body>
</html>
