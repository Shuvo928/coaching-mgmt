<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $query = "SELECT s.*, c.class_name, u.username, u.created_at as account_created
              FROM students s 
              LEFT JOIN classes c ON s.class_id = c.id 
              LEFT JOIN users u ON s.user_id = u.id 
              WHERE s.id = $id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    ?>
    
    <div class="text-center mb-4">
        <?php if($student['photo']): ?>
            <img src="../uploads/student-photos/<?php echo $student['photo']; ?>" 
                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?php echo $student['first_name'].'+'.$student['last_name']; ?>&size=120&background=2a5298&color=fff" 
                 style="border-radius: 50%;">
        <?php endif; ?>
        <h5 class="mt-3"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h5>
        <span class="badge bg-primary"><?php echo $student['student_id']; ?></span>
    </div>
    
    <table class="table table-bordered">
        <tr>
            <th width="40%">Father's Name</th>
            <td><?php echo $student['father_name'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Mother's Name</th>
            <td><?php echo $student['mother_name'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Class</th>
            <td><?php echo $student['class_name'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Roll Number</th>
            <td><?php echo $student['roll_number'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Batch No</th>
            <td><?php echo $student['batch_no'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Date of Birth</th>
            <td><?php echo $student['dob'] ? date('d-m-Y', strtotime($student['dob'])) : 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Gender</th>
            <td><?php echo $student['gender'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td><?php echo $student['phone'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo $student['email'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td><?php echo $student['address'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Admission Date</th>
            <td><?php echo $student['admission_date'] ? date('d-m-Y', strtotime($student['admission_date'])) : 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?php echo $student['username'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <th>Account Created</th>
            <td><?php echo date('d-m-Y h:i A', strtotime($student['account_created'])); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                    <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                </span>
            </td>
        </tr>
    </table>
    
    <?php
}
?>