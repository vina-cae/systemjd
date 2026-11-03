<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['identifier']);
    $password = $_POST['password'];
    
    // Check if input is email or user_id
    $sql = "SELECT * FROM users WHERE (email = ? OR user_id = ?) AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            
            // Log activity using the helper function we created
            logActivity($user['id'], 'login', 'User logged in', $_SERVER['REMOTE_ADDR']);
            
            // Redirect based on role
            switch($user['role']) {
                case 'student':
                    redirect('../student/dashboard.php');
                    break;
                case 'department_head':
                    redirect('../department/dashboard.php');
                    break;
                case 'admin':
                    redirect('../admin/dashboard.php');
                    break;
                default:
                    redirect('../index.html?error=Invalid role detected');
            }
        } else {
            // Invalid password or user not found/active
            redirect('../index.html?error=Invalid credentials or inactive account');
        }
    } else {
        redirect('../index.html?error=Database error occurred');
    }
} else {
    // If someone tries to access login.php directly via URL
    redirect('../index.html');
}
?>