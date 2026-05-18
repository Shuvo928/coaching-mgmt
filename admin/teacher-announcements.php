<?php
session_start();
require_once '../includes/db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher_query = "SELECT id FROM teachers WHERE user_id = '$user_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
if (!$teacher_result || mysqli_num_rows($teacher_result) == 0) {
    die("Teacher not found");
}
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

// Check if announcements table exists
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if (mysqli_num_rows($check_table) == 0) {
    die('<div style="padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px;">
        Announcements table not found. Please run <a href="../setup-announcements-table.php" style="color: #c00;">setup-announcements-table.php</a> first.
    </div>');
}

// Get available classes and groups
$available_classes = [];
$teacher_query = "SELECT DISTINCT c.id, c.class_name
                  FROM teacher_subjects ts
                  JOIN subjects s ON ts.subject_id = s.id
                  JOIN classes c ON s.class_id = c.id
                  WHERE ts.teacher_id = $teacher_id
                  ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $teacher_query);
while ($row = mysqli_fetch_assoc($classes_result)) {
    $available_classes[] = $row;
}

$available_groups = [];
$groups_query = "SELECT id, group_name FROM `groups` ORDER BY group_name";
$groups_result = mysqli_query($conn, $groups_query);
while ($row = mysqli_fetch_assoc($groups_result)) {
    $available_groups[] = $row;
}

// Fetch teacher's announcements
$announcements_query = "SELECT a.*, c.class_name, COALESCE(g.group_name, 'All Groups') AS group_name
                        FROM announcements a
                        JOIN classes c ON a.class_id = c.id
                        LEFT JOIN `groups` g ON a.group_id = g.id
                        WHERE a.teacher_id = $teacher_id
                        ORDER BY a.created_at DESC
                        LIMIT 100";
$announcements_result = mysqli_query($conn, $announcements_query);
$announcements = [];
while ($row = mysqli_fetch_assoc($announcements_result)) {
    $announcements[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 1.25rem 1.25rem 0 0 !important;
        }
        .form-control, .form-select {
            border-radius: 0.75rem;
            border: 1px solid #ced4da;
        }
        .btn-primary-custom {
            background: #2c3e66;
            border: none;
            border-radius: 2rem;
            padding: 0.5rem 1.5rem;
        }
        .btn-primary-custom:hover {
            background: #1a2332;
        }
        .announcement-card {
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .announcement-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e66;
            margin-bottom: 0.5rem;
        }
        .announcement-meta {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
        }
        .badge-class {
            background: #cfe2ff;
            color: #084298;
            padding: 0.35rem 0.65rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
        }
        .badge-group {
            background: #f0e7ff;
            color: #5e0fc8;
            padding: 0.35rem 0.65rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="container-fluid py-5">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bullhorn me-2 text-primary"></i>Announcements Management</h2>
            <a href="teacher-dashboard.php" class="btn btn-outline-secondary rounded-pill">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        <!-- Create Announcement Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle me-2 text-success"></i>Create New Announcement
            </div>
            <div class="card-body">
                <form id="announcementForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">📚 Class</label>
                            <select id="classSelect" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($available_classes as $cls): ?>
                                    <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">👥 Group (Optional)</label>
                            <select id="groupSelect" class="form-select">
                                <option value="">-- All Groups --</option>
                                <?php foreach ($available_groups as $grp): ?>
                                    <option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">📝 Announcement Title</label>
                        <input type="text" id="titleInput" class="form-control" placeholder="Enter announcement title" maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">💬 Announcement Message</label>
                        <textarea id="messageInput" class="form-control" rows="5" placeholder="Enter your announcement message..." style="resize: vertical;" required></textarea>
                        <small class="text-muted">You can use basic text formatting. Max 5000 characters.</small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-primary-custom">
                            <i class="fas fa-paper-plane me-2"></i>Publish Announcement
                        </button>
                        <button type="reset" class="btn btn-outline-secondary rounded-pill">
                            <i class="fas fa-redo me-2"></i>Clear Form
                        </button>
                    </div>
                </form>
                <div id="successMsg" class="alert alert-success mt-3" style="display: none;"></div>
                <div id="errorMsg" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
        </div>

        <!-- Published Announcements -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2 text-info"></i>Your Published Announcements
                <span class="badge bg-secondary float-end"><?= count($announcements) ?> Announcements</span>
            </div>
            <div class="card-body">
                <?php if (count($announcements) > 0): ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div class="announcement-title"><?= htmlspecialchars($ann['title']) ?></div>
                                    <div style="color: #495057; line-height: 1.6; margin: 0.75rem 0;">
                                        <?= htmlspecialchars(substr($ann['message'], 0, 150)) ?><?= strlen($ann['message']) > 150 ? '...' : '' ?>
                                    </div>
                                    <div class="announcement-meta">
                                        <span class="badge-class">📚 <?= htmlspecialchars($ann['class_name']) ?></span>
                                        <span class="badge-group">👥 <?= htmlspecialchars($ann['group_name']) ?></span>
                                        <span title="<?= $ann['created_at'] ?>">
                                            <i class="far fa-calendar me-1"></i><?= date('M d, Y \a\t H:i', strtotime($ann['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill me-2" 
                                            onclick="viewAnnouncement(<?= $ann['id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" 
                                            onclick="deleteAnnouncement(<?= $ann['id'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No announcements published yet. Create one above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Announcement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="modalTitle" style="font-weight: 600;"></h6>
                <div id="modalMeta" style="color: #6c757d; font-size: 0.9rem; margin: 0.75rem 0;"></div>
                <hr>
                <div id="modalMessage" style="color: #495057; line-height: 1.7;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const announcementForm = document.getElementById('announcementForm');
const classSelect = document.getElementById('classSelect');
const groupSelect = document.getElementById('groupSelect');
const titleInput = document.getElementById('titleInput');
const messageInput = document.getElementById('messageInput');
const successMsg = document.getElementById('successMsg');
const errorMsg = document.getElementById('errorMsg');

announcementForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const classId = parseInt(classSelect.value);
    const groupId = groupSelect.value ? parseInt(groupSelect.value) : null;
    const title = titleInput.value.trim();
    const message = messageInput.value.trim();

    if (!classId || !title || !message) {
        showError('Please fill in all required fields');
        return;
    }

    if (message.length > 5000) {
        showError('Message is too long (max 5000 characters)');
        return;
    }

    try {
        const response = await fetch('save-announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                class_id: classId,
                group_id: groupId,
                title: title,
                message: message
            })
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Announcement published successfully!');
            announcementForm.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            showError(data.message || 'Error publishing announcement');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
});

function showSuccess(msg) {
    successMsg.textContent = msg;
    successMsg.style.display = 'block';
    errorMsg.style.display = 'none';
}

function showError(msg) {
    errorMsg.textContent = msg;
    errorMsg.style.display = 'block';
    successMsg.style.display = 'none';
}

function viewAnnouncement(id) {
    fetch('get-announcement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('modalTitle').textContent = data.announcement.title;
            document.getElementById('modalMeta').innerHTML = 
                `<strong>Class:</strong> ${data.announcement.class_name} | ` +
                `<strong>Group:</strong> ${data.announcement.group_name} | ` +
                `<strong>Published:</strong> ${new Date(data.announcement.created_at).toLocaleString()}`;
            document.getElementById('modalMessage').innerHTML = data.announcement.message.replace(/\n/g, '<br>');
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
    })
    .catch(err => console.error('Error:', err));
}

function deleteAnnouncement(id) {
    if (confirm('Are you sure you want to delete this announcement?')) {
        fetch('delete-announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Announcement deleted successfully');
                location.reload();
            } else {
                alert('Error deleting announcement: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error deleting announcement');
        });
    }
}
</script>
</body>
</html>
