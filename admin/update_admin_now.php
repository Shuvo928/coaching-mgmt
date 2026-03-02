<?php
// Direct database update without requiring db.php
$host = 'localhost';
$dbname = 'coaching_db';
$username = 'root';
$password = '';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "<h2>🔧 Updating Admin Password</h2>";

// Generate a fresh hash for admin123
$new_hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "New hash generated: <code>" . $new_hash . "</code><br><br>";

// Update the admin user
$query = "UPDATE users SET password = '$new_hash' WHERE username = 'admin'";

if(mysqli_query($conn, $query)) {
    echo "<p style='color:green;font-weight:bold;'>✅ Admin password updated successfully!</p>";
    
    // Verify the update
    $check = mysqli_query($conn, "SELECT password FROM users WHERE username = 'admin'");
    $user = mysqli_fetch_assoc($check);
    
    echo "<h3>Verification:</h3>";
    echo "New stored hash: " . $user['password'] . "<br>";
    
    if(password_verify('admin123', $user['password'])) {
        echo "<p style='color:green;font-weight:bold;'>✅ Password verification successful! You can now login with admin/admin123</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ Password verification failed! Something went wrong.</p>";
    }
} else {
    echo "<p style='color:red;font-weight:bold;'>❌ Error updating password: " . mysqli_error($conn) . "</p>";
}

mysqli_close($conn);
?>

<br>
<a href="login.php" style="display:inline-block;padding:10px 20px;background:#06B6D4;color:white;text-decoration:none;border-radius:5px;">Go to Login Page</a>