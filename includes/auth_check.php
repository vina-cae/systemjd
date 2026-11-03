<?php
require_once 'db.php';

// 1. Check if user is logged in at all
if (!isLoggedIn()) {
    redirect('../index.html?error=Please login first');
}

// 2. Role-based access control
$currentRole = getUserRole();
// Get the name of the folder the current script is running in
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// If a student tries to access department or admin folders
if ($currentRole === 'student' && $currentDir !== 'student') {
    redirect('../student/dashboard.php');
}

// If a department head tries to access student or admin folders
if ($currentRole === 'department_head' && $currentDir !== 'department') {
    redirect('../department/dashboard.php');
}

// If an admin tries to access student or department folders
if ($currentRole === 'admin' && $currentDir !== 'admin') {
    redirect('../admin/dashboard.php');
}
?>