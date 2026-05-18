# Announcement Management System - Setup & Usage Guide

## Overview
The Announcement Management System allows teachers to create and publish announcements for specific classes and groups, which students and parents can then view in their dashboards.

## Setup Instructions

### Step 1: Create the Announcements Table
1. Open your browser and navigate to: `http://localhost/coaching-mgmt/setup-announcements-table.php`
2. Click the "Create Announcements Table" button
3. You should see a success message: "Announcements table created successfully!"
4. This step only needs to be done once

### Step 2: Access the Announcement Management System

#### For Teachers:
1. Log in to the teacher dashboard (`http://localhost/coaching-mgmt/admin/teacher-dashboard.php`)
2. Click on **"Announcements"** in the left sidebar menu
3. You'll be taken to the Teacher Announcements page

#### For Students:
1. Students will automatically see announcements in their dashboard under the "Latest Announcements" section
2. Announcements shown are only for their class and group (if applicable)

#### For Parents:
1. Parents will see announcements for their child's class and group in the parent dashboard
2. Announcements are displayed under the "Latest Announcements" section

## How to Create an Announcement

### Teacher Steps:
1. Navigate to **Admin > Announcements**
2. Select the **Class** from the dropdown (only classes you teach are shown)
3. (Optional) Select a specific **Group** within that class
4. Enter the announcement **Title** (max 255 characters)
5. Enter the **Message** (max 5000 characters)
6. Click **"Publish Announcement"**
7. A success message will appear with the announcement ID
8. The announcement will immediately appear in your "Published Announcements" list

### Managing Announcements:

**View Announcement:**
- Click the "View" button on any published announcement to see its full content in a modal

**Delete Announcement:**
- Click the "Delete" button to remove an announcement (irreversible)

## Database Schema

The system uses the following database table:

```sql
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    group_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
    INDEX (class_id, group_id),
    INDEX (teacher_id),
    INDEX (created_at DESC)
);
```

## API Endpoints

### For Teachers:
- **POST** `/admin/save-announcement.php` - Create new announcement
- **POST** `/admin/get-announcement.php` - Fetch single announcement details
- **POST** `/admin/delete-announcement.php` - Delete an announcement

### For Students & Parents:
- **POST** `/api/get-announcements.php` - Fetch announcements for viewing
  - Students: Returns announcements for their class/group
  - Parents: Pass `child_user_id` to get child's announcements

## Features

✅ **Teacher Dashboard Integration:** Announcements menu in teacher sidebar
✅ **Student Dashboard Integration:** Announcements card showing latest announcements
✅ **Parent Dashboard Integration:** Announcements card with child's announcements
✅ **Role-Based Filtering:** Students only see their class/group announcements
✅ **Permission Checks:** Only the creating teacher can edit/delete their announcements
✅ **Responsive Design:** Works on desktop, tablet, and mobile devices
✅ **Rich Display:** Shows teacher name, publication date, and time
✅ **View Expansion:** Click "View" to see the full announcement message

## Testing Checklist

- [ ] Announcements table created successfully
- [ ] Teacher can create announcement for their class
- [ ] Announcement appears in teacher's "Published" list
- [ ] Student sees announcement in their dashboard
- [ ] Parent sees announcement in their dashboard
- [ ] Announcement with group shows only to students in that group
- [ ] Teacher can view/delete their own announcements
- [ ] Announcements display with correct teacher name and date
- [ ] Mobile responsiveness works (check on phone/tablet)

## Troubleshooting

**Problem:** Announcements table doesn't exist
- **Solution:** Visit `setup-announcements-table.php` and click to create the table

**Problem:** Student doesn't see announcement
- **Solution:** Check that the announcement's class matches the student's class
- If a group is selected, verify the student is in that group

**Problem:** Teacher can't access announcements
- **Solution:** Ensure the teacher has subjects assigned to a class (permission requirement)

**Problem:** Announcements not loading in dashboard
- **Solution:** 
  - Check browser console for JavaScript errors
  - Verify the session is still active
  - Try refreshing the page

## Files Included

- `setup-announcements-table.php` - Database initialization script
- `admin/teacher-announcements.php` - Teacher UI for managing announcements
- `admin/save-announcement.php` - API endpoint to create announcements
- `admin/get-announcement.php` - API endpoint to fetch single announcement
- `admin/delete-announcement.php` - API endpoint to delete announcements
- `api/get-announcements.php` - API endpoint for student/parent viewing
- `student/dashboard.php` (modified) - Added announcements section
- `parent/dashboard.php` (modified) - Added announcements section
- `admin/teacher-dashboard.php` (modified) - Added announcements link

## Version Information

- System Version: 1.0
- Database: MySQL/MariaDB
- PHP Version: 7.4+
- Framework: Vanilla JS (No jQuery dependency)
- UI Framework: Bootstrap 5.3.0
