<?php
session_start();
require_once '../includes/db.php';

// Check if class_id column exists in teacher_subjects table
$teacherSubjectsClassIdColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM teacher_subjects LIKE 'class_id'");
$teacherSubjectsClassIdColumnExists = ($teacherSubjectsClassIdColumnCheck && mysqli_num_rows($teacherSubjectsClassIdColumnCheck) > 0);

/**
 * Auto-assign preferred subjects for a teacher based on a free-text list.
 *
 * @param \mysqli $conn MySQLi connection object
 * @param int $teacher_id Teacher database ID
 * @param string $assigned_subjects Comma/newline-separated preferred subject names
 * @param int[]|null $class_ids Optional array of class IDs to constrain subject search
 * @return void
 */
function autoAssignPreferredSubjects(\mysqli $conn, int $teacher_id, string $assigned_subjects, ?array $class_ids = null): void {
    global $teacherSubjectsClassIdColumnExists;

    $assigned_subjects = trim($assigned_subjects);
    if($assigned_subjects === '') {
        return;
    }

    if (is_array($class_ids)) {
        $class_ids = array_values(array_filter(array_map('intval', $class_ids), function($value) {
            return $value > 0;
        }));
    } else {
        $class_ids = $class_ids ? [intval($class_ids)] : [];
    }

    if ($teacherSubjectsClassIdColumnExists && !empty($class_ids)) {
        $validatedClassIds = [];
        $class_ids_list = implode(',', array_map('intval', $class_ids));
        $validClassesRes = mysqli_query($conn, "SELECT id FROM classes WHERE id IN ($class_ids_list)");
        if ($validClassesRes) {
            while ($classRow = mysqli_fetch_assoc($validClassesRes)) {
                $validatedClassIds[] = intval($classRow['id']);
            }
        }
        $class_ids = $validatedClassIds;
    }

    $mapping = [
        'bangla' => ['bangla 1st paper', 'bangla 2nd paper'],
        'english' => ['english 1st paper', 'english 2nd paper'],
        'math' => ['general mathematics', 'higher mathematics', 'business mathematics'],
        'mathematics' => ['general mathematics', 'higher mathematics', 'business mathematics'],
        'general mathematics' => ['general mathematics'],
        'higher mathematics' => ['higher mathematics'],
        'business mathematics' => ['business mathematics'],
    ];

    $terms = preg_split('/[\n\r,;]+/', strtolower($assigned_subjects));
    $terms = array_filter(array_map('trim', $terms));
    $terms = array_unique($terms);

    foreach($terms as $term) {
        if($term === '') {
            continue;
        }

        $searchTerms = $mapping[$term] ?? [$term];
        foreach($searchTerms as $searchTerm) {
            $keyword = mysqli_real_escape_string($conn, $searchTerm);
            $classFilter = '';
            if ($teacherSubjectsClassIdColumnExists && !empty($class_ids)) {
                $classFilter = ' AND class_id IN (' . implode(',', array_map('intval', $class_ids)) . ')';
            }

            $subject_query = mysqli_query($conn, "SELECT id, class_id FROM subjects WHERE LOWER(subject_name) LIKE '%$keyword%'$classFilter");
            if(!$subject_query) {
                continue;
            }

            while($subject = mysqli_fetch_assoc($subject_query)) {
                $subject_id = intval($subject['id']);
                $subject_class_id = intval($subject['class_id']);
                $check_query = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id";
                if ($teacherSubjectsClassIdColumnExists) {
                    $check_query .= " AND class_id = $subject_class_id";
                }
                $check_query .= " LIMIT 1";

                $check_result = mysqli_query($conn, $check_query);
                if($check_result && mysqli_num_rows($check_result) === 0) {
                    $columns = "teacher_id, subject_id";
                    $values = "$teacher_id, $subject_id";
                    if ($teacherSubjectsClassIdColumnExists) {
                        $columns .= ", class_id";
                        $values .= ", " . ($subject_class_id ? $subject_class_id : 'NULL');
                    }
                    $insert_query = "INSERT INTO teacher_subjects ($columns) VALUES ($values)";
                    mysqli_query($conn, $insert_query);
                }
            }
        }
    }
}

if(isset($_POST['assign'])) {
    $teacher_id = intval($_POST['teacher_id']);
    if($teacher_id <= 0) {
        $_SESSION['error'] = "Invalid teacher selected for assignment.";
        header("Location: teacher-management.php");
        exit();
    }
    $subjects = $_POST['subjects'] ?? [];
    $subjects = array_map('intval', $subjects);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete existing assignments
        $delete_query = "DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id";
        if(!mysqli_query($conn, $delete_query)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Insert new assignments
        if(!empty($subjects)) {
            foreach($subjects as $subject_id) {
                $subject_id = intval($subject_id);
                $class_id = null;
                $class_result = mysqli_query($conn, "SELECT class_id FROM subjects WHERE id = $subject_id LIMIT 1");
                if(!$class_result) {
                    throw new Exception(mysqli_error($conn));
                }
                if($class_row = mysqli_fetch_assoc($class_result)) {
                    $class_id = intval($class_row['class_id']);
                }
                
                // Build INSERT query conditionally
                global $teacherSubjectsClassIdColumnExists;
                $columns = "teacher_id, subject_id";
                $values = "$teacher_id, $subject_id";
                
                if ($teacherSubjectsClassIdColumnExists) {
                    $columns .= ", class_id";
                    $values .= ", " . ($class_id ? $class_id : 'NULL');
                }
                
                $insert_query = "INSERT INTO teacher_subjects ($columns) VALUES ($values)";
                if(!mysqli_query($conn, $insert_query)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
        }
        
        // Automatically add preferred subjects assignments such as Bangla (only if assigned_subjects column exists)
        $assigned_subjects_column_check = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'assigned_subjects'");
        if($assigned_subjects_column_check && mysqli_num_rows($assigned_subjects_column_check) > 0) {
            $teacher_query = mysqli_query($conn, "SELECT assigned_subjects FROM teachers WHERE id = $teacher_id LIMIT 1");
            if($teacher_query && $teacher_row = mysqli_fetch_assoc($teacher_query)) {
                autoAssignPreferredSubjects($conn, $teacher_id, $teacher_row['assigned_subjects']);
            }
        }
        
        // Persist assigned subjects to teacher assigned_subjects column if it exists
        $subjects_column_check = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'assigned_subjects'");
        if($subjects_column_check && mysqli_num_rows($subjects_column_check) > 0) {
            $subject_names = [];
            $class_ids_for_teacher = [];
            $class_names_for_teacher = [];

            if(!empty($subjects)) {
                $subject_ids = implode(',', $subjects);
                $subject_query = mysqli_query($conn, "SELECT s.subject_name, s.class_id, c.class_name FROM subjects s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id IN ($subject_ids)");
                if(!$subject_query) {
                    throw new Exception(mysqli_error($conn));
                }
                while($sub = mysqli_fetch_assoc($subject_query)) {
                    $subject_names[] = $sub['subject_name'];
                    if(!empty($sub['class_id'])) {
                        $class_ids_for_teacher[] = intval($sub['class_id']);
                    }
                    if(!empty($sub['class_name'])) {
                        $class_names_for_teacher[] = $sub['class_name'];
                    }
                }
            }

            $subject_list = implode(', ', $subject_names);
            $subject_list_safe = mysqli_real_escape_string($conn, $subject_list);
            if(!mysqli_query($conn, "UPDATE teachers SET assigned_subjects = '$subject_list_safe' WHERE id = $teacher_id")) {
                throw new Exception(mysqli_error($conn));
            }

            $unique_class_ids = array_values(array_unique($class_ids_for_teacher));
            $unique_class_names = array_values(array_unique($class_names_for_teacher));
            $class_name_list = mysqli_real_escape_string($conn, implode(', ', $unique_class_names));
            $first_class_id = count($unique_class_ids) ? $unique_class_ids[0] : 'NULL';

            $class_update_query = "UPDATE teachers SET class_name = '$class_name_list'";
            if ($first_class_id !== 'NULL') {
                $class_update_query .= ", class_id = $first_class_id";
            } else {
                $class_update_query .= ", class_id = NULL";
            }
            $class_update_query .= " WHERE id = $teacher_id";

            if(!mysqli_query($conn, $class_update_query)) {
                throw new Exception(mysqli_error($conn));
            }
        } else {
            // If assigned_subjects column does not exist, still update teacher class_name/class_id
            $class_ids_for_teacher = [];
            $class_names_for_teacher = [];
            if(!empty($subjects)) {
                $subject_ids = implode(',', $subjects);
                $subject_query = mysqli_query($conn, "SELECT s.class_id, c.class_name FROM subjects s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id IN ($subject_ids)");
                if(!$subject_query) {
                    throw new Exception(mysqli_error($conn));
                }
                while($sub = mysqli_fetch_assoc($subject_query)) {
                    if(!empty($sub['class_id'])) {
                        $class_ids_for_teacher[] = intval($sub['class_id']);
                    }
                    if(!empty($sub['class_name'])) {
                        $class_names_for_teacher[] = $sub['class_name'];
                    }
                }
            }

            $unique_class_ids = array_values(array_unique($class_ids_for_teacher));
            $unique_class_names = array_values(array_unique($class_names_for_teacher));
            $class_name_list = mysqli_real_escape_string($conn, implode(', ', $unique_class_names));
            $first_class_id = count($unique_class_ids) ? $unique_class_ids[0] : 'NULL';

            $class_update_query = "UPDATE teachers SET class_name = '$class_name_list'";
            if ($first_class_id !== 'NULL') {
                $class_update_query .= ", class_id = $first_class_id";
            } else {
                $class_update_query .= ", class_id = NULL";
            }
            $class_update_query .= " WHERE id = $teacher_id";

            if(!mysqli_query($conn, $class_update_query)) {
                throw new Exception(mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "Subjects assigned successfully!";
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error assigning subjects: " . $e->getMessage();
    }
    
    header("Location: teacher-management.php");
    exit();
}

// Get teacher_id from URL parameter
$selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Get all teachers
$teachers_query = "SELECT id, first_name, last_name FROM teachers ORDER BY first_name, last_name";
$teachers_result = mysqli_query($conn, $teachers_query);

// Get all subjects (only when a teacher is selected)
$subjects_data = [];
$assigned_subject_ids = [];

if($selected_teacher_id > 0) {
    // Determine which classes the selected teacher is assigned to
    $selected_class_ids = [];
    $teacher_info_query = mysqli_query($conn, "SELECT class_id, class_name FROM teachers WHERE id = $selected_teacher_id LIMIT 1");
    if($teacher_info_query && $teacher_info = mysqli_fetch_assoc($teacher_info_query)) {
        if(!empty($teacher_info['class_id'])) {
            $selected_class_ids[] = intval($teacher_info['class_id']);
        }

        if(!empty($teacher_info['class_name'])) {
            $class_names = array_filter(array_map('trim', explode(',', $teacher_info['class_name'])));
            if(!empty($class_names)) {
                $escapedNames = array_map(function($name) use ($conn) {
                    return "'" . mysqli_real_escape_string($conn, $name) . "'";
                }, $class_names);
                $nameList = implode(',', $escapedNames);
                $class_ids_result = mysqli_query($conn, "SELECT id FROM classes WHERE class_name IN ($nameList)");
                if($class_ids_result) {
                    while($classRow = mysqli_fetch_assoc($class_ids_result)) {
                        $selected_class_ids[] = intval($classRow['id']);
                    }
                }
            }
        }
    }

    $selected_class_ids = array_values(array_unique($selected_class_ids));

    if(!empty($selected_class_ids)) {
        $class_ids_str = implode(',', $selected_class_ids);
        $subjects_query = "SELECT s.id, s.subject_name, s.stream, c.class_name, c.id as class_id
                           FROM subjects s
                           JOIN classes c ON s.class_id = c.id
                           WHERE s.class_id IN ($class_ids_str)
                           ORDER BY c.id,
                                   FIELD(s.stream, 'Science', 'Commerce', 'Humanities'),
                                   s.subject_name";
        $subjects_result = mysqli_query($conn, $subjects_query);
        if($subjects_result) {
            while($row = mysqli_fetch_assoc($subjects_result)) {
                $subjects_data[] = $row;
            }
        }
    }

    // Get already assigned subjects for the selected teacher
    $assigned_query = "SELECT subject_id FROM teacher_subjects WHERE teacher_id = $selected_teacher_id";
    $assigned_result = mysqli_query($conn, $assigned_query);
    if($assigned_result) {
        while($row = mysqli_fetch_assoc($assigned_result)) {
            $assigned_subject_ids[] = $row['subject_id'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fc;
        }

        .wrapper {
            display: flex;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            min-height: 100vh;
            color: white;
            position: fixed;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-menu a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            margin-bottom: 5px;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #4CAF50;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: calc(100% - 280px);
        }

        .top-navbar {
            background: white;
            padding: 20px 30px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h4 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .content-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .form-control, .form-select {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: none;
        }

        .subjects-container {
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 20px;
            background: #f9f9f9;
            max-height: 500px;
            overflow-y: auto;
        }

        .subject-checkbox {
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border-left: 3px solid #e1e5e9;
            transition: all 0.3s;
        }

        .subject-checkbox:hover {
            border-left-color: #667eea;
            background: #f0f5ff;
        }

        .subject-checkbox input[type="checkbox"] {
            margin-right: 10px;
        }

        .subject-checkbox label {
            margin: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            width: 100%;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            margin-right: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #5a6268;
            color: white;
        }

        .class-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e5e9;
        }

        .class-group:last-child {
            border-bottom: none;
        }

        .class-group-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stream-group {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .stream-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            opacity: 0.9;
        }

        .stream-group .subject-checkbox {
            margin-left: 8px;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border: none;
            border-radius: 5px;
        }

        .stream-group .subject-checkbox:hover {
            background: rgba(255, 255, 255, 0.7);
        }

        .select-all-btn {
            background: white;
            border: 1px solid #667eea;
            color: #667eea;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }

        .select-all-btn:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
            .sidebar-header h3, .sidebar-menu span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-3x"></i>
                <h3>CoachingPro</h3>
                <small>Admin Panel</small>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="teacher-management.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    <span>Teacher Management</span>
                </a>
                <a href="assign-subjects.php" class="active">
                    <i class="fas fa-book me-2"></i>
                    <span>Assign Subjects</span>
                </a>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="page-title">
                    <h4><i class="fas fa-book me-2"></i>Assign Subjects to Teachers</h4>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Content Card -->
            <div class="content-card">
                <h5 class="card-title">Select Teacher and Assign Subjects</h5>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">Select Teacher *</label>
                        <select class="form-select" name="teacher_id" id="teacher_id" required onchange="loadAssignedSubjects()">
                            <option value="">-- Select a Teacher --</option>
                            <?php while($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo ($teacher['id'] == $selected_teacher_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) . ' (ID: ' . $teacher['id'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if($selected_teacher_id > 0): ?>
                        <div class="mb-4">
                            <label class="form-label">Select Subjects *</label>
                            <div style="margin-bottom: 15px;">
                                <button type="button" class="select-all-btn" onclick="selectAllSubjects()">Select All</button>
                                <button type="button" class="select-all-btn" onclick="deselectAllSubjects()" style="margin-left: 5px;">Deselect All</button>
                            </div>

                            <div class="subjects-container">
                                <?php 
                                if(!empty($subjects_data)):
                                    $current_class = '';
                                    $current_stream = '';
                                    foreach($subjects_data as $subject):
                                        // Check if class changed
                                        if($subject['class_name'] != $current_class): 
                                            // Close previous stream group
                                            if($current_stream != '') echo '</div>';
                                            // Close previous class group
                                            if($current_class != '') echo '</div>';
                                            
                                            $current_class = $subject['class_name'];
                                            $current_stream = '';
                                            echo '<div class="class-group"><div class="class-group-title">' . htmlspecialchars($current_class) . '</div>';
                                        endif;
                                        
                                        // Check if stream changed
                                        if($subject['stream'] != $current_stream):
                                            // Close previous stream group
                                            if($current_stream != '') echo '</div>';
                                            
                                            $current_stream = $subject['stream'];
                                            $stream_color = '';
                                            if($current_stream === 'Science') {
                                                $stream_color = 'style="border-left: 4px solid #1976d2; background: #e3f2fd;"';
                                            } elseif($current_stream === 'Commerce') {
                                                $stream_color = 'style="border-left: 4px solid #f57c00; background: #fff3e0;"';
                                            } else {
                                                $stream_color = 'style="border-left: 4px solid #7b1fa2; background: #f3e5f5;"';
                                            }
                                            echo '<div class="stream-group" ' . $stream_color . '><div class="stream-title">' . htmlspecialchars($current_stream) . ' Stream</div>';
                                        endif;
                                ?>
                                        <div class="subject-checkbox">
                                            <label>
                                                <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>"
                                                       <?php echo in_array($subject['id'], $assigned_subject_ids) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </label>
                                        </div>
                                <?php 
                                    endforeach; 
                                    // Close last open divs
                                    if($current_stream != '') { echo '</div>'; }
                                    if($current_class != '') { echo '</div>'; }
                                endif;
                                if(empty($subjects_data)):
                                ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No subjects available
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="assign" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Assignments
                            </button>
                            <a href="teacher-management.php" class="btn btn-back">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Please select a teacher to view and assign subjects.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAllSubjects() {
            document.querySelectorAll('input[name="subjects[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAllSubjects() {
            document.querySelectorAll('input[name="subjects[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        function loadAssignedSubjects() {
            const teacherId = document.getElementById('teacher_id').value;
            if(teacherId) {
                window.location.href = '?teacher_id=' + teacherId;
            }
        }
    </script>
</body>
</html>