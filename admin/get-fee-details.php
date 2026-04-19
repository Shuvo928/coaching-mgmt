<?php
require_once '../includes/db.php';

if(isset($_POST['id'])) {
    $collection_id = $_POST['id'];
    
    // Check if classes table has section column
    $sectionColumn = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'section'");
    $sectionSelect = ($sectionColumn && mysqli_num_rows($sectionColumn) > 0) ? 'c.section' : "'' AS section";
    
    $query = "SELECT fc.*, s.first_name, s.last_name, s.student_id, s.father_name,
                     c.class_name, $sectionSelect, fh.fee_name
              FROM fee_collections fc
              JOIN students s ON fc.student_id = s.id
              JOIN classes c ON s.class_id = c.id
              JOIN fees_head fh ON fc.fee_head_id = fh.id
              WHERE fc.id = $collection_id";
    
    $result = mysqli_query($conn, $query);
    $receipt = mysqli_fetch_assoc($result);
    
    if($receipt):
    ?>
    <div class="receipt" id="receipt">
        <div class="receipt-header">
            <h4>CoachingPro</h4>
            <p>Fee Payment Receipt</p>
        </div>
        
        <div class="row mb-3">
            <div class="col-6">
                <p><strong>Receipt No:</strong> <?php echo $receipt['receipt_no']; ?></p>
                <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($receipt['payment_date'])); ?></p>
            </div>
            <div class="col-6">
                <p><strong>Student ID:</strong> <?php echo $receipt['student_id']; ?></p>
                <p><strong>Payment Method:</strong> <?php echo $receipt['payment_method']; ?></p>
            </div>
        </div>
        
        <table class="table table-bordered">
            <tr>
                <th>Student Name:</th>
                <td><?php echo $receipt['first_name'] . ' ' . $receipt['last_name']; ?></td>
            </tr>
            <tr>
                <th>Father's Name:</th>
                <td><?php echo $receipt['father_name'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Class:</th>
                <td><?php echo $receipt['class_name'] . ' - ' . $receipt['section']; ?></td>
            </tr>
            <tr>
                <th>Fee Type:</th>
                <td><?php echo $receipt['fee_name']; ?></td>
            </tr>
            <tr>
                <th>Total Amount:</th>
                <td>৳<?php echo number_format($receipt['amount'], 2); ?></td>
            </tr>
            <tr>
                <th>Paid Amount:</th>
                <td>৳<?php echo number_format($receipt['paid_amount'], 2); ?></td>
            </tr>
            <tr>
                <th>Due Amount:</th>
                <td>৳<?php echo number_format($receipt['due_amount'], 2); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <span class="status-badge <?php echo strtolower($receipt['status']); ?>">
                        <?php echo $receipt['status']; ?>
                    </span>
                </td>
            </tr>
            <?php if($receipt['remarks']): ?>
            <tr>
                <th>Remarks:</th>
                <td><?php echo $receipt['remarks']; ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div class="text-center mt-3">
            <p>Thank you for your payment!</p>
            <p style="font-size: 12px;">This is a computer generated receipt.</p>
        </div>
    </div>
    <?php
    endif;
}
?>