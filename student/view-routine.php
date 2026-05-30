<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuth();
checkRole(['student']);

$user = getCurrentUser($conn);
$student = null;
$class_routine = [];
$expected_class_group = '';
$error_msg = '';

function normalizeText(string $value): string {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
}

function formatRoutineKey(array $routine): string {
    $day = normalizeText((string)($routine['day'] ?? ''));
    $start = trim((string)($routine['start_time'] ?? ''));
    $end = trim((string)($routine['end_time'] ?? ''));

    if ($day === '' || $start === '' || $end === '') {
        return '';
    }

    $normalizedStart = date('H:i', strtotime($start));
    $normalizedEnd = date('H:i', strtotime($end));
    if ($normalizedStart === '00:00' && $start !== '00:00') {
        return '';
    }
    if ($normalizedEnd === '00:00' && $end !== '00:00') {
        return '';
    }

    return $day . '|' . $normalizedStart . '|' . $normalizedEnd;
}

function getRoutinePriorityScore(array $routine): int {
    $score = 0;
    $teacher = trim((string)($routine['teacher'] ?? ''));
    $subject = trim((string)($routine['subject'] ?? ''));
    $room = trim((string)($routine['room'] ?? ''));

    if ($teacher !== '') {
        $score += 4;
    }
    if ($subject !== '') {
        $score += 2;
    }
    if ($room !== '') {
        $score += 1;
    }

    return $score;
}

function dedupeStudentRoutine(array $routineRows): array {
    $deduped = [];
    $keyIndex = [];

    foreach ($routineRows as $routine) {
        $key = formatRoutineKey($routine);
        if ($key === '') {
            $deduped[] = $routine;
            continue;
        }

        if (!isset($keyIndex[$key])) {
            $keyIndex[$key] = count($deduped);
            $deduped[] = $routine;
            continue;
        }

        $existingIndex = $keyIndex[$key];
        $existing = $deduped[$existingIndex];
        $existingScore = getRoutinePriorityScore($existing);
        $newScore = getRoutinePriorityScore($routine);

        if ($newScore > $existingScore) {
            $deduped[$existingIndex] = $routine;
        }
    }

    return $deduped;
}

if (!empty($user['id'])) {
    // Fetch student details
    $student_query = "SELECT s.*, c.class_name, aa.`group` AS group_name 
                      FROM students s 
                      LEFT JOIN classes c ON s.class_id = c.id
                      LEFT JOIN admission_applications aa ON s.phone = aa.mobile 
                      WHERE s.user_id = " . intval($user['id']) . " LIMIT 1";
    
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);

    if ($student) {
        // Build class_group name from student's class and group
        $class_number = '';
        $group_name = $student['group_name'] ?? '';
        
        // Parse class name (e.g., "Class 9", "Class 10", "SSC")
        if (!empty($student['class_name'])) {
            if (strpos(strtoupper($student['class_name']), 'SSC') !== false) {
                $class_number = 'SSC Batch';
            } else {
                preg_match('/Class\s+(\d+)/', $student['class_name'], $matches);
                if (!empty($matches[1])) {
                    $class_number = 'Class ' . $matches[1];
                }
            }
        }
        
        // Build class_group string
        if (!empty($class_number) && !empty($group_name)) {
            $group_display = ucfirst(strtolower($group_name));
            if (stripos($group_display, 'group') === false) {
                $group_display .= ' Group';
            }
            $expected_class_group = $class_number . ' — ' . $group_display;
        } else {
            $error_msg = 'Your class and group information could not be determined. Please contact the administration.';
        }

        // Query routine from the routine table
        if (!empty($expected_class_group)) {
            $routine_query = "SELECT * FROM `routine` 
                              WHERE class_group = '" . mysqli_real_escape_string($conn, $expected_class_group) . "' 
                              ORDER BY FIELD(day, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'), start_time";
            
            $routine_result = mysqli_query($conn, $routine_query);
            if ($routine_result) {
                while ($routine = mysqli_fetch_assoc($routine_result)) {
                    $class_routine[] = $routine;
                }
                $class_routine = dedupeStudentRoutine($class_routine);
            }
        }
    } else {
        $error_msg = 'Student information not found. Please contact the administration.';
    }
}

// Group routine by day
$routine_by_day = [];
foreach ($class_routine as $routine) {
    $day = $routine['day'];
    if (!isset($routine_by_day[$day])) {
        $routine_by_day[$day] = [];
    }
    $routine_by_day[$day][] = $routine;
}

// Define day order
$day_order = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
$sorted_routine_by_day = [];
foreach ($day_order as $day) {
    if (isset($routine_by_day[$day])) {
        $sorted_routine_by_day[$day] = $routine_by_day[$day];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Class Routine - CoachingPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .routine-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .header-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        .header-section h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header-section p {
            color: #7f8c8d;
            margin-bottom: 0;
            font-size: 16px;
        }
        
        .class-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .class-info .info-item {
            text-align: center;
        }
        
        .class-info .label {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
        }
        
        .class-info .value {
            font-size: 18px;
            font-weight: 700;
            margin-top: 5px;
        }
        
        .day-section {
            margin-bottom: 30px;
        }
        
        .day-header {
            background: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            border-left: 5px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .day-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .day-header i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .schedule-table {
            background: white;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 10px;
        }
        
        .schedule-table table {
            margin: 0;
            width: 100%;
        }
        
        .schedule-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            border: none;
        }
        
        .schedule-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            vertical-align: middle;
        }
        
        .schedule-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .schedule-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .time-badge {
            display: inline-block;
            background: #e8f0fe;
            color: #1967d2;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .room-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .subject-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .teacher-name {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .alert-info {
            border-radius: 12px;
            border: none;
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .alert-warning {
            border-radius: 12px;
            border: none;
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            color: #f0f0f0;
            transform: translateX(-5px);
        }
        
        .no-classes-msg {
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin: 40px 0;
        }
        
        .no-classes-msg i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .print-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background: #667eea;
            color: white;
        }
        
        @media print {
            body {
                background: white;
            }
            .print-btn {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .class-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .schedule-table table {
                font-size: 12px;
            }
            
            .schedule-table td, .schedule-table th {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="routine-container">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        
        <div class="header-section">
            <h1><i class="fas fa-calendar-alt me-3" style="color: #667eea;"></i>My Class Routine</h1>
            <p>Your personalized weekly class schedule</p>
            
            <?php if ($student): ?>
                <div class="class-info">
                    <div class="info-item">
                        <div class="label">📚 Class</div>
                        <div class="value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">🎓 Group</div>
                        <div class="value"><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">👤 Student</div>
                        <div class="value"><?php echo htmlspecialchars($user['first_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <button class="print-btn" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Notice:</strong> <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($class_routine)): ?>
            <div class="no-classes-msg">
                <i class="fas fa-calendar-check"></i>
                <h3>No Routine Available</h3>
                <p class="text-muted">Your class routine has not been set up yet. Please contact the administration.</p>
            </div>
        <?php else: ?>
            <?php foreach ($sorted_routine_by_day as $day => $routines): ?>
                <div class="day-section">
                    <div class="day-header">
                        <h3>
                            <i class="fas fa-clock"></i>
                            <?php echo htmlspecialchars($day); ?>
                        </h3>
                    </div>
                    <div class="schedule-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 15%;">⏰ Time</th>
                                    <th style="width: 25%;">📖 Subject</th>
                                    <th style="width: 25%;">👨‍🏫 Teacher</th>
                                    <th style="width: 15%;">🚪 Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routines as $routine): ?>
                                    <tr>
                                        <td>
                                            <span class="time-badge">
                                                <?php 
                                                    $start = date('g:i A', strtotime($routine['start_time']));
                                                    $end = date('g:i A', strtotime($routine['end_time']));
                                                    echo htmlspecialchars($start . ' – ' . $end);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="subject-name"><?php echo htmlspecialchars($routine['subject']); ?></div>
                                        </td>
                                        <td>
                                            <div class="teacher-name"><?php echo htmlspecialchars($routine['teacher']); ?></div>
                                        </td>
                                        <td>
                                            <span class="room-badge"><?php echo htmlspecialchars($routine['room']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
