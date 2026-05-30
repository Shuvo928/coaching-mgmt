<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$allowedMimes = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/ogg' => 'ogg',
];
$maxFileSize = 150 * 1024 * 1024; // 150 MB
$assetsDir = realpath(__DIR__ . '/../assets/videos');
$customVideoPattern = glob($assetsDir . '/hero-video.*');
$currentVideoExists = !empty($customVideoPattern) && file_exists($customVideoPattern[0]);
$currentVideoUrl = $currentVideoExists ? '../assets/videos/' . basename($customVideoPattern[0]) : '';
$message = '';
$messageClass = 'alert-info';

function cleanupHeroVideoFiles(string $assetsDir): void {
    foreach (glob($assetsDir . '/hero-video.*') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_video'])) {
        if (empty($_FILES['hero_video']['name'])) {
            $message = 'Please choose a video file to upload.';
            $messageClass = 'alert-warning';
        } elseif ($_FILES['hero_video']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Upload failed. Please try again.';
            $messageClass = 'alert-danger';
        } elseif ($_FILES['hero_video']['size'] > $maxFileSize) {
            $message = 'File is too large. Maximum size is 150 MB.';
            $messageClass = 'alert-danger';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['hero_video']['tmp_name']);
            if (!array_key_exists($mime, $allowedMimes)) {
                $message = 'Invalid video format. Allowed formats are MP4, WEBM, and OGG.';
                $messageClass = 'alert-danger';
            } else {
                $extension = $allowedMimes[$mime];
                $destination = $assetsDir . '/hero-video.' . $extension;
                cleanupHeroVideoFiles($assetsDir);
                if (move_uploaded_file($_FILES['hero_video']['tmp_name'], $destination)) {
                    chmod($destination, 0644);
                    $currentVideoExists = true;
                    $currentVideoUrl = '../assets/videos/' . basename($destination);
                    $message = 'Homepage hero video uploaded successfully.';
                    $messageClass = 'alert-success';
                } else {
                    $message = 'Unable to save the uploaded video. Check folder permissions.';
                    $messageClass = 'alert-danger';
                }
            }
        }
    }

    if (isset($_POST['delete_video'])) {
        if ($currentVideoExists) {
            cleanupHeroVideoFiles($assetsDir);
            $currentVideoExists = false;
            $currentVideoUrl = '';
            $message = 'Custom homepage video deleted. The default video will now display.';
            $messageClass = 'alert-success';
        } else {
            $message = 'No custom video is currently uploaded.';
            $messageClass = 'alert-warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Video Settings | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 220px; background: #1f2937; color: #fff; padding: 20px; }
        .sidebar-header { text-align: center; margin-bottom: 30px; }
        .sidebar-header h3 { margin: 0 0 6px; font-size: 22px; }
        .sidebar-header small { color: #9ca3af; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; color: #e5e7eb; text-decoration: none; border-radius: 8px; transition: background .2s; }
        .menu-item:hover, .menu-item.active { background: #111827; color: #fff; }
        .menu-item i { width: 22px; text-align: center; }
        .main-content { flex: 1; padding: 28px 32px; }
        .page-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
        .video-preview { border-radius: 16px; background: #fff; padding: 20px; box-shadow: 0 10px 30px -16px rgba(15,23,42,.3); }
        .card { border: none; border-radius: 18px; }
        .alert { border-radius: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-2x"></i>
                <h3>Coaching</h3>
                <small>Admin Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admission-management.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Admissions</span>
                </a>
                <a href="student-management.php" class="menu-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                </a>
                <a href="teacher-management.php" class="menu-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
                </a>
                <a href="routine-management.php" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Routine Management</span>
                </a>
                <a href="result-system.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Result System</span>
                </a>
                <a href="fees-management.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Fees Management</span>
                </a>
                <a href="home-video.php" class="menu-item active">
                    <i class="fas fa-video"></i>
                    <span>Homepage Video</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="page-heading">
                <div>
                    <h2>Homepage Hero Video</h2>
                    <p class="text-muted">Upload or delete the current hero video shown on the public homepage.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo htmlspecialchars($messageClass); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card video-preview mb-4">
                <div class="card-body">
                    <h5 class="card-title">Current Homepage Video</h5>
                    <?php if ($currentVideoExists): ?>
                        <video width="100%" controls muted playsinline>
                            <source src="<?php echo htmlspecialchars($currentVideoUrl); ?>" type="video/<?php echo htmlspecialchars(pathinfo($currentVideoUrl, PATHINFO_EXTENSION)); ?>">
                            Your browser does not support the video tag.
                        </video>
                        <p class="mt-3 mb-0 text-muted">Custom video is active. It will replace the default homepage hero video.</p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">No custom video uploaded. The default hero video will display on the homepage.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Upload New Hero Video</h5>
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="hero_video" class="form-label">Choose a video file</label>
                            <input type="file" class="form-control" id="hero_video" name="hero_video" accept="video/mp4,video/webm,video/ogg">
                            <div class="form-text">Allowed formats: MP4, WEBM, OGG. Max size: 150 MB.</div>
                        </div>
                        <button type="submit" name="upload_video" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Video
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Delete Current Custom Video</h5>
                    <form action="" method="post">
                        <button type="submit" name="delete_video" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Delete Custom Video
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
