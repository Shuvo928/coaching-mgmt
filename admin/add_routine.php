<?php
session_start();
require_once '../includes/db.php';

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";

// INSERT ROUTINE
if (isset($_POST['submit'])) {
    $class_id   = $_POST['class_id'];
    $group_id   = $_POST['group_id'];
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    $day        = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time   = $_POST['end_time'];

    $sql = "INSERT INTO class_routine 
            (class_id, group_id, subject_id, teacher_id, day, start_time, end_time)
            VALUES 
            ('$class_id', '$group_id', '$subject_id', '$teacher_id', '$day', '$start_time', '$end_time')";

    if (mysqli_query($conn, $sql)) {
        $message = "Routine added successfully!";
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
}

// FETCH DATA
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY id DESC");
$groups  = mysqli_query($conn, "SELECT * FROM groups ORDER BY id DESC");
$teachers = mysqli_query($conn, "SELECT * FROM teachers WHERE status = 1 ORDER BY id DESC");
?>

<!DOCTYPE html>

<html>
<head>
    <title>Add Routine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4">

```
    <h3 class="mb-3">Add Class Routine</h3>

    <?php if ($message != "") { ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
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
            <select name="teacher_id" class="form-control" required>
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

        <button type="submit" name="submit" class="btn btn-primary w-100">
            Add Routine
        </button>

    </form>
</div>
```

</div>

<!-- AJAX SCRIPT -->

<script>
document.getElementById('group_id').addEventListener('change', function() {
    var group_id = this.value;

    var xhr = new XMLHttpRequest();
    xhr.open("GET", "get_subjects.php?group_id=" + group_id, true);

    xhr.onload = function() {
        document.getElementById('subject_id').innerHTML = this.responseText;
    };

    xhr.send();
});
</script>

</body>
</html>
