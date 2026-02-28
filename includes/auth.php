<?php
// Authentication check middleware
function checkAuth() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: ../admin/login.php");
        exit();
    }
}

// Role-based access control
function checkRole($allowed_roles = []) {
    if(!isset($_SESSION['role'])) {
        header("Location: ../admin/login.php");
        exit();
    }
    
    if(!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../index.php");
        exit();
    }
}

// Get current user details
function getCurrentUser($conn) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT u.*, 
              CASE 
                WHEN u.role = 'teacher' THEN t.first_name
                WHEN u.role = 'student' THEN s.first_name
              END as first_name,
              CASE 
                WHEN u.role = 'teacher' THEN t.last_name
                WHEN u.role = 'student' THEN s.last_name
              END as last_name,
              CASE 
                WHEN u.role = 'teacher' THEN t.photo
                WHEN u.role = 'student' THEN s.photo
              END as photo
              FROM users u 
              LEFT JOIN teachers t ON u.id = t.user_id
              LEFT JOIN students s ON u.id = s.user_id
              WHERE u.id = $user_id";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}
?>