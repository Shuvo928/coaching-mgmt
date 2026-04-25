<?php
session_start();
require_once '../includes/db.php'; // adjust path if needed

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

$assigned_subjects_query = "SELECT ts.subject_id, s.subject_name, s.class_id, c.class_name
                            FROM teacher_subjects ts
                            JOIN subjects s ON ts.subject_id = s.id
                            JOIN classes c ON s.class_id = c.id
                            WHERE ts.teacher_id = $teacher_id
                            ORDER BY c.class_name, s.subject_name";
$assigned_subjects = mysqli_query($conn, $assigned_subjects_query);

// Build a quick lookup for permission checks
$allowed_subject_ids = [];
$allowed_combinations = []; // for frontend filtering
while ($row = mysqli_fetch_assoc($assigned_subjects)) {
    $allowed_subject_ids[] = $row['subject_id'];
    $allowed_combinations[] = [
        'class_id' => $row['class_id'],
        'subject_id' => $row['subject_id'],
        'subject_name' => $row['subject_name'],
        'class_name' => $row['class_name']
    ];
}
// Reset pointer for later use
mysqli_data_seek($assigned_subjects, 0);

// ------------------------------------------------------------------
// 2. Handle Bulk Save / Update of marks
// ------------------------------------------------------------------
$success_msg = '';
$error_msg = '';

if (isset($_GET['save_bulk_marks']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['marks']) || !is_array($data['marks'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        exit;
    }
    
    $marks_to_save = $data['marks'];
    $saved_count = 0;
    $errors = [];
    
    foreach ($marks_to_save as $item) {
        $student_id = intval($item['student_id']);
        $subject_id = intval($item['subject_id']);
        $exam_type = mysqli_real_escape_string($conn, $item['exam_type']);
        $marks = floatval($item['marks']);
        
        // Validation
        if ($student_id <= 0 || $subject_id <= 0) {
            $errors[] = "Invalid student or subject ID";
            continue;
        }
        
        if ($marks < 0 || $marks > 100) {
            $errors[] = "Marks for student $student_id must be between 0 and 100";
            continue;
        }
        
        // Security: verify teacher is allowed to enter marks for this subject
        $check_sql = "SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
        $check_res = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_res) == 0) {
            $errors[] = "You are not allowed to add marks for subject ID $subject_id";
            continue;
        }
        
        // Check if result already exists (student_id + subject_id + exam_type)
        $exists_sql = "SELECT id FROM results WHERE student_id = $student_id AND subject_id = $subject_id AND exam_type = '$exam_type'";
        $exists_res = mysqli_query($conn, $exists_sql);
        
        if (mysqli_num_rows($exists_res) > 0) {
            // Update
            $update_sql = "UPDATE results SET marks = $marks, updated_at = NOW() 
                          WHERE student_id = $student_id AND subject_id = $subject_id AND exam_type = '$exam_type'";
            if (mysqli_query($conn, $update_sql)) {
                $saved_count++;
            } else {
                $errors[] = "Error updating marks for student $student_id: " . mysqli_error($conn);
            }
        } else {
            // Insert new
            $insert_sql = "INSERT INTO results (student_id, subject_id, exam_type, marks, created_at, updated_at) 
                          VALUES ($student_id, $subject_id, '$exam_type', $marks, NOW(), NOW())";
            if (mysqli_query($conn, $insert_sql)) {
                $saved_count++;
            } else {
                $errors[] = "Error inserting marks for student $student_id: " . mysqli_error($conn);
            }
        }
    }
    
    if ($saved_count > 0) {
        echo json_encode(['success' => true, 'message' => "Successfully saved $saved_count student mark(s)." . (count($errors) > 0 ? " Errors: " . implode(", ", $errors) : "")]);
    } else {
        echo json_encode(['success' => false, 'message' => "No marks were saved. " . implode(", ", $errors)]);
    }
    exit;
}

// ------------------------------------------------------------------
// 3. Fetch list of students (only classes where teacher has subjects)
// ------------------------------------------------------------------
$class_ids_allowed = array_unique(array_column($allowed_combinations, 'class_id'));
$class_ids_str = implode(',', $class_ids_allowed);
$students_list = [];
if (!empty($class_ids_str)) {
    $students_sql = "SELECT s.id, s.student_name, s.roll_number, s.class_id, s.group_id,
                            c.class_name, g.group_name
                     FROM students s
                     JOIN classes c ON s.class_id = c.id
                     JOIN `groups` g ON s.group_id = g.id
                     WHERE s.class_id IN ($class_ids_str)
                     ORDER BY c.class_name, g.group_name, s.roll_number";
    $students_res = mysqli_query($conn, $students_sql);
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
    $results_sql = "SELECT r.*, s.student_name, s.roll_number, sub.subject_name, c.class_name, g.group_name
                    FROM results r
                    JOIN students s ON r.student_id = s.id
                    JOIN subjects sub ON r.subject_id = sub.id
                    JOIN classes c ON sub.class_id = c.id
                    JOIN `groups` g ON s.group_id = g.id
                    WHERE r.subject_id IN ($allowed_subjects_str)
                    ORDER BY r.created_at DESC
                    LIMIT 50";
    $results_res = mysqli_query($conn, $results_sql);
    while ($row = mysqli_fetch_assoc($results_res)) {
        $recent_results[] = $row;
    }
}

// ------------------------------------------------------------------
// 5. Fetch class & group lists for dynamic dropdowns (only those where teacher has at least one subject)
// ------------------------------------------------------------------
$available_classes = [];
$available_groups = [];
foreach ($allowed_combinations as $comb) {
    $available_classes[$comb['class_id']] = $comb['class_name'];
}
// Get groups from students list (since groups are associated with students, not subjects)
foreach ($students_list as $student) {
    $available_groups[$student['group_id']] = $student['group_name'];
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
        <li class="nav-item mb-2"><a href="#result-section" class="nav-link text-white"><i class="fas fa-edit me-2"></i>Result Entry</a></li>
        <li class="nav-item mb-2"><a href="#my-results" class="nav-link text-white"><i class="fas fa-table-list me-2"></i>My Results</a></li>
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
                <div class="col-md-3">
                    <label class="form-label fw-semibold">👥 Group</label>
                    <select id="groupSelect" class="form-select" required disabled>
                        <option value="">First select class</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">📖 Subject (Assigned)</label>
                    <select id="subjectSelect" class="form-select" required disabled>
                        <option value="">Select class & group first</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">📝 Exam Type</label>
                    <select id="examTypeSelect" class="form-select" required disabled>
                        <option value="">Select subject first</option>
                        <option value="weekly_test">Weekly Test</option>
                        <option value="monthly_test">Monthly Test</option>
                    </select>
                </div>
            </div>

           

            <!-- Students Marks Table -->
            <div id="tableContainer" style="display: none;">
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
                        <tr><th>Student</th><th>Class / Group</th><th>Subject</th><th>Marks</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_results) > 0): ?>
                            <?php foreach ($recent_results as $res): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($res['student_name']) ?></strong><br><small class="text-muted">Roll: <?= $res['roll_number'] ?></small></td>
                                    <td><?= htmlspecialchars($res['class_name']) ?> / <?= htmlspecialchars($res['group_name']) ?></td>
                                    <td><?= htmlspecialchars($res['subject_name']) ?></td>
                                    <td><span class="fw-bold"><?= $res['marks'] ?></span> / 100</td>
                                    <td><span class="badge <?= ($res['marks'] >= 40) ? 'badge-pass' : 'badge-fail' ?> px-3 py-2"><?= ($res['marks'] >= 40) ? 'Pass' : 'Fail' ?></span></td>
                                    <td><?= date('d-m-Y', strtotime($res['updated_at'])) ?></td>
                                    <td><button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="loadForEdit(<?= $res['student_id'] ?>, <?= $res['subject_id'] ?>, <?= $res['marks'] ?>)"><i class="fas fa-pen"></i> Edit</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No results added yet. Use the form above to add marks.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
            'class_name' => $ac['class_name']
        ];
    }
    echo json_encode($arr);
?>;

const studentsData = <?php echo json_encode($students_list); ?>;

// Get DOM elements
const classSelect = document.getElementById('classSelect');
const groupSelect = document.getElementById('groupSelect');
const subjectSelect = document.getElementById('subjectSelect');
const examTypeSelect = document.getElementById('examTypeSelect');
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
let currentFilteredStudents = [];

// Helper: filter allowed groups for selected class
function updateGroups() {
    const classId = parseInt(classSelect.value);
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
    // Get unique groups for this class from studentsData
    const groupMap = {};
    studentsData.forEach(s => {
        if (s.class_id == classId) {
            groupMap[s.group_id] = s.group_name;
        }
    });
    
    const groupIds = Object.keys(groupMap);
    if (groupIds.length === 0) {
        groupSelect.innerHTML = '<option value="">No groups available</option>';
        groupSelect.disabled = true;
        return;
    }
    groupSelect.disabled = false;
    let options = '<option value="">-- Select Group --</option>';
    groupIds.forEach(gid => {
        options += `<option value="${gid}">${groupMap[gid]}</option>`;
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
    const groupId = parseInt(groupSelect.value);
    if (!classId || !groupId) {
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Select class & group</option>';
        examTypeSelect.disabled = true;
        hideTable();
        return;
    }
    currentGroupId = groupId;
    // Filter subjects for this class from allowedCombinations
    const subjects = allowedCombinations.filter(c => c.class_id == classId);
    if (subjects.length === 0) {
        subjectSelect.innerHTML = '<option value="">No subjects assigned for this class</option>';
        subjectSelect.disabled = true;
        return;
    }
    subjectSelect.disabled = false;
    let options = '<option value="">-- Select Subject --</option>';
    subjects.forEach(subj => {
        options += `<option value="${subj.subject_id}">${subj.subject_name}</option>`;
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
        hideTable();
        return;
    }
    currentSubjectId = subjectId;
    examTypeSelect.disabled = false;
}

function updateTable() {
    const classId = parseInt(classSelect.value);
    const groupId = parseInt(groupSelect.value);
    const subjectId = parseInt(subjectSelect.value);
    const examType = examTypeSelect.value;

    if (!classId || !groupId || !subjectId || !examType) {
        hideTable();
        return;
    }

    currentExamType = examType;
    
    // Filter students for this class + group
    currentFilteredStudents = studentsData.filter(s => 
        s.class_id == classId && s.group_id == groupId
    );

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

    // Get subject and class names
    const subject = allowedCombinations.find(c => c.subject_id == subjectId);
    const subjectName = subject ? subject.subject_name : '';

    let html = '';
    currentFilteredStudents.forEach((student, index) => {
        const rowId = `row_${student.id}_${subjectId}`;
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
                        <option value="weekly_test" ${examType === 'weekly_test' ? 'selected' : ''}>Weekly Test</option>
                        <option value="monthly_test" ${examType === 'monthly_test' ? 'selected' : ''}>Monthly Test</option>
                    </select>
                </td>
                <td>${escapeHtml(subjectName)}</td>
                <td>
                    <input type="number" min="0" max="100" step="any" 
                           name="marks[${student.id}]" 
                           class="form-control form-control-sm marks-input"
                           placeholder="0" 
                           data-student-id="${student.id}"
                           data-subject-id="${subjectId}">
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
    updateSubjects();
});

subjectSelect.addEventListener('change', () => {
    updateExamType();
});

examTypeSelect.addEventListener('change', () => {
    updateTable();
});

// Handle bulk form submission
bulkResultForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(bulkResultForm);
    const classId = parseInt(classSelect.value);
    const groupId = parseInt(groupSelect.value);
    const subjectId = parseInt(subjectSelect.value);
    const examType = examTypeSelect.value;

    // Collect all marks data
    const marksData = [];
    currentFilteredStudents.forEach(student => {
        const marksInput = document.querySelector(`input[name="marks[${student.id}]"]`);
        const examTypeSelect = document.querySelector(`select[name="exam_type[${student.id}]"]`);
        
        if (marksInput && marksInput.value !== '') {
            const marks = parseFloat(marksInput.value);
            if (marks >= 0 && marks <= 100) {
                marksData.push({
                    student_id: student.id,
                    subject_id: subjectId,
                    exam_type: examTypeSelect ? examTypeSelect.value : examType,
                    marks: marks
                });
            }
        }
    });

    if (marksData.length === 0) {
        alert('Please enter marks for at least one student.');
        return;
    }

    // Send to server
    try {
        const response = await fetch(window.location.pathname + '?save_bulk_marks=1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ marks: marksData })
        });

        const result = await response.json();
        if (result.success) {
            alert('Marks saved successfully!');
            bulkResultForm.reset();
            hideTable();
            // Reload page to show in recent results
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while saving marks.');
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
// ----- AJAX handler for fetching existing marks -----
if (isset($_GET['ajax_get_mark']) && isset($_GET['student_id']) && isset($_GET['subject_id'])) {
    header('Content-Type: application/json');
    $student_id = intval($_GET['student_id']);
    $subject_id = intval($_GET['subject_id']);
    // Security: only if teacher is allowed for this subject
    $check = mysqli_query($conn, "SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id");
    if (mysqli_num_rows($check) == 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $mark_sql = "SELECT marks FROM results WHERE student_id = $student_id AND subject_id = $subject_id";
    $mark_res = mysqli_query($conn, $mark_sql);
    if ($row = mysqli_fetch_assoc($mark_res)) {
        echo json_encode(['success' => true, 'marks' => $row['marks']]);
    } else {
        echo json_encode(['success' => true, 'marks' => null]);
    }
    exit;
}
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