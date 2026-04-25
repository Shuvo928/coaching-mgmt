<?php
require_once '../includes/db.php';
require_once 'send-sms.php'; // Reuse SMS function

if(isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] === 'true';
    
    // Get student details and due amount with parent email
    $query = "SELECT s.id, s.first_name, s.last_name, s.phone, 
                     SUM(fc.expected_amount - fc.paid_amount) as total_due,
                     aa.parent_email, aa.parent_name
              FROM students s
              LEFT JOIN fee_collections fc ON s.id = fc.student_id AND fc.payment_status != 'paid'
              LEFT JOIN admission_applications aa ON s.phone = aa.mobile OR s.phone = aa.phone
              WHERE s.id = $student_id
              GROUP BY s.id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    
    if($student) {
        if($send_email) {
            // Send email to parent
            if(!empty($student['parent_email'])) {
                $parent_name = !empty($student['parent_name']) ? $student['parent_name'] : 'Dear Parent';
                $student_name = $student['first_name'] . ' ' . $student['last_name'];
                $due_amount = !empty($student['total_due']) ? number_format($student['total_due'], 2) : '0.00';
                
                $subject = "Fee Payment Reminder - " . $student_name;
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                        .header { background: #667eea; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                        .content { padding: 20px; }
                        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; }
                        .amount { font-size: 24px; font-weight: bold; color: #f44336; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Fee Payment Reminder</h2>
                        </div>
                        <div class='content'>
                            <p>Dear $parent_name,</p>
                            <p>This is a friendly reminder that your child <strong>$student_name</strong> has pending fees.</p>
                            <p><strong>Outstanding Amount:</strong> <span class='amount'>৳$due_amount</span></p>
                            <p>Please make the payment at your earliest convenience to avoid any inconvenience.</p>
                            <p><strong>Payment Methods:</strong></p>
                            <ul>
                                <li>bKash</li>
                                <li>Nagad</li>
                                <li>Rocket</li>
                                <li>Cash at Office</li>
                            </ul>
                            <p>For any questions, please contact us.</p>
                            <p>Thank you,<br><strong>Coaching Management</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                // Use PHP mail function
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
                $headers .= "From: coaching@center.com" . "\r\n";
                
                $email_sent = mail($student['parent_email'], $subject, $message, $headers);
                
                echo json_encode([
                    'success' => $email_sent,
                    'message' => $email_sent ? 'Email sent successfully to ' . $student['parent_email'] : 'Failed to send email'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Parent email not found for this student'
                ]);
            }
        } else {
            // Send SMS (original functionality)
            if(!empty($student['phone'])) {
                $message = "Dear {$student['first_name']}, this is a reminder that your fee of ৳{$student['total_due']} is due. Please pay soon. - Coaching Center";
                
                // Use SMS function from send-sms.php
                $sms_sent = sendViaAPI($student['phone'], $message);
                
                echo json_encode(['success' => $sms_sent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Student phone number not found']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}
?>