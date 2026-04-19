<?php
require_once '../includes/db.php';

if(isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    $exam_id = $_POST['exam_id'] ?? '';
    
    // Check if exam_types table exists
    $examTypesTableExists = false;
    $examTypesCheck = mysqli_query($conn, "SHOW TABLES LIKE 'exam_types'");
    if ($examTypesCheck && mysqli_num_rows($examTypesCheck) > 0) {
        $examTypesTableExists = true;
    }
    
    // Get student details
    $student_query = "SELECT s.*, c.class_name 
                      FROM students s 
                      JOIN classes c ON s.class_id = c.id 
                      WHERE s.id = $student_id";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);
    
    // Get results
    $exam_filter = $exam_id ? "AND r.exam_type_id = $exam_id" : "";
    
    if ($examTypesTableExists) {
        $results_query = "SELECT r.*, s.subject_name, s.subject_code, et.exam_name
                          FROM results r
                          JOIN subjects s ON r.subject_id = s.id
                          JOIN exam_types et ON r.exam_type_id = et.id
                          WHERE r.student_id = $student_id $exam_filter
                          ORDER BY et.exam_name, s.subject_name";
    } else {
        $results_query = "SELECT r.*, s.subject_name, s.subject_code, NULL AS exam_name
                          FROM results r
                          JOIN subjects s ON r.subject_id = s.id
                          WHERE r.student_id = $student_id $exam_filter
                          ORDER BY s.subject_name";
    }
    $results = mysqli_query($conn, $results_query);
    
    if(mysqli_num_rows($results) > 0) {
        ?>
        <div class="result-card" id="result-card">
            <div class="result-header">
                <h3><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h3>
                <h4 class="text-danger">Academic Transcript</h4>
                <p class="text-muted">Student ID: <?php echo $student['student_id']; ?> | Class: <?php echo $student['class_name']; ?></p>
            </div>
            
            <div class="student-info">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Father's Name:</strong> <?php echo $student['father_name'] ?? 'N/A'; ?></p>
                        <p><strong>Mother's Name:</strong> <?php echo $student['mother_name'] ?? 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Roll Number:</strong> <?php echo $student['roll_number'] ?? 'N/A'; ?></p>
                        <p><strong>Session:</strong> <?php echo date('Y') . '-' . (date('Y')+1); ?></p>
                    </div>
                </div>
            </div>
            
            <?php 
            $current_exam = '';
            $total_points = 0;
            $subject_count = 0;
            
            while($row = mysqli_fetch_assoc($results)):
                if($current_exam != $row['exam_name']):
                    if($current_exam != ''):
                        // Calculate GPA for previous exam
                        $gpa = $subject_count > 0 ? round($total_points / $subject_count, 2) : 0;
                        ?>
                        <tr class="table-info">
                            <td colspan="5" class="text-end"><strong>GPA:</strong></td>
                            <td><strong><?php echo $gpa; ?></strong></td>
                        </tr>
                        </table>
                    <?php endif; ?>
                    
                    <h5 class="mt-4 mb-3"><?php echo $row['exam_name']; ?></h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Subject Code</th>
                                <th>Marks</th>
                                <th>Grade</th>
                                <th>Grade Point</th>
                            </tr>
                        </thead>
                    <?php 
                    $current_exam = $row['exam_name'];
                    $total_points = 0;
                    $subject_count = 0;
                endif;
                
                $total_points += $row['points'];
                $subject_count++;
                ?>
                <tr>
                    <td><?php echo $row['subject_name']; ?></td>
                    <td><?php echo $row['subject_code']; ?></td>
                    <td><?php echo $row['marks_obtained']; ?></td>
                    <td>
                        <span class="gpa-badge 
                            <?php 
                            if($row['grade'] == 'A+') echo 'gpa-aplus';
                            elseif($row['grade'] == 'A') echo 'gpa-a';
                            elseif($row['grade'] == 'A-') echo 'gpa-aminus';
                            elseif($row['grade'] == 'B') echo 'gpa-b';
                            elseif($row['grade'] == 'C') echo 'gpa-c';
                            elseif($row['grade'] == 'D') echo 'gpa-d';
                            else echo 'gpa-f';
                            ?>">
                            <?php echo $row['grade']; ?>
                        </span>
                    </td>
                    <td><?php echo number_format($row['points'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
            
            <?php if($subject_count > 0): 
                $gpa = round($total_points / $subject_count, 2);
                $gpa_class = 'gpa-f';
                if($gpa >= 5) $gpa_class = 'gpa-aplus';
                elseif($gpa >= 4) $gpa_class = 'gpa-a';
                elseif($gpa >= 3.5) $gpa_class = 'gpa-aminus';
                elseif($gpa >= 3) $gpa_class = 'gpa-b';
                elseif($gpa >= 2) $gpa_class = 'gpa-c';
                elseif($gpa >= 1) $gpa_class = 'gpa-d';
            ?>
                <tr class="table-info">
                    <td colspan="4" class="text-end"><strong>GPA:</strong></td>
                    <td>
                        <span class="gpa-badge <?php echo $gpa_class; ?>">
                            <?php echo $gpa; ?>
                        </span>
                    </td>
                </tr>
                </table>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <p><strong>Total Subjects:</strong> <?php echo $subject_count; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Grade Point Average (GPA):</strong> 
                            <span class="grade-point"><?php echo $gpa; ?></span>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No results found for this student</p>
              </div>';
    }
}
?>