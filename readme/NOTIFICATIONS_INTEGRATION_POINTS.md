# Notification System Integration Points

This document lists exactly where to integrate notifications in existing code files.

## 1. Admin Dashboard - Add Bell Icon

**File:** `admin/dashboard.php`

**Location:** Find the navbar/topbar section (around line with "Logout" button)

**Add this code:**

```php
<!-- Include notification helpers at the top of the file -->
<?php
require_once '../includes/notification_helpers.php';
?>

<!-- In the navbar/header section, add the bell icon HTML before the logout button -->
<div class="notifications-bell" id="notificationsBell" title="Notifications" style="position: relative; cursor: pointer; font-size: 1.3rem; margin-right: 15px; color: #333;">
    <i class="fas fa-bell"></i>
    <span class="notification-badge hidden" id="notificationBadge" style="position: absolute; top: -8px; right: -8px; background: #ff4757; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; border: 2px solid white;">0</span>
    <div class="notifications-popup" id="notificationsPopup" style="position: absolute; top: 50px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); width: 380px; max-height: 500px; overflow-y: auto; z-index: 1050; display: none;">
        <div class="notification-header" style="padding: 15px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; background: #f8f9fa;">
            <span>Notifications</span>
            <button style="background: none; border: none; color: #666; cursor: pointer; font-size: 1.2rem;" onclick="closeNotificationsPopup()">&times;</button>
        </div>
        <div id="notificationsContainer">
            <div style="padding: 30px 15px; text-align: center; color: #999; font-size: 0.9rem;">Loading notifications...</div>
        </div>
        <div style="padding: 12px 15px; text-align: center; border-top: 1px solid #e0e0e0; font-size: 0.85rem;">
            <a onclick="markAllAsRead(); return false;" style="color: #667eea; text-decoration: none; font-weight: 600; cursor: pointer;">Mark all as read</a>
        </div>
    </div>
</div>
```

**Add this JavaScript at the end of the file (before closing body tag):**

```javascript
<script>
// Notification Bell Functions
document.addEventListener('click', (e) => {
    const popup = document.getElementById('notificationsPopup');
    const bell = document.getElementById('notificationsBell');
    if (popup && bell && !popup.contains(e.target) && !bell.contains(e.target)) {
        closeNotificationsPopup();
    }
});

function closeNotificationsPopup() {
    const popup = document.getElementById('notificationsPopup');
    if (popup) popup.classList.remove('show');
}

function toggleNotificationsPopup() {
    const popup = document.getElementById('notificationsPopup');
    if (popup) popup.classList.toggle('show');
}

document.getElementById('notificationsBell').addEventListener('click', () => {
    toggleNotificationsPopup();
    if (document.getElementById('notificationsPopup').classList.contains('show')) {
        loadNotifications();
    }
});

function loadNotifications() {
    fetch('../api/get-notifications.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                displayNotifications(data.notifications);
            }
        })
        .catch(err => console.error('Error loading notifications:', err));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function displayNotifications(notifications) {
    const container = document.getElementById('notificationsContainer');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div style="padding: 30px 15px; text-align: center; color: #999; font-size: 0.9rem;"><i class="fas fa-check-circle me-2"></i>All caught up! No new notifications.</div>';
        return;
    }

    let html = '';
    notifications.forEach(notif => {
        const isUnread = notif.is_read == 0;
        const createdDate = new Date(notif.created_at);
        const now = new Date();
        const diffMs = now - createdDate;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        let timeStr = '';
        if (diffMins < 1) timeStr = 'Just now';
        else if (diffMins < 60) timeStr = diffMins + 'm ago';
        else if (diffHours < 24) timeStr = diffHours + 'h ago';
        else if (diffDays < 7) timeStr = diffDays + 'd ago';
        else timeStr = createdDate.toLocaleDateString();

        html += `
            <div style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s ease; position: relative; ${isUnread ? 'background: #f0f7ff; padding-left: 20px;' : ''}">
                ${isUnread ? '<div style="content: \'\'; position: absolute; left: 0; width: 4px; height: 100%; background: #667eea;"></div>' : ''}
                <div style="font-weight: 600; color: #2c3e66; margin-bottom: 4px; font-size: 0.95rem;">${escapeHtml(notif.title)}</div>
                <div style="color: #666; font-size: 0.85rem; margin-bottom: 6px; line-height: 1.4;">${escapeHtml(notif.message || '')}</div>
                <div style="font-size: 0.75rem; color: #999;">${timeStr}</div>
                <div style="display: flex; gap: 8px; margin-top: 8px;">
                    ${notif.action_url ? `<button style="padding: 4px 8px; font-size: 0.75rem; border: none; border-radius: 4px; cursor: pointer; background: #667eea; color: white;" onclick="goToAction('${escapeHtml(notif.action_url)}')"><i class="fas fa-arrow-right me-1"></i>View</button>` : ''}
                    <button style="padding: 4px 8px; font-size: 0.75rem; border: none; border-radius: 4px; cursor: pointer; background: #e0e0e0; color: #666;" onclick="deleteNotification(${notif.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function deleteNotification(notifId) {
    if (confirm('Delete this notification?')) {
        fetch('../api/delete-notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notifId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) loadNotifications();
        });
    }
}

function goToAction(url) {
    closeNotificationsPopup();
    window.location.href = url;
}

function markAllAsRead() {
    const unreadNotifs = document.querySelectorAll('[style*="background: #f0f7ff"]');
    let count = 0;
    unreadNotifs.forEach(notif => {
        const button = notif.querySelector('button[onclick*="deleteNotification"]');
        if (button) {
            const onclick = button.getAttribute('onclick');
            const notifId = onclick.match(/\d+/)[0];
            fetch('../api/mark-notification-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notifId })
            });
            count++;
        }
    });
    if (count > 0) setTimeout(() => loadNotifications(), 500);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initial load and auto-refresh
loadNotifications();
setInterval(loadNotifications, 30000);
</script>
```

## 2. Trigger: Notify Admin on Pending Admissions

**File:** `admin/admission-management.php` or wherever admissions are processed

**Add at the top:**
```php
require_once '../includes/notification_helpers.php';
```

**Add after approving an admission:**
```php
// After admission is approved/updated
notifyAdminPendingAdmissions($conn);
```

## 3. Trigger: Notify Students on New Result

**File:** `admin/save-marks.php` or `admin/save-result.php`

**Add at the top:**
```php
require_once '../includes/notification_helpers.php';
```

**Add after saving marks/results:**
```php
// After inserting/updating result in database
if ($class_id && $subject_id && $test_name) {
    notifyStudentsNewResult($conn, $class_id, $subject_id, $test_name);
}
```

## 4. Trigger: Notify Parents on Fee Due

**File:** `api/setup-monthly-fees.php` or fee calculation logic

**Add at the top:**
```php
require_once '../includes/notification_helpers.php';
```

**Add when creating monthly fees:**
```php
// After creating monthly fee record
if ($student_id && $month_name && $due_amount) {
    notifyParentFeeDue($conn, $student_id, $month_name, $due_amount);
}
```

## 5. Trigger: Notify Teachers on Routine Update

**File:** `admin/add_routine.php` or routine saving logic

**Add at the top:**
```php
require_once '../includes/notification_helpers.php';
```

**Add after saving routine:**
```php
// After routine is saved
// Get all teachers for this class/group
$teachers_query = "SELECT DISTINCT teacher_id FROM teacher_subjects WHERE class_id = $class_id";
$teachers_result = mysqli_query($conn, $teachers_query);

while ($teacher_row = mysqli_fetch_assoc($teachers_result)) {
    $teacher_id = intval($teacher_row['teacher_id']);
    notifyTeacherRoutineUpdated($conn, $teacher_id, $class_group, $class_name);
}
```

## 6. Optional: Parent & Teacher Dashboard Updates

**File:** `parent/dashboard.php`

Add bell icon to the user-menu section (similar to student dashboard)

**File:** `admin/teacher-dashboard.php`

Add bell icon to the navbar section (similar to admin dashboard)

---

## Testing Checklist

- [ ] Notifications table created successfully
- [ ] Bell icon appears on student dashboard with 0 unread
- [ ] Can click bell to open popup
- [ ] Can mark notification as read
- [ ] Can delete notification
- [ ] Unread count badge shows correctly
- [ ] Admin gets notification when admission pending
- [ ] Student gets notification when result added
- [ ] Parent gets notification when fee due
- [ ] Teacher gets notification when routine updated

---

**Implementation Status:**
- ✓ Student Dashboard - DONE
- ⏳ Admin Dashboard - READY TO INTEGRATE
- ⏳ Parent Dashboard - READY TO INTEGRATE
- ⏳ Teacher Dashboard - READY TO INTEGRATE
- ⏳ Notification Triggers - READY TO INTEGRATE
