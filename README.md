# Clear-Pass: Student Clearance Management System

## 📖 Project Description
Clear-Pass is a full-stack web application designed to digitize and streamline the student clearance process for universities and colleges. It replaces the traditional manual "signature sheet" with a strict sequential workflow. Students can track their progress in real-time, and department heads can approve, hold, or reject clearances digitally. Once all departments are cleared, the system automatically generates a downloadable PDF Permit to Enroll with a verification QR code.

## ✨ Features
*   **Role-Based Access Control:** Separate dashboards for Students, Department Heads, and Administrators.
*   **Strict Sequential Workflow:** Students cannot be seen by the next department until the previous one approves them.
*   **Real-time Progress Tracking:** Students see a progress bar and visual timeline of their clearance steps.
*   **Department Management:** Admins can add new departments and drag-and-drop to reorder the clearance sequence.
*   **Automated PDF Permits:** Generates a formal certificate with a QR code upon completion.
*   **User Management:** Admin CRUD for students and department heads.
*   **My Profile:** All users can update their info and change their passwords.

## 💻 Tech Stack
*   **Frontend:** HTML5, Tailwind CSS (via CDN), Vanilla JavaScript, Lucide Icons.
*   **Backend:** PHP 8 (Procedural `mysqli` with prepared statements).
*   **Database:** MySQL / MariaDB.
*   **Local Server:** XAMPP.
*   **Hosting:** InfinityFree.

## 📁 Folder Structure
```text
systemjd/
├── admin/                  # Admin dashboards (users, departments, stats)
├── auth/                   # Login, logout, and registration handlers
├── database/               # SQL schema (clearpass_db.sql)
├── department/             # Department head dashboards and clearance processing
├── includes/               # Core files (db.php, functions.php, auth_check.php)
├── student/                # Student dashboards, initiate clearance, permit
├── index.html              # Login page
├── register.html           # Registration UI
├── profile.php             # Shared user profile page
├── .htaccess               # HTTPS force redirect
└── style.css               # Base stylesheet