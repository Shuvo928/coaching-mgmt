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
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th rowspan="2">SL</th>
                        <th rowspan="2">Student ID</th>
                        <th rowspan="2">Student Name</th>
                        <th rowspan="2">Roll</th>
                        <th colspan="<?php echo count($subject_list); ?>" class="text-center">Subject-wise Grade</th>
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
                    while($student = mysqli_fetch_assoc($students)): 
                        // Get student's results
                        $results_query = "SELECT r.*, s.subject_name 
                                         FROM results r
                                         JOIN subjects s ON r.subject_id = s.id
                                         WHERE r.student_id = {$student['id']} 
                                         AND r.exam_type_id = $exam_id";
                        $results = mysqli_query($conn, $results_query);
                        $results_data = [];
                        $total_points = 0;
                        $subject_count = 0;
                        
                        while($result = mysqli_fetch_assoc($results)) {
                            $results_data[$result['subject_id']] = $result;
                            if($result['grade'] != 'F') {
                                $total_points += $result['points'];
                                $subject_count++;
                            }
                        }
                        
                        $gpa = $subject_count > 0 ? round($total_points / $subject_count, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td><?php echo $student['roll_number'] ?? 'N/A'; ?></td>
                        
                        <?php foreach(array_keys($subject_list) as $subject_id): 
                            $grade = isset($results_data[$subject_id]) ? $results_data[$subject_id]['grade'] : '-';
                            $gpa_class = '';
                            if($grade == 'A+') $gpa_class = 'gpa-aplus';
                            elseif($grade == 'A') $gpa_class = 'gpa-a';
                            elseif($grade == 'A-') $gpa_class = 'gpa-aminus';
                            elseif($grade == 'B') $gpa_class = 'gpa-b';
                            elseif($grade == 'C') $gpa_class = 'gpa-c';
                            elseif($grade == 'D') $gpa_class = 'gpa-d';
                            elseif($grade == 'F') $gpa_class = 'gpa-f';
                        ?>
                            <td>
                                <?php if($grade != '-'): ?>
                                    <span class="gpa-badge <?php echo $gpa_class; ?>">
                                        <?php echo $grade; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <td>
                            <span class="gpa-badge 
                                <?php 
                                if($gpa >= 5) echo 'gpa-aplus';
                                elseif($gpa >= 4) echo 'gpa-a';
                                elseif($gpa >= 3.5) echo 'gpa-aminus';
                                elseif($gpa >= 3) echo 'gpa-b';
                                elseif($gpa >= 2) echo 'gpa-c';
                                elseif($gpa >= 1) echo 'gpa-d';
                                else echo 'gpa-f';
                                ?>">
                                <?php echo $gpa; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5">
                <i class="fas fa-table fa-3x text-muted mb-3"></i>
                <p class="text-muted">No data available for grade sheet</p>
              </div>';
    }
}
?>