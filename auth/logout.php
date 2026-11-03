<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    // Log the logout using the helper function
    logActivity($_SESSION['user_id'], 'logout', 'User logged out', $_SERVER['REMOTE_ADDR']);
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
redirect('../index.html?message=Logged out successfully');
?>