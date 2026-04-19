<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT t.*, u.username, u.created_at as account_created
              FROM teachers t 
              LEFT JOIN users u ON t.user_id = u.id 
              WHERE t.id = $id";
    
    $result = mysqli_query($conn, $query);
    $teacher = mysqli_fetch_assoc($result);
    
    // Get assigned subjects
    $subject_query = "SELECT s.subject_name, c.class_name 
                     FROM teacher_subjects ts
                     JOIN subjects s ON ts.subject_id = s.id
                     JOIN classes c ON s.class_id = c.id
                     WHERE ts.teacher_id = $id";
    $subject_result = mysqli_query($conn, $subject_query);
    ?>
    
    <div class="text-center mb-4">
        <?php if($teacher['photo']): ?>
            <img src="../uploads/teacher-photos/<?php echo $teacher['photo']; ?>" 
                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?php echo $teacher['first_name'].'+'.$teacher['last_name']; ?>&size=120&background=2a5298&color=fff" 
                 style="border-radius: 50%;">
        <?php endif; ?>
        <h5 class="mt-3"><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></h5>
        <span class="badge bg-primary"><?php echo $teacher['teacher_id'] ?? 'TCH' . $teacher['id']; ?></span>
    </div>
    
    <table class="table table-bordered">
        <tr>
            <th width="40%">Qualification</th>
            <td><?php echo $teacher['qualification']; ?></td>
        </tr>
        <tr>
            <th>Assigned Subjects</th>
            <td><?php echo $teacher['assigned_subjects'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td><?php echo $teacher['phone']; ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo $teacher['email'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Joining Date</th>
            <td><?php echo date('d-m-Y', strtotime($teacher['joining_date'])); ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td><?php echo $teacher['address'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?php echo $teacher['username']; ?></td>
        </tr>
        <tr>
            <th>Assigned Subjects</th>
            <td>
                <?php 
                if(mysqli_num_rows($subject_result) > 0) {
                    while($sub = mysqli_fetch_assoc($subject_result)) {
                        echo '<span class="badge bg-info text-white me-1 mb-1">';
                        echo $sub['class_name'] . ' - ' . $sub['subject_name'];
                        echo '</span>';
                    }
                } else {
                    echo '<span class="text-muted">No subjects assigned</span>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th>Account Created</th>
            <td><?php echo date('d-m-Y h:i A', strtotime($teacher['account_created'])); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="badge <?php echo $teacher['status'] ? 'bg-success' : 'bg-danger'; ?>">
                    <?php echo $teacher['status'] ? 'Active' : 'Inactive'; ?>
                </span>
            </td>
        </tr>
    </table>
    
    <?php
}
?>