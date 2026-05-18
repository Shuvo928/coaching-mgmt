<?php
session_start();
require_once '../includes/db.php';
/** @var mysqli $conn */

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = 'info';

// DELETE ROUTINE
if (isset($_GET['delete_routine']) && is_numeric($_GET['delete_routine'])) {
    $delete_id = intval($_GET['delete_routine']);
    mysqli_query($conn, "DELETE FROM class_routine WHERE id = $delete_id");
    $message = "Routine deleted successfully.";
    $messageType = 'success';
}

function parseRoutineTimeSeconds($time) {
    $time = trim($time);
    if ($time === '') {
        return false;
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return false;
    }

    return intval(date('H', $timestamp)) * 3600 + intval(date('i', $timestamp)) * 60 + intval(date('s', $timestamp));
}

function normalizeRoutineInterval($start, $end) {
    $s = parseRoutineTimeSeconds($start);
    $e = parseRoutineTimeSeconds($end);
    if ($s === false || $e === false) {
        return false;
    }
    if ($e < $s) {
        $e += 24 * 3600;
    }
    return [$s, $e];
}

function intervalsOverlap($aStart, $aEnd, $bStart, $bEnd) {
    return !($aEnd < $bStart || $aStart > $bEnd);
}

function findRoutineConflicts($conn, $day, $start_time, $end_time, $teacher_id, $room, $group_id, $excludeId = null) {
    $day = mysqli_real_escape_string($conn, trim($day));
    $teacher_id = intval($teacher_id);
    $group_id = intval($group_id);
    $room = mysqli_real_escape_string($conn, trim($room));

    $interval = normalizeRoutineInterval($start_time, $end_time);
    if ($interval === false) {
        return [];
    }
    list($newStart, $newEnd) = $interval;

    $conditions = ["cr.teacher_id = $teacher_id"];
    if ($room !== '') {
        $escapedRoom = mysqli_real_escape_string($conn, trim($room));
        $conditions[] = "LOWER(TRIM(cr.room)) = LOWER('$escapedRoom')";
    }
    if ($group_id > 0) {
        $conditions[] = "cr.group_id = $group_id";
    }

    if (empty($conditions)) {
        return [];
    }

    $query = "SELECT cr.* FROM class_routine cr WHERE cr.day = '$day'";
    if ($excludeId) {
        $query .= " AND cr.id != " . intval($excludeId);
    }
    $query .= " AND (" . implode(' OR ', $conditions) . ")";

    $result = mysqli_query($conn, $query);
    $conflicts = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $existingInterval = normalizeRoutineInterval($row['start_time'], $row['end_time']);
            if ($existingInterval === false) {
                continue;
            }
            list($existingStart, $existingEnd) = $existingInterval;

            if (!intervalsOverlap($newStart, $newEnd, $existingStart, $existingEnd)) {
                continue;
            }

            if (intval($row['teacher_id']) === $teacher_id) {
                $conflicts['Teacher Conflict'] = true;
            }
            if ($room !== '' && trim($row['room']) !== '' && strcasecmp(trim($row['room']), $room) === 0) {
                $conflicts['Room Conflict'] = true;
            }
            if ($group_id > 0 && intval($row['group_id']) === $group_id) {
                $conflicts['Batch Conflict'] = true;
            }
        }
    }

    return array_keys($conflicts);
}

function getRoutineConflictLabels($conn, $routine) {
    return findRoutineConflicts(
        $conn,
        $routine['day'],
        $routine['start_time'],
        $routine['end_time'],
        $routine['teacher_id'],
        $routine['room'],
        $routine['group_id'],
        $routine['id']
    );
}

// INSERT ROUTINE
if (isset($_POST['submit'])) {
    $class_id   = mysqli_real_escape_string($conn, $_POST['class_id']);
    $group_id   = mysqli_real_escape_string($conn, $_POST['group_id']);
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $day        = mysqli_real_escape_string($conn, $_POST['day']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time   = mysqli_real_escape_string($conn, $_POST['end_time']);
    $room       = mysqli_real_escape_string($conn, trim($_POST['room'] ?? ''));

    // Validate teacher eligibility for subject server-side
    $eligibility_query = "SELECT id FROM teacher_subjects WHERE teacher_id = '$teacher_id' AND subject_id = '$subject_id' LIMIT 1";
    $eligibility_result = mysqli_query($conn, $eligibility_query);
    if (!$eligibility_result || mysqli_num_rows($eligibility_result) === 0) {
        $message = "Error: Selected teacher is not assigned to this subject.";
        $messageType = 'danger';
    } else {
        $conflicts = findRoutineConflicts($conn, $day, $start_time, $end_time, $teacher_id, $room, $group_id);

        if (!empty($conflicts)) {
            $message = "Conflict detected: " . implode(', ', $conflicts) . ". Routine not saved. Please resolve the schedule conflict before adding.";
            $messageType = 'warning';
        } else {
            $sql = "INSERT INTO class_routine 
                (class_id, group_id, subject_id, teacher_id, day, start_time, end_time, room)
                VALUES 
                ('$class_id', '$group_id', '$subject_id', '$teacher_id', '$day', '$start_time', '$end_time', '$room')";

            if (mysqli_query($conn, $sql)) {
                $message = "Routine added successfully!";
                $messageType = 'success';
            } else {
                $message = "Error: " . mysqli_error($conn);
                $messageType = 'danger';
            }
        }
    }
}

// FETCH DATA
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY id DESC");
$groups  = mysqli_query($conn, "SELECT * FROM groups ORDER BY id DESC");
$teachers = mysqli_query($conn, "SELECT * FROM teachers WHERE status = 1 ORDER BY id DESC");

// FETCH RECENTLY ADDED ROUTINES
$recent_routines_query = "SELECT cr.*, c.class_name, g.group_name, s.subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                          FROM class_routine cr
                          JOIN classes c ON cr.class_id = c.id
                          LEFT JOIN groups g ON cr.group_id = g.id
                          JOIN subjects s ON cr.subject_id = s.id
                          LEFT JOIN teachers t ON cr.teacher_id = t.id
                          ORDER BY cr.id DESC
                          LIMIT 10";
$recent_routines = mysqli_query($conn, $recent_routines_query);
?>

<!DOCTYPE html>

<html>
<head>
    <title>Add Routine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4">


    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Add Class Routine</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo $message; ?></div>
    <?php } ?>

    <form method="POST">

        <!-- CLASS -->
        <div class="mb-3">
            <label>Class</label>
            <select name="class_id" class="form-control" required>
                <option value="">Select Class</option>
                <?php while($row = mysqli_fetch_assoc($classes)) { ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['class_name']; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- GROUP -->
        <div class="mb-3">
            <label>Group</label>
            <select name="group_id" id="group_id" class="form-control" required>
                <option value="">Select Group</option>
                <?php while($row = mysqli_fetch_assoc($groups)) { ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['group_name']; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- SUBJECT -->
        <div class="mb-3">
            <label>Subject</label>
            <select name="subject_id" id="subject_id" class="form-control" required>
                <option value="">Select Subject</option>
            </select>
        </div>

        <!-- TEACHER -->
        <div class="mb-3">
            <label>Teacher</label>
            <select name="teacher_id" id="teacher_id" class="form-control" required>
                <option value="">Select Teacher</option>
                <?php while($row = mysqli_fetch_assoc($teachers)) { ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['first_name'] . " " . $row['last_name']; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- DAY -->
        <div class="mb-3">
            <label>Day</label>
            <select name="day" class="form-control" required>
                <option value="">Select Day</option>
                <option>Sunday</option>
                <option>Monday</option>
                <option>Tuesday</option>
                <option>Wednesday</option>
                <option>Thursday</option>
                <option>Friday</option>
                <option>Saturday</option>
            </select>
        </div>

        <!-- TIME -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Start Time</label>
                <input type="time" name="start_time" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label>End Time</label>
                <input type="time" name="end_time" class="form-control" required>
            </div>
        </div>

        <!-- ROOM NUMBER -->
        <div class="mb-3">
            <label>Room Number</label>
            <input type="text" name="room" class="form-control" placeholder="Enter room number" required>
        </div>

        <button type="submit" name="submit" class="btn btn-primary w-100">
            Add Routine
        </button>

    </form>
</div>


</div>

<!-- RECENTLY ADDED ROUTINES -->
<div class="card shadow p-4 mt-4">
    <h4 class="mb-4"><i class="fas fa-history me-2"></i> Recently Added Routines</h4>
    
    <?php if (mysqli_num_rows($recent_routines) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Day</th>
                        <th>Class</th>
                        <th>Group</th>
                        <th>Subject</th>
                                <th>Teacher</th>
                        <th>Room</th>
                        <th>Conflict</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($routine = mysqli_fetch_assoc($recent_routines)): 
                        $conflictTypes = getRoutineConflictLabels($conn, $routine);
                    ?>
                        <tr class="<?php echo !empty($conflictTypes) ? 'table-warning' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($routine['day']); ?></strong></td>
                            <td><?php echo htmlspecialchars($routine['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($routine['group_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($routine['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars(trim($routine['teacher_name'] ?? '') ?: 'Unassigned teacher'); ?></td>
                            <td><?php echo htmlspecialchars($routine['room'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($conflictTypes)): ?>
                                    <?php foreach ($conflictTypes as $conflict): ?>
                                        <span class="badge bg-danger me-1"><?php echo htmlspecialchars($conflict); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-success">No conflicts</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($routine['start_time'])) . ' - ' . date('h:i A', strtotime($routine['end_time']))); ?></td>
                            <td>
                                <a href="add_routine.php?delete_routine=<?php echo intval($routine['id']); ?>" class="text-danger" onclick="return confirm('Delete this routine?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-4">No routines added yet.</p>
    <?php endif; ?>
</div>

</div>

<!-- AJAX SCRIPT & VALIDATION -->

<script>
// Fetch subjects when group changes
document.getElementById('group_id').addEventListener('change', function() {
    var group_id = this.value;

    var xhr = new XMLHttpRequest();
    xhr.open("GET", "get_subjects.php?group_id=" + group_id, true);

    xhr.onload = function() {
        document.getElementById('subject_id').innerHTML = this.responseText;
    };

    xhr.send();
});

// Validate teacher-subject assignment when subject changes
document.getElementById('subject_id').addEventListener('change', function() {
    var teacher_id = document.getElementById('teacher_id').value;
    var subject_id = this.value;
    
    if (!teacher_id || !subject_id) {
        return;
    }
    
    // Check if subject is assigned to teacher
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "check_teacher_subject.php?teacher_id=" + teacher_id + "&subject_id=" + subject_id, true);
    
    xhr.onload = function() {
        var response = JSON.parse(this.responseText);
        if (!response.assigned) {
            alert('⚠️ WARNING: This subject is NOT assigned to the selected teacher!\\n\\nPlease select another subject or change the teacher.');
            document.getElementById('subject_id').value = '';
        }
    };
    
    xhr.onerror = function() {
        console.error('Error checking teacher-subject assignment');
    };
    
    xhr.send();
});

// Also validate when teacher changes
document.getElementById('teacher_id').addEventListener('change', function() {
    var subject_id = document.getElementById('subject_id').value;
    var teacher_id = this.value;
    
    if (!teacher_id || !subject_id) {
        return;
    }
    
    // Check if subject is assigned to teacher
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "check_teacher_subject.php?teacher_id=" + teacher_id + "&subject_id=" + subject_id, true);
    
    xhr.onload = function() {
        var response = JSON.parse(this.responseText);
        if (!response.assigned) {
            alert('⚠️ WARNING: The selected subject is NOT assigned to this teacher!\\n\\nPlease select a different teacher or change the subject.');
            document.getElementById('teacher_id').value = '';
        }
    };
    
    xhr.onerror = function() {
        console.error('Error checking teacher-subject assignment');
    };
    
    xhr.send();
});
</script>

</body>
</html>
