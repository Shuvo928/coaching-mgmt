<?php
require_once '../includes/db.php';

if(isset($_POST['class_id']) && isset($_POST['exam_id']) && isset($_POST['subject_id'])) {
    $class_id = $_POST['class_id'];
    $exam_id = $_POST['exam_id'];
    $subject_id = $_POST['subject_id'];
    
    // Get all students in the class
    $students_query = "SELECT s.* FROM students s 
                       WHERE s.class_id = $class_id AND s.status = 1
                       ORDER BY s.roll_number, s.first_name";
    $students = mysqli_query($conn, $students_query);
    
    // Check if marks already exist
    $marks_query = "SELECT * FROM results 
                    WHERE exam_type_id = $exam_id AND subject_id = $subject_id";
    $existing_marks = mysqli_query($conn, $marks_query);
    $marks_data = [];
    while($row = mysqli_fetch_assoc($existing_marks)) {
        $marks_data[$row['student_id']] = $row;
    }
    
    if(mysqli_num_rows($students) > 0) {
        ?>
        <div class="table-responsive">
            <table class="marks-table table">
                <thead>
                    <tr>
                        <th>Roll</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Marks (Out of 100)</th>
                        <th>Grade</th>
                        <th>Grade Point</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_marks = 0;
                    $student_count = 0;
                    while($student = mysqli_fetch_assoc($students)): 
                        $existing = $marks_data[$student['id']] ?? null;
                        $marks = $existing ? $existing['marks_obtained'] : '';
                        $grade = $existing ? $existing['grade'] : '';
                        $point = $existing ? $existing['points'] : '';
                        
                        if($marks) {
                            $total_marks += $marks;
                            $student_count++;
                        }
                    ?>
                    <tr class="marks-row" data-student-id="<?php echo $student['id']; ?>">
                        <td><?php echo $student['roll_number'] ?? 'N/A'; ?></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td>
                            <input type="number" class="marks-input" value="<?php echo $marks; ?>" 
                                   min="0" max="100" step="0.01" onchange="updateGrade(this)">
                        </td>
                        <td class="grade-display"><?php echo $grade; ?></td>
                        <td class="point-display"><?php echo $point; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <?php if($student_count > 0): ?>
                <tfoot>
                    <tr class="table-info">
                        <td colspan="3" class="text-end"><strong>Class Average:</strong></td>
                        <td><strong><?php echo round($total_marks / $student_count, 2); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            
            <div class="text-end mt-3">
                <button class="btn btn-action" onclick="saveMarks()">
                    <i class="fas fa-save me-2"></i>Save All Marks
                </button>
            </div>
        </div>

        <script>
        function updateGrade(input) {
            var marks = parseFloat(input.value);
            var row = $(input).closest('tr');
            var gradeCell = row.find('.grade-display');
            var pointCell = row.find('.point-display');
            
            if(!isNaN(marks)) {
                var result = calculateGPA(marks);
                gradeCell.text(result.grade);
                pointCell.text(result.point.toFixed(2));
            } else {
                gradeCell.text('');
                pointCell.text('');
            }
        }
        </script>
        <?php
    } else {
        echo '<div class="text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No students found in this class</p>
              </div>';
    }
}
?>