<?php

function parentTableExists($conn) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'parents'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensureParentsTableExists($conn) {
    $createTableSql = "CREATE TABLE IF NOT EXISTS parents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_name VARCHAR(100) NOT NULL,
        parent_email VARCHAR(100) DEFAULT NULL,
        parent_phone VARCHAR(50) DEFAULT NULL,
        username VARCHAR(100) DEFAULT NULL,
        password_hash VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_parent_email (parent_email),
        INDEX idx_parent_phone (parent_phone),
        INDEX idx_parent_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return mysqli_query($conn, $createTableSql);
}

function getParentByUsername($conn, $username) {
    if (!parentTableExists($conn)) {
        return null;
    }

    $username = mysqli_real_escape_string($conn, $username);
    $query = "SELECT * FROM parents WHERE username = '$username' AND status = 'Active' LIMIT 1";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getParentById($conn, $parent_id) {
    if (!parentTableExists($conn)) {
        return null;
    }

    $parent_id = (int) $parent_id;
    $result = mysqli_query($conn, "SELECT * FROM parents WHERE id = $parent_id LIMIT 1");
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getParentStudentRows($conn, $parent_id) {
    $parent_id = (int) $parent_id;
    $students = [];

    $result = mysqli_query($conn, "SELECT id, first_name, last_name, phone FROM students WHERE parent_id = $parent_id");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
    }

    return $students;
}

function getStudentRowsByMobile($conn, $mobile) {
    $students = [];
    if (empty($mobile)) {
        return $students;
    }

    $mobile = mysqli_real_escape_string($conn, $mobile);
    $result = mysqli_query($conn, "SELECT id, first_name, last_name, phone FROM students WHERE phone = '$mobile'");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
    }

    return $students;
}

function getParentStudentRowsForSession($conn, $parent_id, $session_student_mobile = '') {
    $students = getParentStudentRows($conn, $parent_id);
    if (empty($students) && !empty($session_student_mobile)) {
        $students = getStudentRowsByMobile($conn, $session_student_mobile);
    }
    return $students;
}

function getParentStudentIds($conn, $parent_id, $session_student_mobile = '') {
    $students = getParentStudentRowsForSession($conn, $parent_id, $session_student_mobile);
    return array_column($students, 'id');
}

function getFirstParentStudent($conn, $parent_id, $session_student_mobile = '') {
    $students = getParentStudentRowsForSession($conn, $parent_id, $session_student_mobile);
    return $students[0] ?? null;
}

function findParentRecord($conn, $parent_email, $parent_phone) {
    if (!parentTableExists($conn)) {
        ensureParentsTableExists($conn);
    }

    $conditions = [];
    if (!empty($parent_email)) {
        $conditions[] = "parent_email = '" . mysqli_real_escape_string($conn, $parent_email) . "'";
    }
    if (!empty($parent_phone)) {
        $conditions[] = "parent_phone = '" . mysqli_real_escape_string($conn, $parent_phone) . "'";
    }

    if (empty($conditions)) {
        return null;
    }

    $query = "SELECT * FROM parents WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function createOrUpdateParentRecord($conn, $parent_name, $parent_email, $parent_phone, $username, $password_hash, $status = 'Active') {
    ensureParentsTableExists($conn);

    $parent_name = mysqli_real_escape_string($conn, $parent_name);
    $parent_email = mysqli_real_escape_string($conn, $parent_email);
    $parent_phone = mysqli_real_escape_string($conn, $parent_phone);
    $username = mysqli_real_escape_string($conn, $username);
    $password_hash = mysqli_real_escape_string($conn, $password_hash);
    $status = mysqli_real_escape_string($conn, $status);

    $existingParent = findParentRecord($conn, $parent_email, $parent_phone);

    if ($existingParent) {
        $parent_id = (int) $existingParent['id'];
        $update = "UPDATE parents SET
            parent_name = '$parent_name',
            parent_email = '$parent_email',
            parent_phone = '$parent_phone',
            username = '$username',
            password_hash = '$password_hash',
            status = '$status',
            updated_at = NOW()
            WHERE id = $parent_id";
        mysqli_query($conn, $update);
        return $parent_id;
    }

    $insert = "INSERT INTO parents (parent_name, parent_email, parent_phone, username, password_hash, status)
               VALUES ('$parent_name', '$parent_email', '$parent_phone', '$username', '$password_hash', '$status')";
    if (mysqli_query($conn, $insert)) {
        return mysqli_insert_id($conn);
    }

    return null;
}

function linkParentToStudentByPhone($conn, $parent_id, $student_phone) {
    if (empty($student_phone)) {
        return false;
    }

    $parent_id = (int) $parent_id;
    $student_phone = mysqli_real_escape_string($conn, $student_phone);
    return mysqli_query($conn, "UPDATE students SET parent_id = $parent_id WHERE phone = '$student_phone'");
}
