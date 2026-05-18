<?php
ob_start();
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
ob_end_clean();

try {
    // Authentication check - student or parent session support
    $session_is_student = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
    $session_is_parent = (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'parent') || isset($_SESSION['parent_id']);

    if (!$session_is_student && !$session_is_parent) {
        throw new Exception('Unauthorized access');
    }

    function resolveStudentClassGroup($conn, $student_row) {
        $class_id = intval($student_row['class_id'] ?? 0);
        $group_id = intval($student_row['group_id'] ?? 0);
        $phone = mysqli_real_escape_string($conn, $student_row['phone'] ?? '');

        if ($class_id <= 0) {
            return [0, 0];
        }

        if ($group_id > 0) {
            return [$class_id, $group_id];
        }

        // Attempt to resolve group via admission application if student.group_id is empty
        if (!empty($phone)) {
            $admission_phone_field = 'phone';
            $admission_check = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
            if ($admission_check && mysqli_num_rows($admission_check) > 0) {
                $admission_phone_field = "COALESCE(NULLIF(mobile, ''), phone)";
            }

            $admission_query = "SELECT `group` AS admission_group FROM admission_applications WHERE $admission_phone_field = '$phone' LIMIT 1";
            $admission_result = mysqli_query($conn, $admission_query);
            if ($admission_result && mysqli_num_rows($admission_result) > 0) {
                $admission_row = mysqli_fetch_assoc($admission_result);
                $admission_group = trim($admission_row['admission_group'] ?? '');
                if (!empty($admission_group)) {
                    $safe_group = mysqli_real_escape_string($conn, $admission_group);
                    $group_lookup = mysqli_query($conn, "SELECT id FROM `groups` WHERE LOWER(group_name) = LOWER('$safe_group') LIMIT 1");
                    if ($group_lookup && mysqli_num_rows($group_lookup) > 0) {
                        $group_row = mysqli_fetch_assoc($group_lookup);
                        $group_id = intval($group_row['id']);
                    }
                }
            }
        }

        return [$class_id, $group_id];
    }

    if ($session_is_student) {
        $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        $student_query = "SELECT * FROM students WHERE user_id = '$user_id' LIMIT 1";
        $student_result = mysqli_query($conn, $student_query);
        if (!$student_result || mysqli_num_rows($student_result) == 0) {
            throw new Exception('Student record not found');
        }
        $student = mysqli_fetch_assoc($student_result);
        list($class_id, $group_id) = resolveStudentClassGroup($conn, $student);
    } else {
        $parent_id = isset($_SESSION['parent_id']) ? intval($_SESSION['parent_id']) : null;
        if (!$parent_id && isset($_SESSION['user_id'])) {
            $parent_id = intval($_SESSION['user_id']);
        }

        $student = null;
        if (isset($_POST['child_user_id']) && !empty($_POST['child_user_id'])) {
            $child_user_id = mysqli_real_escape_string($conn, $_POST['child_user_id']);
            $student_query = "SELECT * FROM students WHERE user_id = '$child_user_id' LIMIT 1";
            $student_result = mysqli_query($conn, $student_query);
            if ($student_result && mysqli_num_rows($student_result) > 0) {
                $student = mysqli_fetch_assoc($student_result);
            }
        }

        if (!$student) {
            if (!$parent_id) {
                throw new Exception('Parent session not found');
            }
            $student_query = "SELECT * FROM students WHERE parent_id = $parent_id LIMIT 1";
            $student_result = mysqli_query($conn, $student_query);
            if (!$student_result || mysqli_num_rows($student_result) == 0) {
                throw new Exception('Associated child record not found');
            }
            $student = mysqli_fetch_assoc($student_result);
        }

        list($class_id, $group_id) = resolveStudentClassGroup($conn, $student);
    }

    if ($class_id <= 0) {
        throw new Exception('Student class information is incomplete');
    }

    $group_condition = $group_id > 0 ? "AND (a.group_id IS NULL OR a.group_id = $group_id)" : "AND a.group_id IS NULL";
    
    $announcements_query = "SELECT a.id, a.title, a.message, a.created_at, a.updated_at,
                                   c.class_name,
                                   COALESCE(g.group_name, 'All Groups') AS group_name,
                                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
                            FROM announcements a
                            JOIN classes c ON a.class_id = c.id
                            LEFT JOIN `groups` g ON a.group_id = g.id
                            JOIN teachers t ON a.teacher_id = t.id
                            WHERE a.class_id = $class_id $group_condition
                            ORDER BY a.created_at DESC
                            LIMIT 50";
    
    $announcements_result = mysqli_query($conn, $announcements_query);
    if (!$announcements_result) {
        throw new Exception('Database query error: ' . mysqli_error($conn));
    }

    $announcements = [];
    while ($row = mysqli_fetch_assoc($announcements_result)) {
        $announcements[] = $row;
    }

    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
        'count' => count($announcements)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>
