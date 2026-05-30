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

    // Check if results table has marks_obtained and total_marks columns
    $resultsMarksColumns = false;
    $marksObtainedCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'marks_obtained'");
    $totalMarksCheck = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'total_marks'");
    $resultsMarksColumns = ($marksObtainedCheck && mysqli_num_rows($marksObtainedCheck) > 0) &&
                            ($totalMarksCheck && mysqli_num_rows($totalMarksCheck) > 0);

    // Get student details
    $student_query = "SELECT s.*, c.class_name
                      FROM students s
                      JOIN classes c ON s.class_id = c.id
                      WHERE s.id = $student_id";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);

    // Get results
    $exam_filter = $exam_id ? "AND r.exam_type_id = $exam_id" : "";

    // Build column list based on what exists
    $resultsCols = "r.id, s.subject_name, r.test_type, r.exam_date as exam_date";

    if ($resultsMarksColumns) {
        $resultsCols .= ", r.marks_obtained, r.total_marks";
    } else {
        $resultsCols .= ", NULL as marks_obtained, NULL as total_marks";
    }

    // Add test type name
    $resultsCols .= ", CASE
        WHEN r.test_type = 'weekly_test' THEN 'Weekly Test'
        WHEN r.test_type = 'monthly_test' THEN 'Monthly Test'
        WHEN r.test_type = 'exam' THEN 'Exam'
        ELSE 'Test'
    END AS test_name";

    // Add teacher name from assigned subject teacher for this student's class
    $resultsCols .= ", CONCAT(TRIM(t.first_name), ' ', TRIM(t.last_name)) AS teacher_name";

    $results_query = "SELECT DISTINCT $resultsCols
                      FROM results r
                      LEFT JOIN subjects s ON r.subject_id = s.id
                      LEFT JOIN students st ON r.student_id = st.id
                      LEFT JOIN teacher_subjects ts ON ts.subject_id = r.subject_id AND ts.class_id = st.class_id
                      LEFT JOIN teachers t ON ts.teacher_id = t.id AND t.status = 1
                      WHERE r.student_id = $student_id
                      ORDER BY r.created_at DESC";

    $results_result = mysqli_query($conn, $results_query);

    if(mysqli_num_rows($results_result) > 0) {
        ?>
        <style>
        .results-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .results-container-header {
            padding: 25px;
            border-bottom: 2px solid #e2e8f0;
            background: #f8f9fa;
        }

        .results-container-header h4 {
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f8f9fa;
        }

        .table thead th {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #212529;
            padding: 15px;
        }

        .table th {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #212529;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .subject-name {
            font-weight: 600;
            color: #333;
        }

        .exam-type {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .marks-badge {
            font-weight: 600;
            color: #333;
        }

        .btn-outline-primary:hover {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        </style>

        <div class="results-container">
            <div class="results-container-header d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-list me-2"></i><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> - Exam Results</h4>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Date</th>
                            <th>Marks Obtained</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while($row = mysqli_fetch_assoc($results_result)) {
                        ?>
                        <tr>
                            <td><span class="fw-semibold"><?php echo htmlspecialchars($row['subject_name'] ?? 'N/A'); ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['test_name'] ?? 'Test'); ?></span></td>
                            <td><?php echo isset($row['exam_date']) && $row['exam_date'] ? date('d M, Y', strtotime($row['exam_date'])) : 'N/A'; ?></td>
                            <td class="marks-badge">
                                <?php
                                    if (isset($row['marks_obtained']) && $row['marks_obtained'] !== null) {
                                        if (isset($row['total_marks']) && $row['total_marks'] !== null && $row['total_marks'] > 0) {
                                            echo htmlspecialchars($row['marks_obtained'] . "/" . $row['total_marks']);
                                        } else {
                                            echo htmlspecialchars($row['marks_obtained'] . "/100");
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(trim($row['teacher_name'] ?? '') !== '' ? $row['teacher_name'] : 'Not assigned yet'); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function editResult(resultId, subjectName, testType, currentMarks, testDate) {
            // Create edit modal/form
            const modalHtml = `
                <div class="modal fade" id="editResultModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Result - ${subjectName}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editResultForm">
                                    <div class="mb-3">
                                        <label class="form-label">Subject</label>
                                        <input type="text" class="form-control" value="${subjectName}" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Exam Type</label>
                                        <input type="text" class="form-control" value="${testType}" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Marks Obtained (0-100)</label>
                                        <input type="number" class="form-control" id="editMarks" min="0" max="100" value="${currentMarks}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Exam Date</label>
                                        <input type="date" class="form-control" id="editExamDate" value="${testDate ? new Date(testDate).toISOString().split('T')[0] : ''}">
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveResultEdit(${resultId})">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('editResultModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editResultModal'));
            modal.show();
        }

        function saveResultEdit(resultId) {
            const marks = document.getElementById('editMarks').value;
            const examDate = document.getElementById('editExamDate').value;

            if (!marks || marks < 0 || marks > 100) {
                alert('Please enter marks between 0-100');
                return;
            }

            // AJAX call to update result
            fetch('update-result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    result_id: resultId,
                    marks: marks,
                    exam_date: examDate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Result updated successfully!');
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editResultModal'));
                    modal.hide();
                    // Reload results
                    location.reload();
                } else {
                    alert('Error updating result: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function deleteResult(resultId, subjectName) {
            if (confirm('Are you sure you want to delete the result for ' + subjectName + '? This action cannot be undone.')) {
                // AJAX call to delete result
                fetch('delete-result.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        result_id: resultId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Result deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting result: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        function addNewResult(studentId) {
            alert('To add a new result, please use the Teacher Dashboard marks entry section.');
            // Could redirect to teacher dashboard or open a form
            // window.location.href = 'teacher-dashboard.php#result-section';
        }
        </script>

        <!-- Bootstrap JS for modal functionality -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <?php
    } else {
        ?>
        <div class="text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <p class="text-muted">No results found for this student</p>
            <button type="button" class="btn btn-primary" onclick="addNewResult(<?php echo $student_id; ?>)">
                <i class="fas fa-plus me-1"></i>Add First Result
            </button>
        </div>
        <?php
    }
}
?>