<?php
require_once '../includes/db.php';

if(isset($_POST['from_date']) && isset($_POST['to_date'])) {
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $class_id = $_POST['class_id'] ?? '';
    
    $class_filter = $class_id ? "AND s.class_id = $class_id" : "";
    
    // Get attendance summary by student
    $query = "SELECT 
                s.id,
                s.student_id,
                s.first_name,
                s.last_name,
                c.class_name,
                COUNT(a.id) as total_days,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
              FROM students s
              JOIN classes c ON s.class_id = c.id
              LEFT JOIN attendance a ON s.id = a.student_id 
                  AND a.date BETWEEN '$from_date' AND '$to_date'
              WHERE s.status = 1 $class_filter
              GROUP BY s.id
              ORDER BY c.class_name, s.roll_number";
    
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) > 0) {
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): 
                        $percentage = $row['total_days'] > 0 
                            ? round(($row['present_count'] / $row['total_days']) * 100, 1)
                            : 0;
                    ?>
                    <tr>
                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                        <td><?php echo $row['class_name']; ?></td>
                        <td><?php echo $row['total_days']; ?></td>
                        <td class="text-success"><?php echo $row['present_count']; ?></td>
                        <td class="text-danger"><?php echo $row['absent_count']; ?></td>
                        <td class="text-warning"><?php echo $row['late_count']; ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%">
                                    <?php echo $percentage; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo '<p class="text-center text-muted py-4">No attendance records found for selected period</p>';
    }
}
?>