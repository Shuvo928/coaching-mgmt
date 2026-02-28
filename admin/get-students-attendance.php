<?php
require_once '../includes/db.php';

if(isset($_POST['class_id']) && isset($_POST['date'])) {
    $class_id = $_POST['class_id'];
    $date = $_POST['date'];
    
    // Get all students in the class
    $query = "SELECT s.*, 
              (SELECT status FROM attendance WHERE student_id = s.id AND date = '$date') as today_status
              FROM students s 
              WHERE s.class_id = $class_id AND s.status = 1
              ORDER BY s.roll_number, s.first_name";
    
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) > 0) {
        ?>
        <form id="attendanceForm">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Student</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="student-attendance-row" data-student-id="<?php echo $row['id']; ?>">
                        <td><?php echo $row['roll_number'] ?? 'N/A'; ?></td>
                        <td>
                            <div class="student-info">
                                <?php if($row['photo']): ?>
                                    <img src="../uploads/student-photos/<?php echo $row['photo']; ?>" class="student-photo">
                                <?php else: ?>
                                    <img src="https://ui-avatars.com/api/?name=<?php echo $row['first_name'].'+'.$row['last_name']; ?>&size=35&background=2a5298&color=fff" class="student-photo">
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $row['student_id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="attendance-status">
                                <label class="status-option present">
                                    <input type="radio" name="status_<?php echo $row['id']; ?>" value="Present" 
                                           <?php echo $row['today_status'] == 'Present' ? 'checked' : ''; ?>>
                                    <span>Present</span>
                                </label>
                                <label class="status-option absent">
                                    <input type="radio" name="status_<?php echo $row['id']; ?>" value="Absent"
                                           <?php echo $row['today_status'] == 'Absent' ? 'checked' : ''; ?>>
                                    <span>Absent</span>
                                </label>
                                <label class="status-option late">
                                    <input type="radio" name="status_<?php echo $row['id']; ?>" value="Late"
                                           <?php echo $row['today_status'] == 'Late' ? 'checked' : ''; ?>>
                                    <span>Late</span>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="text-end mt-3">
                <button type="button" class="btn btn-save-attendance" onclick="saveAttendance()">
                    <i class="fas fa-save me-2"></i>Save Attendance
                </button>
            </div>
        </form>
        <?php
    } else {
        echo '<div class="text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No active students found in this class</p>
              </div>';
    }
}
?>