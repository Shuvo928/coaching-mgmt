<?php
require_once '../includes/db.php';

// GET ROUTINE DATA (PASTE QUERY HERE)
$sql = "
SELECT cr.*, c.class_name, s.subject_name, t.first_name, t.last_name
FROM class_routine cr
JOIN classes c ON cr.class_id = c.id
JOIN subjects s ON cr.subject_id = s.id
JOIN teachers t ON cr.teacher_id = t.id
ORDER BY cr.day, cr.start_time
";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Routine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">
    <h3 class="mb-4">Class Routine</h3>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Day</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Time</th>
            </tr>
        </thead>

        <tbody>
        <?php while($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $row['day']; ?></td>
                <td><?php echo $row['class_name']; ?></td>
                <td><?php echo $row['subject_name']; ?></td>
                <td><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                <td><?php echo $row['start_time'] . " - " . $row['end_time']; ?></td>
            </tr>
        <?php } ?>
        </tbody>

    </table>
</div>

</body>
</html>