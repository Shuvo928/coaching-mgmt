<?php
session_start();
require_once '../includes/db.php'; // adjust path if needed
/** @var mysqli $conn */

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher_query = "SELECT * FROM teachers WHERE user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
if (mysqli_num_rows($teacher_result) == 0) {
    die("Teacher record not found. Please contact admin.");
}
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

function extractClassNameFromGroup(string $classGroup): string {
    $parts = preg_split('/\s*[–—-]\s*/u', $classGroup);
    return isset($parts[0]) ? trim($parts[0]) : trim($classGroup);
}

// ------------------------------------------------------------------
// 1. Fetch teacher's assigned subjects (for validation & dropdown)
// ------------------------------------------------------------------
// First, check if class_id column exists in subjects table
$class_id_check = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'class_id'");
$has_class_id = ($class_id_check && mysqli_num_rows($class_id_check) > 0);

if (!$has_class_id) {
    die("<div style='color: red; padding: 20px;'><h3>Database Migration Required</h3>
         <p>The 'class_id' column is missing from the subjects table.</p>
         <p>Please run the migration script: <a href='../setup-subjects-class-id.php'>setup-subjects-class-id.php</a></p>
         <p>After running the migration, please refresh this page.</p>
         </div>");
}

$assigned_subjects_query = "SELECT DISTINCT ts.subject_id, s.subject_name, s.class_id, c.class_name, s.stream
                            FROM teacher_subjects ts
                            JOIN subjects s ON ts.subject_id = s.id
                            JOIN classes c ON s.class_id = c.id
                            WHERE ts.teacher_id = $teacher_id
                            ORDER BY c.class_name, s.stream, s.subject_name";
$assigned_subjects = mysqli_query($conn, $assigned_subjects_query);

// Build a quick lookup for permission checks
$allowed_subject_ids = [];
$allowed_subject_names = [];
$allowed_combinations = []; // for frontend filtering
$comboKeys = [];
while ($row = mysqli_fetch_assoc($assigned_subjects)) {
    if (!in_array($row['subject_id'], $allowed_subject_ids, true)) {
        $allowed_subject_ids[] = $row['subject_id'];
    }
    if (!in_array($row['subject_name'], $allowed_subject_names, true)) {
        $allowed_subject_names[] = $row['subject_name'];
    }

    $comboKey = $row['class_id'] . '_' . $row['subject_id'];
    if (!isset($comboKeys[$comboKey])) {
        $comboKeys[$comboKey] = true;
        $allowed_combinations[] = [
            'class_id' => $row['class_id'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'class_name' => $row['class_name'],
            'stream' => $row['stream'] ?? ''
        ];
    }
}
// Reset pointer for later use
mysqli_data_seek($assigned_subjects, 0);

// ------------------------------------------------------------------
// 2. Handle Bulk Save / Update of marks
// ------------------------------------------------------------------
// NOTE: Moved to separate file: save-teacher-marks.php
// This keeps the JSON response clean without HTML interference
$success_msg = '';
$error_msg = '';

if (isset($_GET['save_bulk_marks']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... handler code moved to save-teacher-marks.php
}

// ------------------------------------------------------------------
// 3. Fetch list of students (only classes where teacher has subjects)
// ------------------------------------------------------------------
$class_ids_allowed = array_unique(array_column($allowed_combinations, 'class_id'));
$class_ids_str = implode(',', $class_ids_allowed);
$students_list = [];
if (!empty($class_ids_str)) {
    // Check which columns exist in admission_applications
    $admissionPhoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admission_applications LIKE 'mobile'");
    $admissionHasMobile = ($admissionPhoneColumn && mysqli_num_rows($admissionPhoneColumn) > 0);
    $admissionPhoneField = $admissionHasMobile ? 'mobile' : 'phone';
    
    // Use student's ID as roll_number (ID is unique and guaranteed to be non-null)
    $students_sql = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.id AS roll_number, s.class_id, s.group_id, s.phone,
                            c.class_name, 
                            COALESCE(aa.`group`, g.group_name, 'Unassigned') AS group_name
                     FROM students s
                     JOIN classes c ON s.class_id = c.id
                     LEFT JOIN admission_applications aa ON s.phone = aa.$admissionPhoneField
                     LEFT JOIN `groups` g ON s.group_id = g.id
                     WHERE s.class_id IN ($class_ids_str)
                     ORDER BY c.class_name, COALESCE(aa.`group`, g.group_name, 'Unassigned'), s.id";
    $students_res = mysqli_query($conn, $students_sql);
    
    if (!$students_res) {
        die("Students Query Error: " . mysqli_error($conn));
    }
    
    while ($stu = mysqli_fetch_assoc($students_res)) {
        $students_list[] = $stu;
    }
}

// ------------------------------------------------------------------
// 4. Fetch existing results (for display) – only teacher's subjects
// ------------------------------------------------------------------
$allowed_subjects_str = implode(',', $allowed_subject_ids);
$recent_results = [];
if (!empty($allowed_subjects_str)) {
    $results_sql = "SELECT r.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.id AS roll_number, s.class_id AS student_class_id, s.group_id AS student_group_id, sub.subject_name, c.class_name, COALESCE(g.group_name, 'Unassigned') AS group_name
                    FROM results r
                    JOIN students s ON r.student_id = s.id
                    JOIN subjects sub ON r.subject_id = sub.id
                    JOIN classes c ON sub.class_id = c.id
                    LEFT JOIN `groups` g ON s.group_id = g.id
                    WHERE r.subject_id IN ($allowed_subjects_str)
                    ORDER BY r.created_at DESC
                    LIMIT 50";
    $results_res = mysqli_query($conn, $results_sql);
    while ($row = mysqli_fetch_assoc($results_res)) {
        $recent_results[] = $row;
    }
}

// ------------------------------------------------------------------
// 5. Fetch teacher routine data
// ------------------------------------------------------------------
$teacher_routines = [];
$found_routine_combinations = [];
$missing_routine_combos = [];

// Build teacher routine query from the admin-managed current timetable.
$first_name = trim($teacher['first_name']);
$last_name = trim($teacher['last_name']);
$full_name = trim($first_name . ' ' . $last_name);

$teacher_routine_query = "SELECT id, class_group AS class_name, subject AS subject_name, day, start_time, end_time, room
                         FROM `routine`
                         WHERE LOWER(TRIM(teacher)) = LOWER('" . mysqli_real_escape_string($conn, $full_name) . "')";

// Only include routines that match the teacher's assigned class+subject combinations.
$assigned_conditions = [];
foreach ($allowed_combinations as $comb) {
    $subject_name = mysqli_real_escape_string($conn, $comb['subject_name']);
    $class_name = mysqli_real_escape_string($conn, $comb['class_name']);
    $assigned_conditions[] = "(LOWER(subject) = LOWER('$subject_name') AND LOWER(class_group) LIKE LOWER('$class_name%'))";
}

if (!empty($assigned_conditions)) {
    $teacher_routine_query .= " AND (" . implode(' OR ', $assigned_conditions) . ")";
}

$teacher_routine_query .= " ORDER BY FIELD(day,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time";

$routine_result = mysqli_query($conn, $teacher_routine_query);
if (!$routine_result) {
    error_log("Teacher Routine Query Error: " . mysqli_error($conn));
} else {
    while ($row = mysqli_fetch_assoc($routine_result)) {
        $teacher_routines[] = $row;
        $key = strtolower(trim($row['class_name'] . '|' . $row['subject_name']));
        $found_routine_combinations[$key] = true;
    }
}

foreach ($allowed_combinations as $comb) {
    $key = strtolower(trim($comb['class_name'] . '|' . $comb['subject_name']));
    if (!isset($found_routine_combinations[$key])) {
        $missing_routine_combos[] = $comb;
    }
}

$missing_routine_text = '';
if (!empty($missing_routine_combos)) {
    $formatted_missing = [];
    foreach ($missing_routine_combos as $missing_combo) {
        $formatted_missing[] = htmlspecialchars($missing_combo['class_name'] . ' - ' . $missing_combo['subject_name']);
    }
    $missing_routine_text = implode(', ', $formatted_missing);
}

// Sort combined routines by day and time for a consistent display order.
$days_order = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
usort($teacher_routines, function ($a, $b) use ($days_order) {
    $day_a = $days_order[$a['day']] ?? 7;
    $day_b = $days_order[$b['day']] ?? 7;
    if ($day_a !== $day_b) {
        return $day_a <=> $day_b;
    }
    return strcmp($a['start_time'], $b['start_time']);
});

// Filter out conflicting teacher routines so the dashboard shows only the first valid slot for any overlapping day/time or room conflict.
$filtered_teacher_routines = [];
foreach ($teacher_routines as $routine) {
    $conflictFound = false;
    foreach ($filtered_teacher_routines as $existing) {
        if ($existing['day'] !== $routine['day']) {
            continue;
        }

        $existingStart = $existing['start_time'];
        $existingEnd = $existing['end_time'];
        $currentStart = $routine['start_time'];
        $currentEnd = $routine['end_time'];

        $timesOverlap = !($existingEnd <= $currentStart || $existingStart >= $currentEnd);
        $roomConflict = $existing['room'] !== '' && $routine['room'] !== '' && trim($existing['room']) === trim($routine['room']) && $timesOverlap;

        if ($timesOverlap || $roomConflict) {
            $conflictFound = true;
            break;
        }
    }

    if (!$conflictFound) {
        $filtered_teacher_routines[] = $routine;
    }
}
$teacher_routines = $filtered_teacher_routines;

// ------------------------------------------------------------------
// 6. Fetch class & group lists for dynamic dropdowns (only those where teacher has at least one subject)
// ------------------------------------------------------------------
$available_classes = [];
$available_groups = [];

// Fetch classes from teacher's assigned subjects
foreach ($allowed_combinations as $comb) {
    $available_classes[$comb['class_id']] = $comb['class_name'];
}

// Fetch ALL available groups/streams from the groups table
$groups_sql = "SELECT id, group_name FROM `groups` ORDER BY group_name";
$groups_res = mysqli_query($conn, $groups_sql);
if ($groups_res) {
    while ($group = mysqli_fetch_assoc($groups_res)) {
        $available_groups[$group['id']] = $group['group_name'];
    }
}

$available_classes = array_unique($available_classes);
$available_groups = array_unique($available_groups);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Result Management</title>
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: #1e2a3a;
            color: white;
            padding: 1.5rem;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 1.25rem 1.25rem 0 0 !important;
        }
        .btn-primary-custom {
            background: #2c3e66;
            border: none;
            border-radius: 2rem;
            padding: 0.5rem 1.5rem;
        }
        .form-select, .form-control {
            border-radius: 0.75rem;
            border: 1px solid #ced4da;
        }
        .table th {
            background: #f8fafc;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: 0.3s;
                z-index: 1050;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        .badge-pass { background: #d1fae5; color: #065f46; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .sidebar-section {
            margin-top: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #3a4a5a;
        }
        .sidebar-section h6 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #a0aec0;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
            letter-spacing: 0.5px;
        }
        .sidebar-item {
            font-size: 0.9rem;
            color: #e2e8f0;
            padding: 0.35rem 0;
            margin: 0.25rem 0;
        }
        .sidebar-badge {
            display: inline-block;
            background: #2d3748;
            color: #cbd5e0;
            padding: 0.25rem 0.5rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
            margin: 0.25rem 0.25rem 0.25rem 0;
        }
    </style>
</head>
<body>

<!-- Simple Sidebar -->
<div class="sidebar" id="sidebar">
    <h4 class="mb-4"><i class="fas fa-chalkboard-user me-2"></i> Teacher Panel</h4>
    <ul class="nav flex-column">
        <li class="nav-item mb-2"><a href="#profile-section" class="nav-link text-white"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
        <li class="nav-item mb-2"><a href="#result-section" class="nav-link text-white"><i class="fas fa-edit me-2"></i>Result Entry</a></li>
        <li class="nav-item mb-2"><a href="#my-results" class="nav-link text-white"><i class="fas fa-table-list me-2"></i>My Results</a></li>
        <li class="nav-item mb-2"><a href="#routine-section" class="nav-link text-white"><i class="fas fa-calendar-week me-2"></i>View Routine</a></li>
        <li class="nav-item mb-2"><a href="teacher-announcements.php" class="nav-link text-white"><i class="fas fa-bullhorn me-2"></i>Announcements</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
    <hr class="bg-secondary">
    
    <!-- Teacher Info Section -->
    <div class="sidebar-section">
        <h6><i class="fas fa-user-circle me-1"></i> Teacher Info</h6>
        <div class="sidebar-item">
            <strong><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></strong><br>
            <span class="text-white-50" style="font-size: 0.85rem;">ID: <?= $teacher_id ?></span>
        </div>
    </div>

    <!-- Assigned Subjects Section -->
    <div class="sidebar-section">
        <h6><i class="fas fa-book me-1"></i> Assigned Subjects</h6>
        <?php 
        $subjects_by_class = [];
        foreach ($allowed_combinations as $combo) {
            if (!isset($subjects_by_class[$combo['class_name']])) {
                $subjects_by_class[$combo['class_name']] = [];
            }
            $subjects_by_class[$combo['class_name']][] = $combo['subject_name'];
        }
        
        if (empty($subjects_by_class)): 
        ?>
            <div class="text-white-50" style="font-size: 0.9rem;">No subjects assigned</div>
        <?php else: ?>
            <?php foreach ($subjects_by_class as $class_name => $subjects): ?>
                <div class="sidebar-item">
                    <strong style="color: #38b6ff;"><?= htmlspecialchars($class_name) ?></strong><br>
                    <?php foreach ($subjects as $subject): ?>
                        <span class="sidebar-badge"><?= htmlspecialchars($subject) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Classes Section -->
    <div class="sidebar-section">
        <h6><i class="fas fa-graduation-cap me-1"></i> Classes</h6>
        <?php 
        if (empty($available_classes)): 
        ?>
            <div class="text-white-50" style="font-size: 0.9rem;">No classes assigned</div>
        <?php else: ?>
            <?php foreach ($available_classes as $cid => $cname): ?>
                <div class="sidebar-item">
                    <span class="sidebar-badge" style="background: #4a5568; color: #63b3ed;">📚 <?= htmlspecialchars($cname) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Groups Section -->
    <div class="sidebar-section">
        <h6><i class="fas fa-users me-1"></i> Groups</h6>
        <?php 
        if (empty($available_groups)): 
        ?>
            <div class="text-white-50" style="font-size: 0.9rem;">No groups assigned</div>
        <?php else: ?>
            <?php foreach ($available_groups as $gid => $gname): ?>
                <div class="sidebar-item">
                    <span class="sidebar-badge" style="background: #44337a; color: #b19cd9;">👥 <?= htmlspecialchars($gname) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <button class="btn btn-dark d-md-none mb-3" id="menuToggle"><i class="fas fa-bars"></i> Menu</button>

    <!-- Success/Error Messages -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $success_msg ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $error_msg ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- === TEACHER PROFILE SECTION === -->
    <div class="card mb-4" id="profile-section">
        <div class="card-header">
            <i class="fas fa-user-circle me-2 text-primary"></i> My Profile
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3 mb-3 mb-md-0 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:120px; height:120px;">
                        <i class="fas fa-user fa-3x text-secondary"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <h5 class="mb-1"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h5>
                    <p class="text-muted mb-2">Teacher ID: <strong><?= htmlspecialchars($teacher['teacher_id'] ?? ('TCH' . $teacher_id)) ?></strong></p>
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <strong>Email</strong><br>
                            <?= htmlspecialchars($teacher['email'] ?? 'N/A') ?>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Phone</strong><br>
                            <?= htmlspecialchars($teacher['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Status</strong><br>
                            <?= htmlspecialchars(isset($teacher['status']) ? ($teacher['status'] ? 'Active' : 'Inactive') : 'N/A') ?>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Joined On</strong><br>
                            <?= htmlspecialchars(!empty($teacher['joining_date']) ? date('d-m-Y', strtotime($teacher['joining_date'])) : 'N/A') ?>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Qualification</strong><br>
                            <?= htmlspecialchars($teacher['qualification'] ?? 'N/A') ?>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Class Assignment</strong><br>
                            <?= htmlspecialchars($teacher['class_name'] ?? 'N/A') ?>
                        </div>
                        <div class="col-12">
                            <strong>Address</strong><br>
                            <?= nl2br(htmlspecialchars($teacher['address'] ?? 'N/A')) ?>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <h6 class="text-uppercase text-secondary small mb-3">Assigned Subjects</h6>
            <?php if (count($allowed_combinations) > 0): ?>
                <div class="row">
                    <?php foreach ($allowed_combinations as $combo): ?>
                        <div class="col-sm-6 col-lg-4 mb-2">
                            <div class="border rounded p-2">
                                <strong><?= htmlspecialchars($combo['subject_name']) ?></strong><br>
                                <span class="text-muted small"><?= htmlspecialchars($combo['class_name']) ?><?php if (!empty($combo['stream'])): ?> — <?= htmlspecialchars($combo['stream']) ?><?php endif; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No assigned subjects available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- === FILTER & STUDENT MARKS TABLE === -->
    <div class="card" id="result-section">
        <div class="card-header">
            <i class="fas fa-pen-alt me-2 text-primary"></i> Student Marks Entry
            <span class="badge bg-secondary float-end">Only your assigned subjects</span>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">📚 Class</label>
                    <select id="classSelect" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($available_classes as $cid => $cname): ?>
                            <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">👥 Group</label>
                    <select id="groupSelect" class="form-select" required disabled>
                        <option value="">First select class</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">📖 Subject (Assigned)</label>
                    <select id="subjectSelect" class="form-select" required disabled>
                        <option value="">Select class & group first</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">📝 Exam Type</label>
                    <select id="examTypeSelect" class="form-select" required disabled>
                        <option value="">Select subject first</option>
                        <option value="weekly_test">Weekly Test</option>
                        <option value="monthly_test">Monthly Test</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">📅 Exam Date</label>
                    <input type="date" id="examDateSelect" class="form-control" required disabled>
                </div>
            </div>

            <div id="tableContainer" style="display: none;">
                <div id="editNotice" class="alert alert-info d-none" role="alert">
                    Editing an existing submitted mark. Save will update the selected record.
                </div>
                <form method="POST" id="bulkResultForm">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="studentsMarksTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Group</th>
                                    <th>Exam Name</th>
                                    <th>Subject</th>
                                    <th>Exam Date</th>
                                    <th>Marks (0-100)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="marksTableBody">
                                <!-- Auto-populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary rounded-pill" id="saveBulkBtn">
                            <i class="fas fa-save me-1"></i> Save All Marks
                        </button>
                        <button type="reset" class="btn btn-secondary rounded-pill">
                            <i class="fas fa-redo me-1"></i> Clear All
                        </button>
                    </div>
                </form>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="text-center text-muted py-5">
                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3">Select filters to view and enter student marks</p>
            </div>
        </div>
    </div>

    <!-- === LIST OF EXISTING RESULTS (Only Teacher's Subjects) === -->
    <div class="card" id="my-results">
        <div class="card-header">
            <i class="fas fa-table me-2 text-primary"></i> Recently Added Marks
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Student</th><th>Class / Group</th><th>Subject</th><th>Exam Type</th><th>Marks</th><th>Exam Date</th><th>Status</th><th>Added Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_results) > 0): ?>
                            <?php foreach ($recent_results as $res): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($res['student_name']) ?></strong><br><small class="text-muted">Roll: <?= $res['roll_number'] ?></small></td>
                                    <td><?= htmlspecialchars($res['class_name']) ?> / <?= htmlspecialchars($res['group_name']) ?></td>
                                    <td><?= htmlspecialchars($res['subject_name']) ?></td>
                                    <td><small class="badge bg-info"><?= ($res['test_type'] === 'weekly_test') ? 'Weekly Test' : 'Monthly Test' ?></small></td>
                                    <td><span class="fw-bold"><?= $res['marks_obtained'] ?></span> / 100</td>
                                    <td><?= isset($res['exam_date']) && $res['exam_date'] ? date('d-m-Y', strtotime($res['exam_date'])) : '<span class="text-muted">N/A</span>' ?></td>
                                    <td><span class="badge <?= ($res['marks_obtained'] >= 40) ? 'badge-pass' : 'badge-fail' ?> px-3 py-2"><?= ($res['marks_obtained'] >= 40) ? 'Pass' : 'Fail' ?></span></td>
                                    <td><?= date('d-m-Y', strtotime($res['created_at'])) ?></td>
                                    <td><button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" onclick="loadResultForEdit(<?= $res['id'] ?>)"><i class="fas fa-pen"></i> Edit</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No results added yet. Use the form above to add marks.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- === VIEW TEACHER ROUTINE === -->
    <div class="card" id="routine-section">
        <div class="card-header">
            <i class="fas fa-calendar-week me-2 text-primary"></i> View Routine
        </div>
        <div class="card-body">
            <?php if (count($teacher_routines) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_routines as $routine): ?>
                                <tr>
                                    <td><?= htmlspecialchars($routine['day']) ?></td>
                                    <td><?= htmlspecialchars($routine['class_name']) ?></td>
                                    <td><?= htmlspecialchars($routine['subject_name']) ?></td>
                                    <td><?= htmlspecialchars(date('h:i A', strtotime($routine['start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($routine['end_time']))) ?></td>
                                    <td><?= htmlspecialchars($routine['room']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3 mb-0">No class routine assigned yet.</p>
                    <p class="small text-muted">Ask admin to create your routine from the class routine manager.</p>
                </div>
            <?php endif; ?>
            <?php if (!empty($missing_routine_text)): ?>
                <div class="alert alert-warning small mt-4">
                    <strong>No routine defined for:</strong> <?= $missing_routine_text ?>.
                    Ask admin to add routine entries for these assigned subjects.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ------------------------------------------------------------------
// Dynamic filtering & table population
// ------------------------------------------------------------------
const allowedCombinations = <?php 
    $arr = [];
    foreach ($allowed_combinations as $ac) {
        $arr[] = [
            'class_id' => $ac['class_id'],
            'subject_id' => $ac['subject_id'],
            'subject_name' => $ac['subject_name'],
            'class_name' => $ac['class_name'],
            'stream' => $ac['stream']
        ];
    }
    echo json_encode($arr);
?>;

const studentsData = <?php echo json_encode($students_list); ?>;

const availableGroups = <?php 
    $arr = [];
    foreach ($available_groups as $gid => $gname) {
        $arr[] = [
            'id' => $gid,
            'name' => $gname
        ];
    }
    echo json_encode($arr);
?>;

// DEBUG: Log available data
console.log('DEBUG - Allowed Combinations:', allowedCombinations);
console.log('DEBUG - Students Data:', studentsData);
console.log('DEBUG - Available Groups:', availableGroups);
console.log('DEBUG - Combinations count:', allowedCombinations.length);
console.log('DEBUG - Students count:', studentsData.length);
console.log('DEBUG - Groups count:', availableGroups.length);

// Get DOM elements
const classSelect = document.getElementById('classSelect');
const groupSelect = document.getElementById('groupSelect');
const subjectSelect = document.getElementById('subjectSelect');
const examTypeSelect = document.getElementById('examTypeSelect');
const examDateSelect = document.getElementById('examDateSelect');
const tableContainer = document.getElementById('tableContainer');
const emptyState = document.getElementById('emptyState');
const marksTableBody = document.getElementById('marksTableBody');
const bulkResultForm = document.getElementById('bulkResultForm');
const saveBulkBtn = document.getElementById('saveBulkBtn');

// Store current selections
let currentClassId = null;
let currentGroupId = null;
let currentSubjectId = null;
let currentExamType = null;
let currentExamDate = null;
let currentFilteredStudents = [];
let currentEditRecord = null;

function resetEditState() {
    currentEditRecord = null;
    const editNotice = document.getElementById('editNotice');
    if (editNotice) {
        editNotice.classList.add('d-none');
    }
}

// Helper: filter allowed groups for selected class
function updateGroups() {
    const classId = parseInt(classSelect.value);
    if (currentEditRecord && currentEditRecord.class_id !== classId) {
        resetEditState();
    }

    if (!classId) {
        groupSelect.disabled = true;
        groupSelect.innerHTML = '<option value="">-- Select Group --</option>';
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Select class & group first</option>';
        examTypeSelect.disabled = true;
        examTypeSelect.value = '';
        hideTable();
        return;
    }
    currentClassId = classId;

    // Derive sections/streams from teacher-assigned subjects for the selected class
    const classStreams = [...new Set(allowedCombinations
        .filter(c => c.class_id == classId)
        .map(c => c.stream)
        .filter(stream => stream && stream.trim() !== ''))];

    if (classStreams.length === 0) {
        groupSelect.disabled = true;
        groupSelect.innerHTML = '<option value="">No assigned subject section for this class</option>';
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Select class first</option>';
        examTypeSelect.disabled = true;
        examTypeSelect.value = '';
        hideTable();
        return;
    }

    groupSelect.disabled = false;
    let options = '<option value="">-- Select Group --</option>';
    classStreams.forEach(streamName => {
        const safeName = escapeHtml(streamName);
        options += `<option value="${safeName}">${safeName}</option>`;
    });
    groupSelect.innerHTML = options;

    // Reset dependent fields
    subjectSelect.disabled = true;
    subjectSelect.innerHTML = '<option value="">Select group first</option>';
    examTypeSelect.disabled = true;
    examTypeSelect.value = '';
    hideTable();
}

function updateSubjects() {
    const classId = parseInt(classSelect.value);
    const selectedStream = groupSelect.value;
    if (!classId || !selectedStream) {
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Select class & group</option>';
        examTypeSelect.disabled = true;
        hideTable();
        return;
    }

    currentGroupId = null;

    // Filter subjects for this class + stream from allowedCombinations
    const subjects = allowedCombinations.filter(c => c.class_id == classId && c.stream === selectedStream);
    if (subjects.length === 0) {
        subjectSelect.innerHTML = '<option value="">No subjects assigned for this class and section</option>';
        subjectSelect.disabled = true;
        return;
    }
    subjectSelect.disabled = false;
    let options = '<option value="">-- Select Subject --</option>';
    subjects.forEach(subj => {
        options += `<option value="${subj.subject_id}">${escapeHtml(subj.subject_name)}</option>`;
    });
    subjectSelect.innerHTML = options;

    // Reset exam type
    examTypeSelect.disabled = true;
    examTypeSelect.value = '';
    hideTable();
    currentSubjectId = null;
}

function updateExamType() {
    const subjectId = parseInt(subjectSelect.value);
    if (!subjectId) {
        examTypeSelect.disabled = true;
        examTypeSelect.value = '';
        examDateSelect.disabled = true;
        examDateSelect.value = '';
        hideTable();
        return;
    }
    currentSubjectId = subjectId;
    examTypeSelect.disabled = false;
    // Set default exam type to weekly_test
    if (!examTypeSelect.value) {
        examTypeSelect.value = 'weekly_test';
    }
    // Enable exam date field as well
    examDateSelect.disabled = false;
    // Trigger table update immediately after subject selection
    updateTable();
}

function updateTable() {
    const classId = parseInt(classSelect.value);
    const groupValue = groupSelect.value;
    const subjectId = parseInt(subjectSelect.value);
    const examType = examTypeSelect.value || 'weekly_test';
    const examDate = examDateSelect.value;

    // Require at minimum: class, group, and subject
    if (!classId || !groupValue || !subjectId) {
        hideTable();
        return;
    }

    currentExamType = examType;
    currentExamDate = examDate;
    
    // Filter students for this class by student group stream
    currentFilteredStudents = studentsData.filter(s => {
        if (s.class_id != classId) {
            return false;
        }
        if (groupValue === 'unassigned') {
            return s.group_id === null || s.group_id === 0;
        }
        return String(s.group_name).trim().toLowerCase() === String(groupValue).trim().toLowerCase();
    });

    if (currentFilteredStudents.length === 0) {
        hideTable();
        return;
    }

    // Populate table
    populateMarksTable();
    showTable();
}

function populateMarksTable() {
    const classId = parseInt(classSelect.value);
    const groupId = parseInt(groupSelect.value);
    const subjectId = parseInt(subjectSelect.value);
    const examType = examTypeSelect.value;
    const examDate = examDateSelect.value;

    // Get subject and class names
    const subject = allowedCombinations.find(c => c.subject_id == subjectId);
    const subjectName = subject ? subject.subject_name : '';

    let html = '';
    currentFilteredStudents.forEach((student, index) => {
        const rowId = `row_${student.id}_${subjectId}`;
        const isEditRow = currentEditRecord && currentEditRecord.student_id === student.id && currentEditRecord.subject_id === subjectId;
        const editResultId = isEditRow ? currentEditRecord.id : null;
        const editMarks = isEditRow ? currentEditRecord.marks_obtained : '';
        const editExamDate = isEditRow && currentEditRecord.exam_date ? currentEditRecord.exam_date : examDate;
        const editExamType = isEditRow ? currentEditRecord.test_type : examType;

        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(student.student_name)}</strong>
                    <br><small class="text-muted">Roll: ${student.roll_number}</small>
                </td>
                <td>${escapeHtml(student.class_name)}</td>
                <td>${escapeHtml(student.group_name)}</td>
                <td>
                    <select name="exam_type[${student.id}]" class="form-select form-select-sm">
                        <option value="weekly_test" ${editExamType === 'weekly_test' ? 'selected' : ''}>Weekly Test</option>
                        <option value="monthly_test" ${editExamType === 'monthly_test' ? 'selected' : ''}>Monthly Test</option>
                    </select>
                </td>
                <td>${escapeHtml(subjectName)}</td>
                <td>
                    <input type="date" 
                           name="exam_date[${student.id}]" 
                           class="form-control form-control-sm"
                           value="${editExamDate}"
                           data-student-id="${student.id}">
                </td>
                <td>
                    <input type="number" min="0" max="100" step="any" 
                           name="marks[${student.id}]" 
                           class="form-control form-control-sm marks-input"
                           placeholder="0" 
                           value="${editMarks}"
                           data-student-id="${student.id}"
                           data-subject-id="${subjectId}">
                    ${editResultId ? `<input type="hidden" name="result_id[${student.id}]" value="${editResultId}">` : ''}
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" 
                            onclick="clearRow(this)">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </td>
            </tr>
        `;
    });
    marksTableBody.innerHTML = html;
}

function showTable() {
    tableContainer.style.display = 'block';
    emptyState.style.display = 'none';
}

function hideTable() {
    tableContainer.style.display = 'none';
    emptyState.style.display = 'block';
    marksTableBody.innerHTML = '';
}

function clearRow(btn) {
    const row = btn.closest('tr');
    const marksInput = row.querySelector('.marks-input');
    marksInput.value = '';
    marksInput.focus();
}

function loadResultForEdit(resultId) {
    fetch('get-result.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + encodeURIComponent(resultId)
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text || response.statusText); });
        }
        return response.json();
    })
    .then(data => {
        console.log('Result data loaded:', data);

        currentEditRecord = {
            id: data.id,
            student_id: data.student_id,
            subject_id: data.subject_id,
            test_type: data.test_type,
            marks_obtained: data.marks_obtained,
            exam_date: data.exam_date,
            class_id: data.class_id,
            group_id: data.group_id,
            group_name: data.group_name || ''
        };

        const editNotice = document.getElementById('editNotice');
        if (editNotice) {
            editNotice.textContent = 'Editing submitted marks for ' + data.student_name + ' (' + data.subject_name + ').';
            editNotice.classList.remove('d-none');
        }

        classSelect.value = data.class_id;
        updateGroups();

        setTimeout(() => {
            const subjectMatch = allowedCombinations.find(c => c.subject_id == data.subject_id);
            const selectedGroup = subjectMatch && subjectMatch.stream ? subjectMatch.stream : (data.group_name ? data.group_name : ((data.group_id === null || data.group_id === 0) ? 'unassigned' : data.group_id));
            groupSelect.value = selectedGroup;
            if (!groupSelect.value && selectedGroup) {
                // Try a more lenient match in case casing or formatting differs
                const normalizedSelected = String(selectedGroup).trim().toLowerCase();
                for (const opt of groupSelect.options) {
                    if (opt.value.trim().toLowerCase() === normalizedSelected) {
                        groupSelect.value = opt.value;
                        break;
                    }
                }
            }
            updateSubjects();

            setTimeout(() => {
                subjectSelect.value = data.subject_id;
                examTypeSelect.value = data.test_type || 'weekly_test';
                examDateSelect.value = data.exam_date || '';

                updateExamType();
                updateTable();

                setTimeout(() => {
                    const marksInput = document.querySelector(`input[name="marks[${data.student_id}]"]`);
                    if (marksInput) {
                        marksInput.value = data.marks_obtained;
                        marksInput.focus();
                        marksInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 100);
            }, 100);
        }, 100);
    })
    .catch(error => {
        console.error('Error loading result:', error);
        alert('Error loading result for editing. Please try again.');
    });
}


function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event Listeners
classSelect.addEventListener('change', () => {
    updateGroups();
});

groupSelect.addEventListener('change', () => {
    const selectedGroup = groupSelect.value;
    if (currentEditRecord && currentEditRecord.group_name !== selectedGroup) {
        resetEditState();
    }
    updateSubjects();
});

subjectSelect.addEventListener('change', () => {
    if (currentEditRecord && parseInt(subjectSelect.value) !== currentEditRecord.subject_id) {
        resetEditState();
    }
    updateExamType();
});

examTypeSelect.addEventListener('change', () => {
    updateTable();
});

examDateSelect.addEventListener('change', () => {
    updateTable();
});

// Handle bulk form submission
bulkResultForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const classId = parseInt(classSelect.value);
    const groupId = parseInt(groupSelect.value);
    const subjectId = parseInt(subjectSelect.value);
    const examType = examTypeSelect.value;
    const examDate = examDateSelect.value;

    // Collect all marks data
    const marksData = [];
    currentFilteredStudents.forEach(student => {
        const marksInput = document.querySelector(`input[name="marks[${student.id}]"]`);
        const examDateInput = document.querySelector(`input[name="exam_date[${student.id}]"]`);
        const examTypeInput = document.querySelector(`select[name="exam_type[${student.id}]"]`);
        const resultIdInput = document.querySelector(`input[name="result_id[${student.id}]"]`);
        
        if (marksInput && marksInput.value !== '') {
            const marks = parseFloat(marksInput.value);
            if (marks >= 0 && marks <= 100) {
                const item = {
                    student_id: student.id,
                    subject_id: subjectId,
                    exam_type: examTypeInput ? examTypeInput.value : examType,
                    marks: marks,
                    exam_date: examDateInput ? examDateInput.value : examDate
                };
                if (resultIdInput && resultIdInput.value) {
                    item.result_id = parseInt(resultIdInput.value);
                }
                marksData.push(item);
            }
        }
    });

    if (marksData.length === 0) {
        alert('Please enter marks for at least one student.');
        return;
    }

    // Send to server
    try {
        const response = await fetch('save-teacher-marks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ marks: marksData })
        });

        // Check if response is ok
        if (!response.ok) {
            const text = await response.text();
            console.error('Server error:', text);
            alert('Server error ' + response.status + ':\n' + text);
            return;
        }

        // Get response text first to debug any issues
        const responseText = await response.text();
        console.log('Response:', responseText);
        
        // Try to parse JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.error('Response was:', responseText);
            alert('Error parsing response: ' + jsonError.message + '\nRaw response: ' + responseText);
            return;
        }

        if (result.success) {
            alert('Marks saved successfully!');
            bulkResultForm.reset();
            hideTable();
            // Reload page to show in recent results
            setTimeout(() => location.reload(), 500);
        } else {
            const errorMsg = result.message || 'Unknown error';
            const errors = result.errors ? '\n\nDetails:\n' + result.errors.join('\n') : '';
            alert('Error: ' + errorMsg + errors);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while saving marks: ' + error.message);
    }
});

// Initial state
hideTable();

// Sidebar toggle for mobile
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>

<?php
// ----- Handler for saving manual marks -----
// NOTE: Moved to separate file: save-manual-marks.php
// This keeps the JSON response clean without HTML interference
/*
if (isset($_GET['save_manual_marks']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... handler code moved to save-manual-marks.php
}
*/

// ----- AJAX handler for fetching existing marks -----
// NOTE: This handler is now in save-manual-marks.php if needed
/*
if (isset($_GET['ajax_get_mark']) && isset($_GET['student_id']) && isset($_GET['subject_id'])) {
    // ... handler code moved if needed
}
*/
?>

<script>
// Override the AJAX call to use the same file with a GET parameter
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    if (url.toString().includes('ajax_get_mark.php')) {
        const urlParams = new URLSearchParams(url.split('?')[1]);
        const newUrl = window.location.pathname + '?ajax_get_mark=1&student_id=' + urlParams.get('student_id') + '&subject_id=' + urlParams.get('subject_id');
        return originalFetch(newUrl, options);
    }
    return originalFetch(url, options);
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>