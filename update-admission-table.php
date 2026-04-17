<?php
// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'coaching_db');

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

// Array of columns to add
$columns = [
    "parent_name VARCHAR(100) NOT NULL DEFAULT 'Not Provided'",
    "parent_email VARCHAR(100) NOT NULL DEFAULT 'not@provided.com'",
    "parent_phone VARCHAR(15) NOT NULL DEFAULT '0000000000'",
    "sender_number VARCHAR(20) NOT NULL DEFAULT '0000000000'",
    "monthly_fee DECIMAL(10,2) DEFAULT 0",
    "fee_recorded TINYINT(1) DEFAULT 0"
];

$results = [];
$errors = [];

foreach ($columns as $column_def) {
    // Extract column name
    $col_name = explode(' ', $column_def)[0];
    
    // Check if column already exists
    $check_sql = "SHOW COLUMNS FROM admission_applications LIKE '$col_name'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE admission_applications ADD COLUMN $column_def";
        
        if (mysqli_query($conn, $sql)) {
            $results[] = "✅ Column '$col_name' added successfully";
        } else {
            $errors[] = "❌ Error adding '$col_name': " . mysqli_error($conn);
        }
    } else {
        $results[] = "⚠️ Column '$col_name' already exists";
    }
}

mysqli_close($conn);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .success {
            background: #d4edda;
            border-left: 5px solid #28a745;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #856404;
        }
        .button-group {
            text-align: center;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .table-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .summary {
            background: #ecfeff;
            border-left: 5px solid #06b6d4;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Setup Status</h1>
        
        <div class="summary">
            <strong>Table Updated:</strong> admission_applications
            <br><strong>Purpose:</strong> Adding parent/guardian information columns
        </div>

        <?php if (!empty($results)): ?>
            <h2 style="color: #28a745; font-size: 20px;">Results:</h2>
            <?php foreach ($results as $result): ?>
                <div class="success">
                    <?php echo $result; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h2 style="color: #dc3545; font-size: 20px;">Errors:</h2>
            <?php foreach ($errors as $error): ?>
                <div class="error">
                    <?php echo $error; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="table-info">
            <strong>New Columns Added:</strong>
            <br>• parent_name (VARCHAR 100)
            <br>• parent_email (VARCHAR 100)
            <br>• parent_phone (VARCHAR 15)
            <br>• monthly_fee (DECIMAL 10,2)
        </div>

        <div class="button-group">
            <a href="admission.php" class="btn btn-primary">Go to Admission Form</a>
        </div>
    </div>
</body>
</html>
