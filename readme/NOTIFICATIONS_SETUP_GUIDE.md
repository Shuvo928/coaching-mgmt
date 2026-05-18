# Notification System Setup Guide

## Overview
The notification system automatically creates and displays notifications for important events across the platform with automatic 7-day retention and a simple date-ordered display.

## Features
✓ Automatic notifications for admissions, results, fees, and routines  
✓ Role-based filtering (admin, teacher, student, parent)  
✓ Unread/read status tracking  
✓ Auto-delete after 7 days  
✓ Bell icon with unread count badge  
✓ View-only actions (no quick actions)  
✓ In-app only delivery

## Installation Steps

### Step 1: Create Database Table
1. Open your browser and navigate to:
   ```
   http://localhost/coaching-mgmt/setup-notifications-table.php
   ```
2. Click the "Create Notifications Table" button
3. You should see a success message

### Step 2: Add Helper Functions
The helper functions are already in:
```
includes/notification_helpers.php
```

**Main Functions:**
- `createNotification()` - Create a notification
- `notifyStudentsNewResult()` - Notify all students in a class
- `notifyAdminPendingAdmissions()` - Notify admins of pending approvals
- `notifyParentFeeDue()` - Notify parents about fees
- `notifyTeacherRoutineUpdated()` - Notify teachers about routines
- `getUnreadNotificationCount()` - Get unread count for user
- `clearExpiredNotifications()` - Delete expired notifications

### Step 3: API Endpoints Created

**Automatically Generated:**
- `api/get-notifications.php` - Fetch notifications for current user
- `api/mark-notification-read.php` - Mark notification as read
- `api/delete-notification.php` - Delete a notification

### Step 4: Update Dashboards
Add notification bell to each dashboard:

**Student Dashboard** (DONE ✓)
- File: `student/dashboard.php`
- Features:
  - Bell icon in topbar with unread badge
  - Notification popup shows last 10 notifications
  - Auto-refresh every 30 seconds
  - Mark all as read button

**Parent Dashboard** (DO THIS)
- Add to `parent/dashboard.php` in the user-menu section
- Same features as student dashboard

**Admin Dashboard** (DO THIS)
- Add to `admin/dashboard.php` in the navbar
- Same features as student dashboard

**Teacher Dashboard** (DO THIS)
- Add to `admin/teacher-dashboard.php` in the navbar
- Same features as student dashboard

### Step 5: Integrate Notification Triggers

**When to Create Notifications:**

**1. Admin Notifications - Pending Admissions**
```php
// In admin/save-admission.php or after approval logic:
require_once '../includes/notification_helpers.php';
notifyAdminPendingAdmissions($conn);
```

**2. Student Notifications - New Results**
```php
// In admin/save-marks.php or save-result.php (after inserting result):
notifyStudentsNewResult($conn, $class_id, $subject_id, $test_name);
```

**3. Parent Notifications - Fee Due**
```php
// In api/setup-monthly-fees.php or fee collection logic:
notifyParentFeeDue($conn, $student_id, $fee_month, $due_amount);
```

**4. Teacher Notifications - Routine Updated**
```php
// In admin/save-routine.php (after routine is saved):
notifyTeacherRoutineUpdated($conn, $teacher_id, $class_group, $class_name);
```

## Notification Types

### Admin: approval
- Triggered: New admission application received
- Title: "Pending Admissions Review"
- Message: "You have X pending application(s) awaiting approval"
- Action: admin/admission-management.php

### Teacher: routine
- Triggered: Class routine is set/updated
- Title: "Class Routine Available"
- Message: "Your class routine for [class] ([group]) has been set"
- Action: admin/teacher-dashboard.php#routine-section

### Student: result
- Triggered: New result is entered
- Title: "New Result Added: [Subject] - [Test]"
- Message: "Your result for [Subject] ([Test]) has been entered"
- Action: student/dashboard.php

### Parent: fees
- Triggered: Monthly fee is due
- Title: "Fee Payment Due - [Month]"
- Message: "Fee payment for [Student] is due for [Month]. Amount: ৳X"
- Action: parent/fees.php

## Database Schema

```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    recipient_role VARCHAR(50) NOT NULL,  -- admin, teacher, student, parent
    type VARCHAR(100) NOT NULL,            -- approval, routine, result, fees, etc
    title VARCHAR(255) NOT NULL,
    message TEXT,
    related_id INT,                        -- ID of related entity
    action_url VARCHAR(255),               -- Link to action
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,             -- Auto-delete after this date
    INDEX idx_recipient (recipient_id, recipient_role),
    INDEX idx_created (created_at DESC),
    INDEX idx_expires (expires_at)
);
```

## Testing

### Test 1: Check Notifications Table
```bash
# Run this in your database client
SELECT COUNT(*) as notification_count FROM notifications;
```

### Test 2: Create Test Notification
```php
<?php
require_once 'includes/db.php';
require_once 'includes/notification_helpers.php';

// Create a test notification for user ID 1
createNotification($conn, 1, 'student', 'result', 'Test Notification', 'This is a test message', null, 'student/dashboard.php');

echo "Test notification created!";
?>
```

### Test 3: Test API Endpoints
```bash
# Test get-notifications
curl http://localhost/coaching-mgmt/api/get-notifications.php

# Test mark-notification-read (requires valid notification ID)
curl -X POST http://localhost/coaching-mgmt/api/mark-notification-read.php \
  -H "Content-Type: application/json" \
  -d '{"notification_id":1}'
```

## Cleanup (Optional)

### Automatic Cleanup
Add to a daily cron job:
```php
<?php
require_once 'includes/db.php';
require_once 'includes/notification_helpers.php';
clearExpiredNotifications($conn);
?>
```

### Manual Cleanup
Run this periodically:
```bash
php -r "require 'includes/db.php'; require 'includes/notification_helpers.php'; clearExpiredNotifications(\$conn); echo 'Cleanup done';"
```

## Troubleshooting

### No notifications showing
1. Check notifications table exists: `SHOW TABLES LIKE 'notifications'`
2. Check API endpoint returns data: `api/get-notifications.php`
3. Check browser console for JavaScript errors (F12)
4. Verify session is active: Check `$_SESSION['user_id']` and `$_SESSION['role']`

### Notifications not appearing after event
1. Check notification is inserted: `SELECT * FROM notifications WHERE created_at > NOW() - INTERVAL 1 HOUR`
2. Verify `createNotification()` was called in the right place
3. Check recipient_id and recipient_role are correct
4. Clear browser cache and refresh

### Notifications showing but bell icon not visible
1. Check that notification CSS is loaded (check browser dev tools Styles tab)
2. Verify Font Awesome icons are loaded (check CSS file in head)
3. Clear browser cache: Ctrl+Shift+Delete

## File Summary

| File | Purpose |
|------|---------|
| `setup-notifications-table.php` | Database table setup |
| `api/get-notifications.php` | Fetch notifications |
| `api/mark-notification-read.php` | Mark as read |
| `api/delete-notification.php` | Delete notification |
| `includes/notification_helpers.php` | Helper functions |
| `student/dashboard.php` | Student UI (updated) |
| `parent/dashboard.php` | Parent UI (needs update) |
| `admin/dashboard.php` | Admin UI (needs update) |
| `admin/teacher-dashboard.php` | Teacher UI (needs update) |

## Next Steps

1. ✓ Create notifications table
2. ✓ Add student dashboard UI
3. Add parent dashboard UI
4. Add admin dashboard UI
5. Add teacher dashboard UI
6. Integrate notification triggers in existing code
7. Test all notification types
8. Set up daily cleanup job

---
**Last Updated:** 2024
**Status:** Bell icon added to student dashboard, ready for parent/admin/teacher integration
