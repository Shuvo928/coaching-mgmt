<?php
require_once '../includes/db.php';

if(isset($_POST['class_id']) && isset($_POST['exam_id'])) {
    $class_id = $_POST['class_id'];
    $exam_id = $_POST['exam_id'];
    
    // Get class details
    $class_query = mysqli_query($conn, "SELECT * FROM classes WHERE id = $class_id");
    $class = mysqli_fetch_assoc($class_query);
    
    // Get all subjects for this class
    $subjects_query = "SELECT * FROM subjects WHERE class_id = $class_id ORDER BY subject_name";
    $subjects = mysqli_query($conn, $subjects_query);
    $subject_list = [];
    while($sub = mysqli_fetch_assoc($subjects)) {
        $subject_list[$sub['id']] = $sub['subject_name'];
    }
    
    // Get all students in the class
    $students_query = "SELECT s.* FROM students s 
                       WHERE s.class_id = $class_id AND s.status = 1
                       ORDER BY s.roll_number, s.first_name";
    $students = mysqli_query($conn, $students_query);
    
    if(mysqli_num_rows($students) > 0 && !empty($subject_list)) {
        ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" style="font-size: 14px;">
                <thead>
                    <tr>
                        <th rowspan="2">SL</th>
                        <th rowspan="2">Student ID</th>
                        <th rowspan="2">Student Name</th>
                        <th rowspan="2">Roll</th>
                        <th colspan="<?php echo count($subject_list); ?>" class="text-center">Subject-wise Marks</th>
                        <th rowspan="2">Total</th>
                        <th rowspan="2">GPA</th>
                    </tr>
                    <tr>
                        <?php foreach($subject_list as $subject): ?>
                            <th><?php echo $subject; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sl = 1;
                    $subject_totals = array_fill_keys(array_keys($subject_list), 0);
                    $student_count = 0;
                    
                    while($student = mysqli_fetch_assoc($students)): 
                        // Get student's results
                        $results_query = "SELECT r.*, s.subject_name 
                                         FROM results r
                                         JOIN subjects s ON r.subject_id = s.id
                                         WHERE r.student_id = {$student['id']} 
                                         AND r.exam_type_id = $exam_id";
                        $results = mysqli_query($conn, $results_query);
                        $results_data = [];
                        $total_marks = 0;
                        $total_points = 0;
                        $subject_count = 0;
                        
                        while($result = mysqli_fetch_assoc($results)) {
                            $results_data[$result['subject_id']] = $result;
                            $total_marks += $result['marks_obtained'];
                            if($result['grade'] != 'F') {
                                $total_points += $result['points'];
                                $subject_count++;
                            }
                            $subject_totals[$result['subject_id']] += $result['marks_obtained'];
                        }
                        
                        $gpa = $subject_count > 0 ? round($total_points / $subject_count, 2) : 0;
                        $student_count++;
                    ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td><?php echo $student['roll_number'] ?? 'N/A'; ?></td>
                        
                        <?php foreach(array_keys($subject_list) as $subject_id): 
                            $marks = isset($results_data[$subject_id]) ? $results_data[$subject_id]['marks_obtained'] : '-';
                        ?>
                            <td><?php echo $marks; ?></td>
                        <?php endforeach; ?>
                        
                        <td><strong><?php echo $total_marks; ?></strong></td>
                        <td><strong><?php echo $gpa; ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <!-- Average Row -->
                    <?php if($student_count > 0): ?>
                    <tr class="table-info">
                        <td colspan="4" class="text-end"><strong>Class Average:</strong></td>
                        <?php foreach(array_keys($subject_list) as $subject_id): 
                            $avg = round($subject_totals[$subject_id] / $student_count, 1);
                        ?>
                            <td><strong><?php echo $avg; ?></strong></td>
                        <?php endforeach; ?>
                        <td></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5">
                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                <p class="text-muted">No data available for tabulation sheet</p>
              </div>';
    }
}
?>