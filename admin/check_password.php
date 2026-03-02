<?php
session_start();
require_once '../includes/db.php';

echo "<h2>Password Check Utility</h2>";

// Check admin user
$query = "SELECT id, username, password, role FROM users WHERE username = 'admin'";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "<h3>Admin User Found:</h3>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Stored Password Hash: " . $user['password'] . "<br>";
    
    // Test password verification
    $test_password = "admin123";
    if(password_verify($test_password, $user['password'])) {
        echo "<span style='color:green;font-weight:bold;'>✓ Password 'admin123' VERIFIES successfully with current hash!</span><br>";
    } else {
        echo "<span style='color:red;font-weight:bold;'>✗ Password 'admin123' does NOT verify with current hash!</span><br>";
        
        // Generate new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<br>New hash for 'admin123': " . $new_hash . "<br>";
        echo "<button onclick='copyToClipboard(\"" . $new_hash . "\")'>Copy New Hash</button>";
    }
} else {
    echo "<span style='color:red;'>No admin user found with username 'admin'</span><br>";
    
    // Check all users
    echo "<h3>All Users in Database:</h3>";
    $all_users = mysqli_query($conn, "SELECT id, username, role FROM users");
    if(mysqli_num_rows($all_users) > 0) {
        while($user = mysqli_fetch_assoc($all_users)) {
            echo "ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}<br>";
        }
    } else {
        echo "No users found in database!";
    }
}

// Show password hash for reference
echo "<hr>";
echo "<h3>Password Hash for 'admin123' (use this to update):</h3>";
echo password_hash('admin123', PASSWORD_DEFAULT);
?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Hash copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>

<style>
body { font-family: Arial; padding: 20px; line-height: 1.6; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
button { background: #06B6D4; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; }
button:hover { background: #0891b2; }
</style>