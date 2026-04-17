<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['admin']);

// Handle routine save
if(isset($_POST['save_routine'])) {
    $class_id = intval($_POST['class_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $day = mysqli_real_escape_string($conn, trim($_POST['day'] ?? ''));
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $room = mysqli_real_escape_string($conn, trim($_POST['room'] ?? ''));

    if(!$class_id || !$subject_id || !$teacher_id || !$day || !$start_time || !$end_time || !$room) {
        $_SESSION['error'] = 'Please complete all routine fields.';
        header('Location: class-routine.php');
        exit();
    }

    $eligibility_check = mysqli_query($conn, "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id LIMIT 1");
    if(!$eligibility_check || mysqli_num_rows($eligibility_check) === 0) {
        $_SESSION['error'] = 'Selected teacher is not eligible for the chosen subject.';
        header('Location: class-routine.php');
        exit();
    }

    $insert_query = "INSERT INTO class_routine (class_id, subject_id, teacher_id, day, start_time, end_time, room) 
                     VALUES ($class_id, $subject_id, $teacher_id, '$day', '$start_time', '$end_time', '$room')";

    if(mysqli_query($conn, $insert_query)) {
        $_SESSION['success'] = 'Class routine saved successfully.';
    } else {
        $_SESSION['error'] = 'Error saving routine: ' . mysqli_error($conn);
    }

    header('Location: class-routine.php');
    exit();
}

// Handle delete routine
if(isset($_GET['delete_routine']) && is_numeric($_GET['delete_routine'])) {
    $delete_id = intval($_GET['delete_routine']);
    mysqli_query($conn, "DELETE FROM class_routine WHERE id = $delete_id");
    $_SESSION['success'] = 'Class routine removed.';
    header('Location: class-routine.php');
    exit();
}

$classes = mysqli_query($conn, 'SELECT * FROM classes ORDER BY class_name, section');
$subjects = mysqli_query($conn, 'SELECT * FROM subjects ORDER BY class_id, subject_name');
$teachers = mysqli_query($conn, 'SELECT ts.teacher_id, t.first_name, t.last_name, ts.subject_id, s.class_id FROM teacher_subjects ts JOIN teachers t ON ts.teacher_id = t.id JOIN subjects s ON ts.subject_id = s.id ORDER BY t.first_name, t.last_name');

$routines = mysqli_query($conn, "SELECT cr.*, c.class_name, s.subject_name, s.subject_code, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name 
                                FROM class_routine cr 
                                JOIN classes c ON cr.class_id = c.id 
                                JOIN subjects s ON cr.subject_id = s.id 
                                LEFT JOIN teachers t ON cr.teacher_id = t.id 
                                ORDER BY FIELD(cr.day,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cr.start_time");

$subjectData = [];
while($row = mysqli_fetch_assoc($subjects)) {
    $subjectData[] = $row;
}

$teacherEligibility = [];
mysqli_data_seek($teachers, 0);
while($row = mysqli_fetch_assoc($teachers)) {
    $teacherEligibility[] = $row;
}

function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Routine Management - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f4f7fc; }
        .wrapper { display: flex; }
        .sidebar { width: 280px; background: linear-gradient(135deg, #1e3c72, #2a5298); min-height: 100vh; color: white; position: fixed; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-weight: 700; margin-top: 10px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 12px 25px; color: rgba(255,255,255,0.8); display: flex; align-items: center; transition: all 0.3s; cursor: pointer; text-decoration: none; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.15); color: #fff; }
        .menu-item i { margin-right: 12px; }
        .main-content { margin-left: 280px; width: calc(100% - 280px); padding: 25px 30px; }
        .top-navbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .top-navbar .page-title h4 { margin: 0; font-size: 22px; }
        .top-navbar .user-info { display: flex; align-items: center; gap: 15px; }
        .top-navbar .user-info img { width: 35px; height: 35px; border-radius: 50%; }
        .card { border-radius: 14px; box-shadow: 0 8px 25px rgba(20, 40, 80, 0.08); border: none; }
        .form-label { font-weight: 500; }
        select.form-control, input.form-control { border-radius: 10px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-3x"></i>
                <h3>CoachingPro</h3>
                <small>Admin Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="student-management.php" class="menu-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                </a>
                <a href="teacher-management.php" class="menu-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
                </a>
                <a href="class-management.php" class="menu-item">
                    <i class="fas fa-school"></i>
                    <span>Class & Subjects</span>
                </a>
                <a href="class-routine.php" class="menu-item active">
                    <i class="fas fa-calendar-week"></i>
                    <span>Class Routine</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="result-system.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Result System</span>
                </a>
                <a href="fees-management.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Fees Management</span>
                </a>
                <a href="sms-system.php" class="menu-item">
                    <i class="fas fa-sms"></i>
                    <span>SMS System</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="top-navbar">
                <div class="page-title">
                    <h4>Class Routine Management</h4>
                </div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['display_name']); ?>&background=2a5298&color=fff" alt="User">
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="card mb-4 p-4">
                <h5 class="mb-4">Create New Routine</h5>
                <form method="POST" action="class-routine.php">
                    <input type="hidden" name="save_routine" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Class</label>
                            <select class="form-select" id="classSelect" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo escape($class['class_name']); ?><?php echo $class['section'] ? ' - ' . escape($class['section']) : ''; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subject</label>
                            <select class="form-select" id="subjectSelect" name="subject_id" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" id="teacherSelect" name="teacher_id" required>
                                <option value="">Select Teacher</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Day</label>
                            <select class="form-select" name="day" required>
                                <option value="">Select Day</option>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Room</label>
                            <input type="text" class="form-control" name="room" placeholder="Room / Venue" required>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Routine</button>
                    </div>
                </form>
            </div>

            <div class="card p-4">
                <h5 class="mb-4">Existing Routines</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($routines) > 0): ?>
                                <?php while($routine = mysqli_fetch_assoc($routines)): ?>
                                    <tr>
                                        <td><?php echo escape($routine['day']); ?></td>
                                        <td><?php echo escape($routine['class_name']); ?></td>
                                        <td><?php echo escape($routine['subject_name']); ?></td>
                                        <td><?php echo escape($routine['teacher_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo escape(date('h:i A', strtotime($routine['start_time'])) . ' - ' . date('h:i A', strtotime($routine['end_time']))); ?></td>
                                        <td><?php echo escape($routine['room']); ?></td>
                                        <td>
                                            <a href="class-routine.php?delete_routine=<?php echo $routine['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this routine?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-muted text-center">No routines created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const subjects = <?php echo json_encode($subjectData); ?>;
        const teachers = <?php echo json_encode($teacherEligibility); ?>;

        const classSelect = document.getElementById('classSelect');
        const subjectSelect = document.getElementById('subjectSelect');
        const teacherSelect = document.getElementById('teacherSelect');

        function populateSubjects() {
            const classId = classSelect.value;
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            teacherSelect.innerHTML = '<option value="">Select Teacher</option>';

            subjects.filter(sub => sub.class_id == classId).forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.id;
                option.textContent = sub.subject_name + ' (' + sub.subject_code + ')';
                subjectSelect.appendChild(option);
            });
        }

        function populateTeachers() {
            const subjectId = subjectSelect.value;
            teacherSelect.innerHTML = '<option value="">Select Teacher</option>';

            teachers.filter(t => t.subject_id == subjectId).forEach(t => {
                const option = document.createElement('option');
                option.value = t.teacher_id;
                option.textContent = t.first_name + ' ' + t.last_name;
                teacherSelect.appendChild(option);
            });
        }

        classSelect.addEventListener('change', populateSubjects);
        subjectSelect.addEventListener('change', populateTeachers);
    </script>
</body>
</html>
