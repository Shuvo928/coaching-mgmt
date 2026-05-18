<?php
session_start();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/auth.php';

checkAuth();
checkRole(['admin']);

function normalizeTeacherName(string $name): string {
    return trim(preg_replace('/\s+/', ' ', $name));
}

function findRegisteredTeacherName(mysqli $conn, string $teacher): string {
    $teacher = normalizeTeacherName($teacher);
    if ($teacher === '') {
        return '';
    }

    $queries = [
        // exact normalized full name, collapsing repeated spaces inside names
        "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1 AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(TRIM(first_name), ' ', TRIM(last_name)), '    ', ' '), '   ', ' '), '  ', ' '), '  ', ' ')) = LOWER(?) LIMIT 1",
        // exact first name or last name match for single-token input
        "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1 AND (LOWER(TRIM(first_name)) = LOWER(?) OR LOWER(TRIM(last_name)) = LOWER(?)) LIMIT 1",
        // partial match anywhere within the name
        "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1 AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(TRIM(first_name), ' ', TRIM(last_name)), '    ', ' '), '   ', ' '), '  ', ' '), '  ', ' ')) LIKE LOWER(CONCAT('%', ?, '%')) LIMIT 1"
    ];

    $fullName = '';
    foreach ($queries as $index => $sql) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }

        if ($index === 0) {
            mysqli_stmt_bind_param($stmt, 's', $teacher);
        } elseif ($index === 1) {
            if (strpos($teacher, ' ') !== false) {
                mysqli_stmt_close($stmt);
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'ss', $teacher, $teacher);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $teacher);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $fullName = $row['full_name'];
        }
        mysqli_stmt_close($stmt);

        if ($fullName !== '') {
            break;
        }
    }

    return normalizeTeacherName($fullName);
}

function isRegisteredTeacher(mysqli $conn, string $teacher): bool {
    return findRegisteredTeacherName($conn, $teacher) !== '';
}

function extractClassNameFromGroup(string $classGroup): string {
    $parts = preg_split('/\s*[–—-]\s*/u', $classGroup);
    return isset($parts[0]) ? trim($parts[0]) : trim($classGroup);
}

function findTeacherForSubjectAndClassGroup(mysqli $conn, string $classGroup, string $subject): string {
    $subject = trim(preg_replace('/\s+/', ' ', $subject));
    if ($subject === '') {
        return '';
    }

    $className = extractClassNameFromGroup($classGroup);
    $classId = 0;
    if ($className !== '') {
        $stmt = mysqli_prepare($conn, "SELECT id FROM classes WHERE LOWER(TRIM(class_name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $className);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $classId = intval($row['id']);
            }
            mysqli_stmt_close($stmt);
        }
    }

    $hasClassIdColumn = false;
    $columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
    if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
        $hasClassIdColumn = true;
    }

    $teacherName = '';
    $searchQueries = [
        "SELECT CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name
            FROM teacher_subjects ts
            JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
            JOIN subjects s ON ts.subject_id = s.id
            WHERE LOWER(TRIM(s.subject_name)) = LOWER(TRIM(?))%s
            ORDER BY ts.id ASC
            LIMIT 1",
        "SELECT CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name
            FROM teacher_subjects ts
            JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
            JOIN subjects s ON ts.subject_id = s.id
            WHERE LOWER(TRIM(s.subject_name)) LIKE LOWER(CONCAT('%%', TRIM(?), '%%'))%s
            ORDER BY ts.id ASC
            LIMIT 1"
    ];

    $classFilter = '';
    if ($hasClassIdColumn && $classId > 0) {
        $classFilter = " AND ts.class_id = $classId";
    }

    foreach ($searchQueries as $queryTemplate) {
        $sql = sprintf($queryTemplate, $classFilter);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, 's', $subject);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $teacherName = trim($row['teacher_name']);
        }
        mysqli_stmt_close($stmt);

        if ($teacherName !== '') {
            break;
        }
    }

    if ($teacherName === '' && $classFilter !== '') {
        // fallback to search without class filter when class-specific assignment is not found
        foreach ($searchQueries as $queryTemplate) {
            $sql = sprintf($queryTemplate, '');
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 's', $subject);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $teacherName = trim($row['teacher_name']);
            }
            mysqli_stmt_close($stmt);
            if ($teacherName !== '') {
                break;
            }
        }
    }

    return $teacherName;
}

function inferStreamFromQualification(string $qualification): string {
    $qualification = mb_strtolower(trim($qualification));
    if ($qualification === '') {
        return '';
    }

    $streamMappings = [
        'Science' => ['chemistry', 'physics', 'biology', 'mathematics', 'math', 'biochemistry', 'geology', 'statistics', 'microbiology', 'zoology', 'botany'],
        'Commerce' => ['accounting', 'business', 'finance', 'banking', 'bcom', 'bba', 'mba', 'commerce'],
        'Humanities' => ['humanities', 'arts', 'history', 'geography', 'political science', 'political', 'sociology', 'economics', 'english', 'psychology', 'literature', 'philosophy', 'languages'],
    ];

    foreach ($streamMappings as $stream => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($qualification, $keyword) !== false) {
                return $stream;
            }
        }
    }

    return '';
}

function normalizeStream(string $stream): string {
    return trim(ucwords(strtolower($stream)));
}

function getTeacherStreams(mysqli $conn, string $teacher): array {
    $teacher = normalizeTeacherName($teacher);
    if ($teacher === '') {
        return [];
    }

    $streams = [];
    $subjectStreamSql = "SELECT DISTINCT COALESCE(NULLIF(s.stream, ''), '') AS stream
                         FROM teacher_subjects ts
                         JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
                         JOIN subjects s ON ts.subject_id = s.id
                         WHERE LOWER(CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name))) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $subjectStreamSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $teacher);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $stream = normalizeStream($row['stream'] ?? '');
            if ($stream !== '') {
                $streams[] = $stream;
            }
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($streams)) {
        $qualificationSql = "SELECT qualification FROM teachers WHERE status = 1 AND LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = LOWER(?) LIMIT 1";
        $stmt = mysqli_prepare($conn, $qualificationSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $teacher);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $qualificationStream = inferStreamFromQualification($row['qualification'] ?? '');
                if ($qualificationStream !== '') {
                    $streams[] = $qualificationStream;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    return array_values(array_unique($streams));
}

function findSubjectStream(mysqli $conn, string $subject): string {
    $subject = trim(preg_replace('/\s+/', ' ', $subject));
    if ($subject === '') {
        return '';
    }

    $queryTemplates = [
        "SELECT stream FROM subjects WHERE LOWER(TRIM(subject_name)) = LOWER(TRIM(?)) LIMIT 1",
        "SELECT stream FROM subjects WHERE LOWER(TRIM(subject_name)) LIKE LOWER(CONCAT('%', TRIM(?), '%')) LIMIT 1"
    ];

    foreach ($queryTemplates as $sql) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 's', $subject);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $stream = normalizeStream($row['stream'] ?? '');
            mysqli_stmt_close($stmt);
            return $stream;
        }
        mysqli_stmt_close($stmt);
    }

    return '';
}

function isSubjectAllowedForTeacher(mysqli $conn, string $teacher, string $subject): ?string {
    $allowedStreams = getTeacherStreams($conn, $teacher);
    if (empty($allowedStreams)) {
        return null;
    }

    $subjectStream = findSubjectStream($conn, $subject);
    if ($subjectStream === '') {
        return null;
    }

    if (!in_array($subjectStream, $allowedStreams, true)) {
        return "Teacher '" . htmlspecialchars($teacher, ENT_QUOTES, 'UTF-8') . "' is qualified for " . implode(', ', $allowedStreams) . " subjects, but '$subject' belongs to the $subjectStream stream.";
    }

    return null;
}

// routine-management.php
// Static Timetable Management System for Coaching Center
// Admin can edit routine manually with conflict warnings and teacher replacement.

// Use the existing connection
// Note: Using mysqli for consistency with the rest of the system
    
    // Create routine table
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS `routine` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `class_group` VARCHAR(100) NOT NULL,
            `day` VARCHAR(20) NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `subject` VARCHAR(100) NOT NULL,
            `teacher` VARCHAR(100) NOT NULL,
            `room` VARCHAR(10) NOT NULL,
            UNIQUE KEY `unique_slot` (`class_group`, `day`, `start_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    mysqli_query($conn, $create_table_sql);
    
    
    // Handle edit submission
    $editError = '';
    $editSuccess = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
        $id = $_POST['edit_id'];
        $class_group = $_POST['class_group'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $subject = $_POST['subject'];
        $teacherRaw = trim($_POST['teacher'] ?? '');
        $teacher = findRegisteredTeacherName($conn, $teacherRaw);
        if ($teacher === '' && trim($subject) !== '' && trim($class_group) !== '') {
            $teacher = findTeacherForSubjectAndClassGroup($conn, $class_group, $subject);
        }
        $room = $_POST['room'];
        
        // Validate teacher -> subject stream eligibility
        $conflicts = [];
        $streamError = isSubjectAllowedForTeacher($conn, $teacher, $subject);
        if ($streamError !== null) {
            $conflicts[] = $streamError;
        }
        
        // Conflict check: teacher already teaching elsewhere with overlapping time on the same day
        $check_sql = "SELECT * FROM `routine` WHERE id != ? AND day = ? AND teacher = ? AND NOT (end_time <= ? OR start_time >= ?)";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, 'issss', $id, $day, $teacher, $start_time, $end_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $conflicts[] = "Teacher '$teacher' already has another class that overlaps this time on the same day.";
        }
        mysqli_stmt_close($stmt);
        
        // Conflict check: room already booked with overlapping time on the same day
        $room_check_sql = "SELECT * FROM `routine` WHERE id != ? AND day = ? AND room = ? AND NOT (end_time <= ? OR start_time >= ?)";
        $stmt = mysqli_prepare($conn, $room_check_sql);
        mysqli_stmt_bind_param($stmt, 'issss', $id, $day, $room, $start_time, $end_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $conflicts[] = "Room '$room' is already booked during an overlapping time period on this day.";
        }
        mysqli_stmt_close($stmt);

        // Conflict check: same class_group, day and start_time must remain unique
        $slot_check_sql = "SELECT * FROM `routine` WHERE id != ? AND class_group = ? AND day = ? AND start_time = ?";
        $stmt = mysqli_prepare($conn, $slot_check_sql);
        mysqli_stmt_bind_param($stmt, 'isss', $id, $class_group, $day, $start_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $conflicts[] = "Another routine entry already exists for this class/group on $day at " . date('g:i A', strtotime($start_time)) . ".";
        }
        mysqli_stmt_close($stmt);
        
        if (!empty($conflicts)) {
            $editError = "Conflict(s) detected!<br>" . implode("<br>", $conflicts) . "<br>Please resolve manually.";
        } else {
            $update_sql = "UPDATE `routine` SET class_group=?, day=?, start_time=?, end_time=?, subject=?, teacher=?, room=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, 'sssssssi', $class_group, $day, $start_time, $end_time, $subject, $teacher, $room, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['highlighted_routine_id'] = $id;
            $_SESSION['routine_save_success'] = "Entry updated successfully.";
            header("Location: routine-management.php");
            exit;
        }
    }

    $addError = '';
    $addSuccess = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_routine'])) {
        $class_id = intval($_POST['class_id'] ?? 0);
        $group_id = intval($_POST['group_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $new_subject_name = trim($_POST['new_subject_name'] ?? '');
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        $day = mysqli_real_escape_string($conn, trim($_POST['day'] ?? ''));
        $start_time = mysqli_real_escape_string($conn, trim($_POST['start_time'] ?? ''));
        $end_time = mysqli_real_escape_string($conn, trim($_POST['end_time'] ?? ''));
        $room = mysqli_real_escape_string($conn, trim($_POST['room'] ?? ''));

        $class_name = '';
        $group_name = '';
        $subject_name = '';
        $teacher_name = '';

        if ($class_id > 0) {
            $classResult = mysqli_query($conn, "SELECT class_name FROM classes WHERE id = $class_id LIMIT 1");
            if ($classResult && ($classRow = mysqli_fetch_assoc($classResult))) {
                $class_name = trim($classRow['class_name']);
            }
        }
        if ($group_id > 0) {
            $groupResult = mysqli_query($conn, "SELECT group_name FROM groups WHERE id = $group_id LIMIT 1");
            if ($groupResult && ($groupRow = mysqli_fetch_assoc($groupResult))) {
                $group_name = trim($groupRow['group_name']);
            }
        }
        if ($teacher_id > 0) {
            $teacherResult = mysqli_query($conn, "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE id = $teacher_id AND status = 1 LIMIT 1");
            if ($teacherResult && ($teacherRow = mysqli_fetch_assoc($teacherResult))) {
                $teacher_name = trim($teacherRow['full_name']);
            }
        }

        if ($new_subject_name !== '') {
            $escapedNewSubject = mysqli_real_escape_string($conn, $new_subject_name);
            $subjectQuery = "SELECT id, subject_name FROM subjects WHERE LOWER(TRIM(subject_name)) = LOWER('$escapedNewSubject')";
            if ($class_id > 0) {
                $subjectQuery .= " AND class_id = $class_id";
            }
            $subjectQuery .= " LIMIT 1";
            $existingSubject = mysqli_query($conn, $subjectQuery);
            if ($existingSubject && ($subjectRow = mysqli_fetch_assoc($existingSubject))) {
                $subject_id = intval($subjectRow['id']);
                $subject_name = trim($subjectRow['subject_name']);
            } else {
                $subjectStream = mysqli_real_escape_string($conn, $group_name);
                if ($class_id > 0) {
                    $insertSubjectSql = "INSERT INTO subjects (subject_name, class_id, stream) VALUES (?, ?, ?)";
                    $subjectStmt = mysqli_prepare($conn, $insertSubjectSql);
                    if ($subjectStmt) {
                        mysqli_stmt_bind_param($subjectStmt, 'sis', $new_subject_name, $class_id, $subjectStream);
                        mysqli_stmt_execute($subjectStmt);
                        $subject_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($subjectStmt);
                    }
                } else {
                    $insertSubjectSql = "INSERT INTO subjects (subject_name, stream) VALUES (?, ?)";
                    $subjectStmt = mysqli_prepare($conn, $insertSubjectSql);
                    if ($subjectStmt) {
                        mysqli_stmt_bind_param($subjectStmt, 'ss', $new_subject_name, $subjectStream);
                        mysqli_stmt_execute($subjectStmt);
                        $subject_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($subjectStmt);
                    }
                }
                if ($subject_id > 0) {
                    $subject_name = $new_subject_name;
                }
            }

            if ($subject_id > 0 && $teacher_id > 0) {
                $hasTSClassId = false;
                $classColCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
                if ($classColCheck && mysqli_num_rows($classColCheck) > 0) {
                    $hasTSClassId = true;
                }
                $mappingSql = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
                if ($hasTSClassId && $class_id > 0) {
                    $mappingSql .= " AND class_id = $class_id";
                }
                $mappingResult = mysqli_query($conn, $mappingSql);
                if (!$mappingResult || mysqli_num_rows($mappingResult) === 0) {
                    if ($hasTSClassId && $class_id > 0) {
                        $insertMapping = "INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES (?, ?, ?)";
                        $mappingStmt = mysqli_prepare($conn, $insertMapping);
                        if ($mappingStmt) {
                            mysqli_stmt_bind_param($mappingStmt, 'iii', $teacher_id, $subject_id, $class_id);
                            mysqli_stmt_execute($mappingStmt);
                            mysqli_stmt_close($mappingStmt);
                        }
                    } else {
                        $insertMapping = "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)";
                        $mappingStmt = mysqli_prepare($conn, $insertMapping);
                        if ($mappingStmt) {
                            mysqli_stmt_bind_param($mappingStmt, 'ii', $teacher_id, $subject_id);
                            mysqli_stmt_execute($mappingStmt);
                            mysqli_stmt_close($mappingStmt);
                        }
                    }
                }
            }
        } elseif ($subject_id > 0) {
            $subjectResult = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id = $subject_id LIMIT 1");
            if ($subjectResult && ($subjectRow = mysqli_fetch_assoc($subjectResult))) {
                $subject_name = trim($subjectRow['subject_name']);
            }
        }

        $class_group = $class_name;
        if ($group_name !== '') {
            $class_group .= ' — ' . $group_name;
        }

        if ($class_group === '' || $subject_name === '' || $teacher_name === '' || $day === '' || $start_time === '' || $end_time === '' || $room === '') {
            $addError = 'Please fill in all add-routine fields correctly.';
        } else {
            $eligibility_query = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id LIMIT 1";
            $eligibility_result = mysqli_query($conn, $eligibility_query);
            if (!$eligibility_result || mysqli_num_rows($eligibility_result) === 0) {
                $addError = 'Error: Selected teacher is not assigned to the selected subject.';
            } else {
                $check_sql = "SELECT * FROM `routine` WHERE day = ? AND teacher = ? AND NOT (end_time <= ? OR start_time >= ?)";
                $stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($stmt, 'ssss', $day, $teacher_name, $start_time, $end_time);
                mysqli_stmt_execute($stmt);
                $conflictResult = mysqli_stmt_get_result($stmt);
                if ($conflictResult && mysqli_num_rows($conflictResult) > 0) {
                    $addError = "Conflict detected: Teacher '$teacher_name' already has another class that overlaps this time on the same day.";
                }
                mysqli_stmt_close($stmt);

                if ($addError === '') {
                    $room_check_sql = "SELECT * FROM `routine` WHERE day = ? AND room = ? AND NOT (end_time <= ? OR start_time >= ?)";
                    $stmt = mysqli_prepare($conn, $room_check_sql);
                    mysqli_stmt_bind_param($stmt, 'ssss', $day, $room, $start_time, $end_time);
                    mysqli_stmt_execute($stmt);
                    $roomConflictResult = mysqli_stmt_get_result($stmt);
                    if ($roomConflictResult && mysqli_num_rows($roomConflictResult) > 0) {
                        $addError = "Conflict detected: Room '$room' is already booked during an overlapping time period on this day.";
                    }
                    mysqli_stmt_close($stmt);
                }

                if ($addError === '') {
                    $duplicate_check = mysqli_query($conn, "SELECT id FROM `routine` WHERE class_group = '" . mysqli_real_escape_string($conn, $class_group) . "' AND day = '" . mysqli_real_escape_string($conn, $day) . "' AND start_time = '" . mysqli_real_escape_string($conn, $start_time) . "' LIMIT 1");
                    if ($duplicate_check && mysqli_num_rows($duplicate_check) > 0) {
                        $addError = 'Another routine entry already exists for this class/group at the same day and start time.';
                    }
                }

                if ($addError === '') {
                    $insert_sql = "INSERT INTO `routine` (class_group, day, start_time, end_time, subject, teacher, room) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'sssssss', $class_group, $day, $start_time, $end_time, $subject_name, $teacher_name, $room);
                        if (mysqli_stmt_execute($stmt)) {
                            $newId = mysqli_insert_id($conn);
                            $_SESSION['highlighted_routine_id'] = $newId;
                            $_SESSION['routine_save_success'] = 'Routine added successfully.';
                            mysqli_stmt_close($stmt);
                            header('Location: routine-management.php');
                            exit;
                        }
                        mysqli_stmt_close($stmt);
                    }
                    if ($addError === '') {
                        $addError = 'Error saving routine: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }

    // Preserve one-time highlight and flash after redirect
    $highlightedRowId = null;
    $keepHighlightInSession = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($addError)) {
        $keepHighlightInSession = true;
    }

    if (isset($_SESSION['highlighted_routine_id'])) {
        $highlightedRowId = intval($_SESSION['highlighted_routine_id']);
        if (!$keepHighlightInSession) {
            unset($_SESSION['highlighted_routine_id']);
        }
    }
    if (isset($_SESSION['routine_save_success'])) {
        $editSuccess = $_SESSION['routine_save_success'];
        unset($_SESSION['routine_save_success']);
    }

    // Handle Replace Teacher (quick replace)
    $replaceMsg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_teacher'])) {
        $oldTeacherRaw = $_POST['old_teacher'];
        $newTeacherRaw = $_POST['new_teacher'];
        $classFilter = $_POST['class_filter'] ?? '';

        $oldTeacher = normalizeTeacherName($oldTeacherRaw);
        $newTeacher = findRegisteredTeacherName($conn, $newTeacherRaw);

        $sql = "UPDATE `routine` SET teacher = ? WHERE teacher = ?";
        $params = [$newTeacher, $oldTeacher];
        $types = 'ss';
        if (!empty($classFilter)) {
            $sql .= " AND class_group = ?";
            $params[] = $classFilter;
            $types .= 's';
        }
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $rowsUpdated = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        $replaceMsg = "Replaced '$oldTeacher' with '" . ($newTeacher === '' ? 'Not assigned' : $newTeacher) . "' in $rowsUpdated record(s).";
    }
    
    // Fetch all routine data for display
    $result = mysqli_query($conn, "SELECT * FROM `routine` ORDER BY class_group, FIELD(day, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'), start_time");
    $routines = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Get distinct class groups for filter and replace dropdown
    $result = mysqli_query($conn, "SELECT DISTINCT class_group FROM `routine` ORDER BY class_group");
    $classGroups = [];
    while ($row = mysqli_fetch_row($result)) {
        $classGroups[] = $row[0];
    }

    // Get available classes, groups, subjects, and teachers for the add routine form
    $classesRes = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
    $classes = [];
    if ($classesRes) {
        while ($r = mysqli_fetch_assoc($classesRes)) { $classes[] = $r; }
    }

    $groupsRes = mysqli_query($conn, "SELECT id, group_name FROM groups ORDER BY group_name");
    $groups = [];
    if ($groupsRes) {
        while ($r = mysqli_fetch_assoc($groupsRes)) { $groups[] = $r; }
    }

    // Fetch subjects including class association so we can filter client-side
        $subjectsRes = mysqli_query($conn, "SELECT id, subject_name, class_id, COALESCE(stream, '') AS stream FROM subjects ORDER BY subject_name");
    $subjects = [];
    if ($subjectsRes) {
        while ($r = mysqli_fetch_assoc($subjectsRes)) { $subjects[] = $r; }
    }

    // Teachers list and teacher_subjects mapping for filtering
    $teachersRes = mysqli_query($conn, "SELECT id, CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1 ORDER BY first_name, last_name");
    $teachersList = [];
    if ($teachersRes) {
        while ($r = mysqli_fetch_assoc($teachersRes)) { $teachersList[] = $r; }
    }

    // Build teacher-subject mapping (include class_id when available)
    $hasTSClassCol = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) { $hasTSClassCol = true; }

    $tsSelect = "SELECT ts.subject_id, ts.teacher_id" . ($hasTSClassCol ? ", ts.class_id" : "") . ", CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name FROM teacher_subjects ts JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1";
    $tsRes = mysqli_query($conn, $tsSelect);
    $teacherSubjects = [];
    if ($tsRes) {
        while ($r = mysqli_fetch_assoc($tsRes)) {
            $teacherSubjects[] = $r;
        }
    }
    
    // Get distinct teacher names for replace dropdown
    $result = mysqli_query($conn, "SELECT DISTINCT teacher FROM `routine` ORDER BY teacher");
    $teachers = [];
    while ($row = mysqli_fetch_row($result)) {
        $teachers[] = $row[0];
    }
    // Also fetch registered teacher full names for the edit modal datalist
    $registeredTeachers = [];
    $rt = mysqli_query($conn, "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1 ORDER BY first_name, last_name");
    if ($rt) {
        while ($r = mysqli_fetch_assoc($rt)) {
            $registeredTeachers[] = $r['full_name'];
        }
    }
    // Build a lookup of registered teachers (full name) for quick existence checks
    $registeredTeachersMap = [];
    $tr = mysqli_query($conn, "SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) AS full_name FROM teachers WHERE status = 1");
    if ($tr) {
        while ($trow = mysqli_fetch_assoc($tr)) {
            $registeredTeachersMap[strtolower(normalizeTeacherName($trow['full_name']))] = true;
        }
    }
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaching Center - Timetable Management</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: auto; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 20px; }
        h1, h2 { color: #2c3e50; margin-top: 0; }
        .message { padding: 10px; margin: 10px 0; border-radius: 6px; }
        .error { background: #fee; color: #c00; border-left: 5px solid #c00; }
        .success { background: #efe; color: #060; border-left: 5px solid #060; }
        .info { background: #e7f3ff; color: #004080; border-left: 5px solid #004080; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; overflow-x: auto; display: block; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #2c3e50; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f9f9f9; }
        .edit-btn { background: #3498db; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .edit-btn:hover { background: #2980b9; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 20px; border-radius: 10px; width: 500px; max-width: 90%; }
        .modal-content h3 { margin-top: 0; }
        .modal-content input, .modal-content select { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; }
        .modal-content button { margin-top: 10px; padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .close { float: right; font-size: 24px; font-weight: bold; cursor: pointer; }
        .highlighted-row { background: #fff7c6 !important; }
        .replace-section { background: #ecf0f1; padding: 15px; border-radius: 8px; margin: 20px 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .replace-section .form-group { flex: 1; min-width: 150px; }
        .replace-section label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .replace-section select, .replace-section input { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .replace-section button { background: #e67e22; padding: 8px 16px; border: none; border-radius: 4px; color: white; cursor: pointer; }
        @media (max-width: 768px) { th, td { font-size: 12px; padding: 6px; } .replace-section { flex-direction: column; } }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 12px; flex-wrap: wrap;">
        <h1 style="margin: 0;">📚 Coaching Center Timetable Management</h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="window.location.replace(window.location.pathname + window.location.search);" style="background: #16a085; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">🔄 Refresh</button>
            <a href="dashboard.php" style="background: #2c3e50; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
   
    <?php if (!empty($editError)): ?>
        <div class="message error"><?php echo nl2br(htmlspecialchars($editError)); ?></div>
    <?php endif; ?>
    <?php if (!empty($editSuccess)): ?>
        <div class="message success"><?php echo htmlspecialchars($editSuccess); ?></div>
    <?php endif; ?>
    <?php if (!empty($replaceMsg)): ?>
        <div class="message info"><?php echo htmlspecialchars($replaceMsg); ?></div>
    <?php endif; ?>
    <?php if (!empty($addError)): ?>
        <div class="message error"><?php echo nl2br(htmlspecialchars($addError)); ?></div>
    <?php endif; ?>

    <section style="margin-bottom: 24px; padding: 16px; border: 1px solid #dcdcdc; border-radius: 10px; background: #fbfbfb;">
        <h2 style="margin-top: 0;">➕ Add Routine</h2>
        <form method="post" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px;">
            <div>
                <label for="class_id">Class</label>
                <select name="class_id" id="class_id" required>
                    <option value="">Select class</option>
                    <?php foreach ($classes as $row): ?>
                        <option value="<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="group_id">Group</label>
                <select name="group_id" id="group_id" required>
                    <option value="">Select group</option>
                    <?php foreach ($groups as $row): ?>
                        <option value="<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['group_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="subject_id">Subject</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">Select subject</option>
                    <?php foreach ($subjects as $row): ?>
                        <option value="<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['subject_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new_subject_name">New Subject</label>
                <input type="text" name="new_subject_name" id="new_subject_name" placeholder="Add a new subject">
                <small style="color:#666; font-size:12px; display:block; margin-top:4px;">Leave blank to choose an existing subject.</small>
            </div>
            <div>
                <label for="teacher_id">Teacher</label>
                <select name="teacher_id" id="teacher_id" required>
                    <option value="">Select teacher</option>
                    <?php foreach ($teachersList as $row): ?>
                        <option value="<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="day">Day</label>
                <select name="day" id="day" required>
                    <option value="">Select day</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                </select>
            </div>
            <div>
                <label for="room">Room</label>
                <input type="text" name="room" id="room" placeholder="Room" required>
            </div>
            <div>
                <label for="start_time">Start Time</label>
                <input type="time" name="start_time" id="start_time" required>
            </div>
            <div>
                <label for="end_time">End Time</label>
                <input type="time" name="end_time" id="end_time" required>
            </div>
            <div style="align-self: flex-end;">
                <input type="hidden" name="add_routine" value="1">
                <button type="submit" style="background: #27ae60; color: white; border: none; border-radius: 6px; padding: 12px 16px; cursor: pointer; width: 100%;">Save Routine</button>
            </div>
        </form>
    </section>

    <!-- Routine Display Table -->
    <h2>📅 Current Timetable</h2>
    <div style="margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <input id="routineSearch" type="text" placeholder="Search class, group, subject, teacher, room..." style="flex:1; min-width:260px; padding:10px 12px; border:1px solid #ccc; border-radius:8px;" />
        <span style="color:#555; font-size:14px;">Filter the timetable instantly as you type.</span>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Class & Group</th><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routines as $row): ?>
                    <tr<?php echo ($highlightedRowId !== null && intval($row['id']) === $highlightedRowId) ? ' class="highlighted-row"' : ''; ?>>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['class_group']); ?></td>
                        <td><?php echo htmlspecialchars($row['day']); ?></td>
                        <td><?php echo date("g:i A", strtotime($row['start_time'])) . " – " . date("g:i A", strtotime($row['end_time'])); ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <?php
                            $teacherRaw = trim($row['teacher'] ?? '');
                            $teacherResolved = findRegisteredTeacherName($conn, $teacherRaw);
                            $teacherHtml = $teacherResolved === '' ? 'Not assigned' : htmlspecialchars($teacherResolved);
                        ?>
                        <td><?php echo $teacherHtml; ?></td>
                        <td><?php echo htmlspecialchars($row['room']); ?></td>
                        <td><button class="edit-btn" data-id="<?php echo $row['id']; ?>" 
                                    data-class="<?php echo htmlspecialchars($row['class_group']); ?>"
                                    data-day="<?php echo htmlspecialchars($row['day']); ?>"
                                    data-start="<?php echo $row['start_time']; ?>"
                                    data-end="<?php echo $row['end_time']; ?>"
                                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                    data-teacher="<?php echo htmlspecialchars($row['teacher']); ?>"
                                    data-room="<?php echo htmlspecialchars($row['room']); ?>">✏️ Edit</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($highlightedRowId !== null): ?>
<div class="container" style="margin-top: 24px;">
    <h2 style="margin-top: 0;">Recently Added</h2>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Class & Group</th><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routines as $row): ?>
                    <?php if (intval($row['id']) === $highlightedRowId): ?>
                        <?php
                            $teacherRaw = trim($row['teacher'] ?? '');
                            $teacherResolved = findRegisteredTeacherName($conn, $teacherRaw);
                            $teacherHtmlRow = $teacherResolved === '' ? 'Not assigned' : htmlspecialchars($teacherResolved);
                        ?>
                        <tr class="highlighted-row">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['class_group']); ?></td>
                            <td><?php echo htmlspecialchars($row['day']); ?></td>
                            <td><?php echo date("g:i A", strtotime($row['start_time'])) . " – " . date("g:i A", strtotime($row['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo $teacherHtmlRow; ?></td>
                            <td><?php echo htmlspecialchars($row['room']); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal for Edit Form -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Routine Entry</h3>
        <form method="post" id="editForm">
            <input type="hidden" name="edit_id" id="edit_id">
            <label>Class & Group</label>
            <select name="class_group" id="class_group" required>
                <?php foreach ($classGroups as $cg): ?>
                    <option value="<?php echo htmlspecialchars($cg); ?>"><?php echo htmlspecialchars($cg); ?></option>
                <?php endforeach; ?>
            </select>
            <label>Day</label>
            <select name="day" id="day" required>
                <option>Saturday</option><option>Sunday</option><option>Monday</option>
                <option>Tuesday</option><option>Wednesday</option><option>Thursday</option>
            </select>
            <label>Start Time</label>
            <input type="time" name="start_time" id="start_time" required>
            <label>End Time</label>
            <input type="time" name="end_time" id="end_time" required>
            <label>Subject</label>
            <input type="text" name="subject" id="subject" required>
            <label>Teacher</label>
            <input type="text" name="teacher" id="teacher" required list="teacherList">
            <datalist id="teacherList">
                <?php foreach ($registeredTeachers as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>">
                <?php endforeach; ?>
            </datalist>
            <label>Room</label>
            <input type="text" name="room" id="room" required>
            <button type="submit">💾 Save Changes</button>
            <button type="button" class="close-modal" style="background:#95a5a6;">Cancel</button>
        </form>
        <p class="info" style="font-size:12px; margin-top:10px;">⚠️ System will check teacher & room conflicts before saving (warns only).</p>
    </div>
</div>

<script>
    // Modal handling
    const modal = document.getElementById('editModal');
    const editButtons = document.querySelectorAll('.edit-btn');
    const closeSpan = document.querySelector('.close');
    const closeModalBtn = document.querySelector('.close-modal');
    
    function openModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('class_group').value = data.class_group;
        document.getElementById('day').value = data.day;
        document.getElementById('start_time').value = data.start_time;
        document.getElementById('end_time').value = data.end_time;
        document.getElementById('subject').value = data.subject;
        document.getElementById('teacher').value = '';
        document.getElementById('room').value = data.room;
        modal.style.display = 'flex';
        autoFillTeacher();
    }
    
    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const data = {
                id: btn.dataset.id,
                class_group: btn.dataset.class,
                day: btn.dataset.day,
                start_time: btn.dataset.start,
                end_time: btn.dataset.end,
                subject: btn.dataset.subject,
                teacher: btn.dataset.teacher,
                room: btn.dataset.room
            };
            openModal(data);
        });
    });

    const routineSearch = document.getElementById('routineSearch');
    const routineRows = document.querySelectorAll('tbody tr');

    function filterRoutineRows() {
        const query = routineSearch.value.trim().toLowerCase();

        routineRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }

    if (routineSearch) {
        routineSearch.addEventListener('input', filterRoutineRows);
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    if (closeSpan) closeSpan.onclick = closeModal;
    if (closeModalBtn) closeModalBtn.onclick = closeModal;
    window.onclick = function(event) {
        if (event.target === modal) closeModal();
    }

    function autoFillTeacher() {
        const classGroup = document.getElementById('class_group').value;
        const subject = document.getElementById('subject').value.trim();
        const teacherInput = document.getElementById('teacher');

        if (!classGroup || !subject || !teacherInput) {
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_teacher_by_subject_group.php?class_group=' + encodeURIComponent(classGroup) + '&subject=' + encodeURIComponent(subject), true);
        xhr.onload = function() {
            if (xhr.status !== 200) {
                return;
            }
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.assigned && data.teacher) {
                    teacherInput.value = data.teacher;
                }
            } catch (e) {
                console.error('Failed to parse teacher lookup response', e);
            }
        };
        xhr.onerror = function() {
            console.error('Teacher lookup request failed');
        };
        xhr.send();
    }

    document.getElementById('subject').addEventListener('change', autoFillTeacher);
    document.getElementById('class_group').addEventListener('change', autoFillTeacher);
    
    // --- Dynamic filtering for Add Routine form ---
    (function() {
        var subjects = <?php echo json_encode($subjects, JSON_HEX_TAG); ?>;
        var teachers = <?php echo json_encode($teachersList, JSON_HEX_TAG); ?>;
        var teacherSubjects = <?php echo json_encode($teacherSubjects, JSON_HEX_TAG); ?>;
        var groups = <?php echo json_encode($groups, JSON_HEX_TAG); ?>;

        function populateSubjects() {
            var classId = document.getElementById('class_id').value;
            var subjectSelect = document.getElementById('subject_id');
            var newSubject = document.getElementById('new_subject_name').value.trim();

            subjectSelect.innerHTML = '<option value="">Select subject</option>';
            if (newSubject) {
                subjectSelect.innerHTML = '<option value="">Existing subjects disabled while adding new subject</option>';
                subjectSelect.disabled = true;
                populateTeachers();
                return;
            }

            subjects.forEach(function(s) {
                // show subject if no class_id or matches selected class
                if (classId && String(s.class_id) !== String(classId)) return;

                // apply group/stream filter if a group is selected
                var groupId = document.getElementById('group_id').value;
                if (groupId) {
                    var groupObj = groups.find(g => String(g.id) === String(groupId));
                    var groupName = groupObj ? String(groupObj.group_name).toLowerCase() : '';
                    var stream = (s.stream || '').toLowerCase();
                    // Exact match requested: subject.stream must equal group name (case-insensitive)
                    if (stream && groupName && stream !== groupName) {
                        return; // subject stream doesn't exactly match selected group
                    }
                }

                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.subject_name;
                subjectSelect.appendChild(opt);
            });
            // trigger teacher population clearing
            populateTeachers();
            // if no subjects available, show fallback option
            if (document.getElementById('subject_id').options.length === 1) {
                var sOpt = document.createElement('option');
                sOpt.value = '';
                sOpt.textContent = 'No subjects available for selected group';
                sOpt.disabled = true;
                document.getElementById('subject_id').appendChild(sOpt);
                document.getElementById('subject_id').disabled = true;
            } else {
                document.getElementById('subject_id').disabled = false;
            }
        }

        function populateTeachers() {
            var subjectId = document.getElementById('subject_id').value;
            var classId = document.getElementById('class_id').value;
            var newSubject = document.getElementById('new_subject_name').value.trim();
            var teacherSelect = document.getElementById('teacher_id');
            teacherSelect.innerHTML = '<option value="">Select teacher</option>';

            if (newSubject) {
                teachers.forEach(function(t) {
                    var opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.full_name;
                    teacherSelect.appendChild(opt);
                });
                if (teachers.length === 0) {
                    var tOpt = document.createElement('option');
                    tOpt.value = '';
                    tOpt.textContent = 'No active teachers available';
                    tOpt.disabled = true;
                    teacherSelect.appendChild(tOpt);
                    teacherSelect.disabled = true;
                } else {
                    teacherSelect.disabled = false;
                }
                return;
            }

            if (!subjectId) {
                teacherSelect.disabled = true;
                return;
            }

            // find teacher ids for this subject (and optionally class_id if present on mapping)
            var allowedTeacherIds = {};
            teacherSubjects.forEach(function(ts) {
                if (String(ts.subject_id) !== String(subjectId)) return;
                if (ts.hasOwnProperty('class_id') && ts.class_id !== null && ts.class_id !== '' && classId) {
                    if (String(ts.class_id) !== String(classId)) return;
                }
                allowedTeacherIds[String(ts.teacher_id)] = true;
            });

            var added = 0;
            teachers.forEach(function(t) {
                if (allowedTeacherIds[String(t.id)]) {
                    var opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.full_name;
                    teacherSelect.appendChild(opt);
                    added++;
                }
            });
            if (added === 0) {
                var tOpt = document.createElement('option');
                tOpt.value = '';
                tOpt.textContent = 'No teachers assigned to selected subject/class';
                tOpt.disabled = true;
                teacherSelect.appendChild(tOpt);
                teacherSelect.disabled = true;
            } else {
                teacherSelect.disabled = false;
            }
        }

        document.getElementById('class_id').addEventListener('change', populateSubjects);
        // When group changes, re-populate subjects so stream exact-match is applied
        document.getElementById('group_id').addEventListener('change', populateSubjects);
        document.getElementById('subject_id').addEventListener('change', populateTeachers);
        document.getElementById('new_subject_name').addEventListener('input', populateSubjects);

        // initialize subjects list on page load
        populateSubjects();
    })();
</script>
</body>
</html>