<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$classGroup = isset($_GET['class_group']) ? trim($_GET['class_group']) : '';
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';

function normalizeText(string $value): string {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
}

function extractClassNameFromGroup(string $classGroup): string {
    $parts = preg_split('/\s*[–—-]\s*/u', $classGroup);
    return isset($parts[0]) ? trim($parts[0]) : trim($classGroup);
}

function findClassId(mysqli $conn, string $className): int {
    if ($className === '') {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM classes WHERE LOWER(TRIM(class_name)) = LOWER(TRIM(?)) LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $className);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $classId = 0;
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $classId = intval($row['id']);
    }
    mysqli_stmt_close($stmt);
    return $classId;
}

function buildTeacherLookupQuery(bool $useClassFilter): string {
    $classFilter = $useClassFilter ? ' AND ts.class_id = ?' : '';
    return "SELECT CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name
            FROM teacher_subjects ts
            JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
            JOIN subjects s ON ts.subject_id = s.id
            WHERE LOWER(TRIM(s.subject_name)) = LOWER(TRIM(?))" . $classFilter . "
            ORDER BY ts.id ASC
            LIMIT 1";
}

function buildTeacherLookupPartialQuery(bool $useClassFilter): string {
    $classFilter = $useClassFilter ? ' AND ts.class_id = ?' : '';
    return "SELECT CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name
            FROM teacher_subjects ts
            JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
            JOIN subjects s ON ts.subject_id = s.id
            WHERE LOWER(TRIM(s.subject_name)) LIKE LOWER(CONCAT('%', TRIM(?), '%'))" . $classFilter . "
            ORDER BY ts.id ASC
            LIMIT 1";
}

$response = ['assigned' => false, 'teacher' => ''];

if ($subject === '') {
    echo json_encode($response);
    exit;
}

$className = extractClassNameFromGroup($classGroup);
$classId = findClassId($conn, $className);

$hasClassIdColumn = false;
$columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
    $hasClassIdColumn = true;
}

$searchAttempts = [];
if ($hasClassIdColumn && $classId > 0) {
    $searchAttempts[] = ['sql' => buildTeacherLookupQuery(true), 'bindClass' => true];
    $searchAttempts[] = ['sql' => buildTeacherLookupPartialQuery(true), 'bindClass' => true];
}
$searchAttempts[] = ['sql' => buildTeacherLookupQuery(false), 'bindClass' => false];
$searchAttempts[] = ['sql' => buildTeacherLookupPartialQuery(false), 'bindClass' => false];

foreach ($searchAttempts as $attempt) {
    $stmt = mysqli_prepare($conn, $attempt['sql']);
    if (!$stmt) {
        continue;
    }

    if ($attempt['bindClass'] && $classId > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $subject, $classId);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $subject);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $teacherName = trim($row['teacher_name']);
        if ($teacherName !== '') {
            $response['assigned'] = true;
            $response['teacher'] = $teacherName;
            mysqli_stmt_close($stmt);
            break;
        }
    }
    mysqli_stmt_close($stmt);
}

echo json_encode($response);
mysqli_close($conn);
