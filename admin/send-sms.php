<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'count' => 0];

if(isset($_POST['type'])) {
    $type = $_POST['type'];
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    // ==============================================
    // 🔧 SMS API CONFIGURATION - UPDATE THESE VALUES
    // ==============================================
    
    // Choose your SMS provider:
    // 1. BulkSMSBD  - https://bulksmsbd.net
    // 2. GreenWeb   - https://greenweb.com.bd
    // 3. SSL Wireless - https://sslwireless.com
    // 4. ADN Telecom - https://adntelecom.com
    
    $api_config = [
        // Your SMS Provider API Key
        'api_key' => 'YOUR_API_KEY_HERE',  // Replace with actual API key
        
        // Sender ID / Masking Name (usually 11 characters)
        'sender_id' => '8801234567890',     // Replace with your sender ID
        
        // API URL of your SMS provider
        'api_url' => 'http://bulksmsbd.net/api/smsapi',  // Replace with provider URL
        
        // Additional provider-specific parameters
        'provider' => 'bulksmsbd',           // Options: bulksmsbd, greenweb, ssl, adn
        'api_type' => 'json'                  // Response format: json or xml
    ];
    
    // ==============================================
    // Function to send SMS via different providers
    // ==============================================
    function sendViaAPI($phone, $message) {
        global $api_config, $conn;
        
        $status = 'Failed';
        $api_response = '';
        
        // Format phone number (remove any non-numeric characters)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure phone number has country code (Bangladesh: 88)
        if(strlen($phone) == 10) {
            $phone = '88' . $phone;
        }
        
        // Prepare API request based on provider
        switch($api_config['provider']) {
            case 'bulksmsbd':
                // BulkSMSBD.net API format
                $data = [
                    'api_key' => $api_config['api_key'],
                    'senderid' => $api_config['sender_id'],
                    'number' => $phone,
                    'message' => $message
                ];
                break;
                
            case 'greenweb':
                // GreenWeb API format
                $data = [
                    'token' => $api_config['api_key'],
                    'to' => $phone,
                    'message' => $message
                ];
                break;
                
            case 'ssl':
                // SSL Wireless API format
                $data = [
                    'api_token' => $api_config['api_key'],
                    'sid' => $api_config['sender_id'],
                    'msisdn' => $phone,
                    'sms' => $message,
                    'csms_id' => uniqid()
                ];
                break;
                
            default:
                // Default format (adjust as needed)
                $data = [
                    'api_key' => $api_config['api_key'],
                    'sender' => $api_config['sender_id'],
                    'number' => $phone,
                    'message' => $message
                ];
        }
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_config['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds timeout
        
        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Check if SMS was sent successfully
        if($http_code == 200 && !empty($api_response)) {
            // Parse response based on provider
            $response_data = json_decode($api_response, true);
            
            // Check success based on provider response
            if($api_config['provider'] == 'bulksmsbd') {
                // BulkSMSBD returns success code
                $status = (isset($response_data['response_code']) && $response_data['response_code'] == 202) ? 'Sent' : 'Failed';
            } elseif($api_config['provider'] == 'greenweb') {
                // GreenWeb returns success status
                $status = (strpos($api_response, 'SUCCESS') !== false) ? 'Sent' : 'Failed';
            } else {
                // Default: consider 200 as success
                $status = 'Sent';
            }
        } else {
            // Log cURL error if any
            if(!empty($curl_error)) {
                error_log("SMS cURL Error: " . $curl_error);
            }
        }
        
        // Log SMS attempt in database
        $safe_message = mysqli_real_escape_string($conn, $message);
        $log_query = "INSERT INTO sms_logs (mobile_number, message, type, status, api_response) 
                      VALUES ('$phone', '$safe_message', 'Bulk', '$status', " . 
                      ($api_response ? "'" . mysqli_real_escape_string($conn, substr($api_response, 0, 255)) . "'" : "NULL") . ")";
        mysqli_query($conn, $log_query);
        
        return ($status == 'Sent');
    }
    
    // ==============================================
    // Handle different SMS types
    // ==============================================
    switch($type) {
        case 'individual':
            $student_id = intval($_POST['student_id']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            
            // Get student details
            $student_query = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id");
            if(mysqli_num_rows($student_query) > 0) {
                $student = mysqli_fetch_assoc($student_query);
                
                // Personalize message
                $personalized_message = str_replace('[NAME]', $student['first_name'], $message);
                $personalized_message = str_replace('[STUDENT_ID]', $student['student_id'], $personalized_message);
                
                // Send SMS
                if(sendViaAPI($phone, $personalized_message)) {
                    $response['success'] = true;
                    $response['message'] = 'SMS sent successfully';
                } else {
                    $response['message'] = 'Failed to send SMS. Please check API configuration.';
                }
            } else {
                $response['message'] = 'Student not found';
            }
            break;
            
        case 'class':
            $class_id = intval($_POST['class_id']);
            
            // Get all students in class
            $students = mysqli_query($conn, "SELECT * FROM students WHERE class_id = $class_id AND status = 1");
            $sent_count = 0;
            $total_students = 0;
            
            while($student = mysqli_fetch_assoc($students)) {
                $total_students++;
                if(!empty($student['phone'])) {
                    $personalized_message = str_replace('[NAME]', $student['first_name'], $message);
                    $personalized_message = str_replace('[CLASS]', 'Class ' . $class_id, $personalized_message);
                    
                    if(sendViaAPI($student['phone'], $personalized_message)) {
                        $sent_count++;
                    }
                    
                    // Small delay to avoid API rate limiting
                    usleep(500000); // 0.5 seconds delay
                }
            }
            
            $response['success'] = true;
            $response['count'] = $sent_count;
            $response['message'] = "SMS sent to $sent_count out of $total_students students";
            break;
            
        case 'bulk':
            $groups = $_POST['groups']; // This is an array
            $sent_count = 0;
            $processed_count = 0;
            
            foreach($groups as $group) {
                switch($group) {
                    case 'all_students':
                        $students = mysqli_query($conn, "SELECT * FROM students WHERE status = 1");
                        while($student = mysqli_fetch_assoc($students)) {
                            $processed_count++;
                            if(!empty($student['phone'])) {
                                $personalized_message = str_replace('[NAME]', $student['first_name'], $message);
                                if(sendViaAPI($student['phone'], $personalized_message)) {
                                    $sent_count++;
                                }
                                usleep(300000); // 0.3 seconds delay
                            }
                        }
                        break;
                        
                    case 'all_teachers':
                        $teachers = mysqli_query($conn, "SELECT * FROM teachers WHERE status = 1");
                        while($teacher = mysqli_fetch_assoc($teachers)) {
                            $processed_count++;
                            if(!empty($teacher['phone'])) {
                                $personalized_message = str_replace('[NAME]', $teacher['first_name'], $message);
                                if(sendViaAPI($teacher['phone'], $personalized_message)) {
                                    $sent_count++;
                                }
                                usleep(300000);
                            }
                        }
                        break;
                        
                    case 'fee_pending':
                        $students = mysqli_query($conn, "SELECT DISTINCT s.* FROM students s 
                                                          LEFT JOIN fee_collections f ON s.id = f.student_id 
                                                          WHERE (f.status IS NULL OR f.status != 'Paid') 
                                                          AND s.status = 1");
                        while($student = mysqli_fetch_assoc($students)) {
                            $processed_count++;
                            if(!empty($student['phone'])) {
                                $personalized_message = str_replace('[NAME]', $student['first_name'], $message);
                                if(sendViaAPI($student['phone'], $personalized_message)) {
                                    $sent_count++;
                                }
                                usleep(300000);
                            }
                        }
                        break;
                        
                    case 'exam_soon':
                        $next_week = date('Y-m-d', strtotime('+7 days'));
                        $students = mysqli_query($conn, "SELECT DISTINCT s.* FROM students s
                                                          JOIN exam_routine er ON s.class_id = er.class_id
                                                          WHERE er.exam_date BETWEEN CURDATE() AND '$next_week'
                                                          AND s.status = 1");
                        while($student = mysqli_fetch_assoc($students)) {
                            $processed_count++;
                            if(!empty($student['phone'])) {
                                $personalized_message = str_replace('[NAME]', $student['first_name'], $message);
                                $personalized_message = str_replace('[EXAM_DATE]', $er['exam_date'], $personalized_message);
                                if(sendViaAPI($student['phone'], $personalized_message)) {
                                    $sent_count++;
                                }
                                usleep(300000);
                            }
                        }
                        break;
                }
            }
            
            $response['success'] = true;
            $response['count'] = $sent_count;
            $response['message'] = "Bulk SMS sent to $sent_count out of $processed_count recipients";
            break;
            
        case 'custom':
            $numbers = explode(',', $_POST['numbers']);
            $sent_count = 0;
            $total_numbers = count($numbers);
            
            foreach($numbers as $number) {
                $number = trim($number);
                if(!empty($number) && strlen($number) >= 10) {
                    if(sendViaAPI($number, $message)) {
                        $sent_count++;
                    }
                    usleep(300000);
                }
            }
            
            $response['success'] = true;
            $response['count'] = $sent_count;
            $response['message'] = "SMS sent to $sent_count out of $total_numbers numbers";
            break;
    }
}

echo json_encode($response);
?>