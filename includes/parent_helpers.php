<?php

function parentTableExists(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'parents'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensureParentsTableExists(mysqli $conn): bool {
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

function getParentByUsername(mysqli $conn, string $username): ?array {
    if (!parentTableExists($conn)) {
        return null;
    }

    $username = mysqli_real_escape_string($conn, $username);
    $query = "SELECT * FROM parents WHERE username = '$username' AND status = 'Active' LIMIT 1";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getParentById(mysqli $conn, int $parent_id): ?array {
    if (!parentTableExists($conn)) {
        return null;
    }

    $parent_id = (int) $parent_id;
    $result = mysqli_query($conn, "SELECT * FROM parents WHERE id = $parent_id LIMIT 1");
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getParentStudentRows(mysqli $conn, int $parent_id): array {
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

function getStudentRowsByMobile(mysqli $conn, string $mobile): array {
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

function getParentStudentRowsForSession(mysqli $conn, int $parent_id, string $session_student_mobile = ''): array {
    $students = getParentStudentRows($conn, $parent_id);
    if (empty($students) && !empty($session_student_mobile)) {
        $students = getStudentRowsByMobile($conn, $session_student_mobile);
    }
    return $students;
}

function getParentStudentIds(mysqli $conn, int $parent_id, string $session_student_mobile = ''): array {
    $students = getParentStudentRowsForSession($conn, $parent_id, $session_student_mobile);
    return array_map('intval', array_column($students, 'id'));
}

function getFirstParentStudent(mysqli $conn, int $parent_id, string $session_student_mobile = ''): ?array {
    $students = getParentStudentRowsForSession($conn, $parent_id, $session_student_mobile);
    return $students[0] ?? null;
}

function findParentRecord(mysqli $conn, string $parent_email, string $parent_phone): ?array {
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
//“admin set credentials korar por parent account create/update hobe”
function createOrUpdateParentRecord(mysqli $conn, string $parent_name, string $parent_email, string $parent_phone, string $username, string $password_hash, string $status = 'Active'): ?int {
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

function linkParentToStudentByPhone(mysqli $conn, int $parent_id, string $student_phone): bool {
    if (empty($student_phone)) {
        return false;
    }

    $parent_id = (int) $parent_id;
    $student_phone = mysqli_real_escape_string($conn, $student_phone);
    return mysqli_query($conn, "UPDATE students SET parent_id = $parent_id WHERE phone = '$student_phone'");
}

function ensureParentDiscontinueRequestsTableExists(mysqli $conn): bool {
    $createTableSql = "CREATE TABLE IF NOT EXISTS parent_discontinue_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        parent_name VARCHAR(100) DEFAULT NULL,
        parent_email VARCHAR(100) DEFAULT NULL,
        parent_phone VARCHAR(50) DEFAULT NULL,
        student_id INT DEFAULT NULL,
        student_name VARCHAR(150) DEFAULT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        admin_id INT DEFAULT NULL,
        decided_at DATETIME DEFAULT NULL,
        due_amount DECIMAL(10,2) DEFAULT 0,
        due_summary TEXT DEFAULT NULL,
        note TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return mysqli_query($conn, $createTableSql);
}

function getParentDiscontinueRequestByParent(mysqli $conn, int $parent_id): ?array {
    ensureParentDiscontinueRequestsTableExists($conn);
    $parent_id = (int) $parent_id;
    $query = "SELECT * FROM parent_discontinue_requests WHERE parent_id = $parent_id ORDER BY requested_at DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function getParentDiscontinueRequestById(mysqli $conn, int $request_id): ?array {
    ensureParentDiscontinueRequestsTableExists($conn);
    $request_id = (int) $request_id;
    $query = "SELECT * FROM parent_discontinue_requests WHERE id = $request_id LIMIT 1";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function createParentDiscontinueRequest(mysqli $conn, int $parent_id, string $parent_name, string $parent_email, string $parent_phone, ?int $student_id, string $student_name, float $due_amount, string $due_summary): bool {
    ensureParentDiscontinueRequestsTableExists($conn);

    $parent_id = (int) $parent_id;
    $parent_name = mysqli_real_escape_string($conn, $parent_name);
    $parent_email = mysqli_real_escape_string($conn, $parent_email);
    $parent_phone = mysqli_real_escape_string($conn, $parent_phone);
    $student_id = !empty($student_id) ? (int) $student_id : 'NULL';
    $student_name = mysqli_real_escape_string($conn, $student_name);
    $due_amount = number_format((float) $due_amount, 2, '.', '');
    $due_summary = mysqli_real_escape_string($conn, $due_summary);

    $query = "INSERT INTO parent_discontinue_requests 
        (parent_id, parent_name, parent_email, parent_phone, student_id, student_name, due_amount, due_summary)
        VALUES ($parent_id, '$parent_name', '$parent_email', '$parent_phone', $student_id, '$student_name', $due_amount, '$due_summary')";
    return mysqli_query($conn, $query);
}

function updateParentDiscontinueRequestStatus(mysqli $conn, int $request_id, string $status, ?int $admin_id = null, string $note = ''): bool {
    ensureParentDiscontinueRequestsTableExists($conn);
    $request_id = (int) $request_id;
    $status = mysqli_real_escape_string($conn, $status);
    $admin_id = !empty($admin_id) ? (int) $admin_id : 'NULL';
    $note = mysqli_real_escape_string($conn, $note);
    $decided_at = date('Y-m-d H:i:s');

    $query = "UPDATE parent_discontinue_requests SET status = '$status', admin_id = $admin_id, decided_at = '$decided_at', note = '$note' WHERE id = $request_id";
    return mysqli_query($conn, $query);
}

function getPendingParentDiscontinueRequestCount(mysqli $conn): int {
    ensureParentDiscontinueRequestsTableExists($conn);
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM parent_discontinue_requests WHERE status = 'Pending'");
    return ($result && ($row = mysqli_fetch_assoc($result))) ? (int)$row['total'] : 0;
}

function getPendingAdmissionsCount(mysqli $conn): int {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM admission_applications WHERE status = 'Pending'");
    return ($result && ($row = mysqli_fetch_assoc($result))) ? (int)$row['total'] : 0;
}

function getAllParentDiscontinueRequests(mysqli $conn): array {
    ensureParentDiscontinueRequestsTableExists($conn);
    $result = mysqli_query($conn, "SELECT * FROM parent_discontinue_requests ORDER BY requested_at DESC");
    $requests = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
    }
    return $requests;
}
