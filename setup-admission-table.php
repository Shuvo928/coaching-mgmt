<?php
// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'coaching_db');

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

// SQL to create/update admission_applications table
$sql = "CREATE TABLE IF NOT EXISTS admission_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT,
    program VARCHAR(50) NOT NULL,
    `group` VARCHAR(50),
    parent_name VARCHAR(100) NOT NULL,
    parent_email VARCHAR(100) NOT NULL,
    parent_phone VARCHAR(15) NOT NULL,
    username VARCHAR(100) NULL,
    password_hash VARCHAR(255) NULL,
    monthly_fee DECIMAL(10,2),
    transaction_id VARCHAR(100),
    payment_method VARCHAR(50),
    sender_number VARCHAR(20),
    application_fee DECIMAL(10,2),
    fee_recorded TINYINT(1) DEFAULT 0,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px;'>";
    echo "<h2 style='color: #155724;'>✅ Success!</h2>";
    echo "<p>The <strong>admission_applications</strong> table has been created/updated successfully.</p>";
    echo "<p>The following columns are now available:</p>";
    echo "<ul>";
    echo "<li>Student Info: full_name, gender, mobile, email, address</li>";
    echo "<li>Program: program, group, monthly_fee</li>";
    echo "<li>Parent Info: parent_name, parent_email, parent_phone</li>";
    echo "<li>Payment: transaction_id, payment_method, application_fee</li>";
    echo "<li>Status: status, created_at, updated_at</li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'><strong>You can now use the admission form!</strong></p>";
    echo "<a href='admission.php' style='background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>Go to Admission Form</a>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px;'>";
    echo "<h2 style='color: #721c24;'>❌ Error</h2>";
    echo "<p>Error creating table: " . mysqli_error($conn) . "</p>";
    echo "</div>";
}

mysqli_close($conn);
?>
