<?php
/**
 * Diagnostic: Check Monthly Fees Setup Status
 */

require_once 'includes/db.php';

// Check what phone column name exists in admission_applications
$admissionPhoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
$admissionHasMobile = ($admissionPhoneColumn && mysqli_num_rows($admissionPhoneColumn) > 0);
$admissionPhoneField = $admissionHasMobile ? 'mobile' : 'phone';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Monthly Fees Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #2196f3; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #2196f3; }
        .error-box { background: #ffebee; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #f44336; }
        .success-box { background: #e8f5e9; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #4caf50; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Monthly Fees Diagnostic Report</h2>

        <?php
        // 1. Check students and admissions connection
        echo "<h3>1. Students & Admissions Data</h3>";
        $students_query = "SELECT 
                            s.id, s.student_id, s.first_name, s.last_name, s.phone,
                            aa.id as admission_id, aa.$admissionPhoneField, aa.monthly_fee, aa.status
                          FROM students s
                          LEFT JOIN admission_applications aa ON s.phone = aa.$admissionPhoneField
                          LIMIT 5";
        $students_result = mysqli_query($conn, $students_query);
        
        if($students_result && mysqli_num_rows($students_result) > 0) {
            echo "<table>";
            echo "<tr><th>Student ID</th><th>Name</th><th>Phone</th><th>Admission Mobile</th><th>Monthly Fee</th><th>Status</th></tr>";
            while($row = mysqli_fetch_assoc($students_result)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td>" . htmlspecialchars($row[$admissionPhoneField] ?? 'NULL') . "</td>";
                echo "<td>৳" . ($row['monthly_fee'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // 2. Check monthly_fees table
        echo "<h3>2. Monthly Fees Records</h3>";
        $monthly_query = "SELECT 
                            mf.id, mf.student_id, mf.month, mf.year, mf.tuition_fee, 
                            mf.due_amount, mf.status, s.student_id as sid, s.first_name
                          FROM monthly_fees mf
                          LEFT JOIN students s ON mf.student_id = s.id
                          ORDER BY mf.student_id DESC
                          LIMIT 10";
        $monthly_result = mysqli_query($conn, $monthly_query);
        
        if($monthly_result && mysqli_num_rows($monthly_result) > 0) {
            echo "<table>";
            echo "<tr><th>Student ID</th><th>Name</th><th>Month/Year</th><th>Tuition Fee</th><th>Due Amount</th><th>Status</th></tr>";
            while($row = mysqli_fetch_assoc($monthly_result)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['sid']) . "</td>";
                echo "<td>" . htmlspecialchars($row['first_name'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['month'] . " " . $row['year']) . "</td>";
                echo "<td>৳" . number_format($row['tuition_fee'], 2) . "</td>";
                echo "<td>৳" . number_format($row['due_amount'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // 3. Check admission_applications data
        echo "<h3>3. Admission Applications (Sample)</h3>";
        $admit_query = "SELECT 
                        id, full_name, mobile, monthly_fee, status, created_at
                      FROM admission_applications
                      LIMIT 5";
        $admit_result = mysqli_query($conn, $admit_query);
        
        if($admit_result && mysqli_num_rows($admit_result) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Mobile</th><th>Monthly Fee</th><th>Status</th></tr>";
            while($row = mysqli_fetch_assoc($admit_result)) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['mobile']) . "</td>";
                echo "<td>৳" . number_format($row['monthly_fee'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // 4. Analysis
        echo "<h3>Analysis & Findings</h3>";
        
        // Count matched records
        $match_query = "SELECT COUNT(*) as cnt FROM students s 
                       INNER JOIN admission_applications aa ON s.phone = aa.$admissionPhoneField
                       WHERE aa.status = 'Approved'";
        $match_result = mysqli_query($conn, $match_query);
        $match_data = mysqli_fetch_assoc($match_result);
        $matched = $match_data['cnt'];

        if($matched > 0) {
            echo "<div class='success-box'>
                    ✓ Found <strong>$matched</strong> students linked to approved admissions
                  </div>";
        } else {
            echo "<div class='error-box'>
                    ✗ <strong>No students found</strong> linked to admissions!<br>
                    This means students.phone ≠ admission_applications.mobile<br>
                    Need to check phone number format/linking.
                  </div>";
        }

        // Check monthly_fees with admission fees populated
        $populated_query = "SELECT COUNT(*) as cnt FROM monthly_fees WHERE tuition_fee > 0";
        $populated_result = mysqli_query($conn, $populated_query);
        $populated_data = mysqli_fetch_assoc($populated_result);

        echo "<div class='info-box'>
                Monthly fees records with amounts > 0: <strong>" . $populated_data['cnt'] . "</strong>
              </div>";
        ?>
    </div>
</body>
</html>
