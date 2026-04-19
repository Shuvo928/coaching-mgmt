<?php
require_once 'includes/db.php';

function safeQuery($conn, $sql, $label) {
    if (!mysqli_query($conn, $sql)) {
        echo "ERROR: {$label}: " . mysqli_error($conn) . PHP_EOL;
        return false;
    }
    echo "OK: {$label}." . PHP_EOL;
    return true;
}

echo "Starting parent table migration..." . PHP_EOL;

$createParents = "CREATE TABLE IF NOT EXISTS parents (
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

if (!safeQuery($conn, $createParents, 'Create parents table')) {
    exit(1);
}

$alterStudents = "ALTER TABLE students ADD COLUMN IF NOT EXISTS parent_id INT NULL";
if (!safeQuery($conn, $alterStudents, 'Add students.parent_id')) {
    exit(1);
}

// Migrate parent rows from admission_applications.
$insertParents = "INSERT INTO parents (parent_name, parent_email, parent_phone, username, password_hash, status)
SELECT
  TRIM(COALESCE(parent_name, '')) AS parent_name,
  TRIM(COALESCE(parent_email, '')) AS parent_email,
  TRIM(COALESCE(parent_phone, '')) AS parent_phone,
  TRIM(COALESCE(username, '')) AS username,
  TRIM(COALESCE(password_hash, '')) AS password_hash,
  IF(COALESCE(username, '') != '', 'Active', 'Inactive') AS status
FROM admission_applications
WHERE COALESCE(parent_email, '') != '' OR COALESCE(parent_phone, '') != ''
GROUP BY parent_email, parent_phone";
if (!safeQuery($conn, $insertParents, 'Insert parent records from admission_applications')) {
    exit(1);
}

// Link students to parent records by matching student phone and parent contact information.
$updateParentLinks = "UPDATE students s
JOIN admission_applications a ON s.phone = COALESCE(a.mobile, a.phone)
JOIN parents p ON (
    (COALESCE(p.parent_email, '') != '' AND p.parent_email = a.parent_email)
    OR
    (COALESCE(p.parent_phone, '') != '' AND p.parent_phone = a.parent_phone)
)
SET s.parent_id = p.id";
if (!safeQuery($conn, $updateParentLinks, 'Link students to parents')) {
    exit(1);
}

// Report current totals.
$result = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM parents');
$parentsCount = mysqli_fetch_assoc($result)['count'] ?? 0;
$result = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM students WHERE parent_id IS NOT NULL');
$linkedCount = mysqli_fetch_assoc($result)['count'] ?? 0;

echo "Migration complete." . PHP_EOL;
echo "Parent records: {$parentsCount}" . PHP_EOL;
echo "Students linked to parents: {$linkedCount}" . PHP_EOL;
