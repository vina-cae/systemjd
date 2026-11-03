<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = sanitize($_POST['student_id']);
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if (strlen($password) < 8) {
        redirect('../register.html?error=Password must be at least 8 characters');
    }
    
    // Check if email or student ID exists
    $check_sql = "SELECT id FROM users WHERE email = ? OR user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, 'ss', $email, $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        redirect('../register.html?error=Email or Student ID already registered');
    }
    mysqli_stmt_close($stmt);
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin Transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert user
        $insert_sql = "INSERT INTO users (user_id, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'student', 'active')";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, 'ssss', $student_id, $email, $hashedPassword, $full_name);
        mysqli_stmt_execute($stmt);
        
        $userId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Log registration
        $log_sql = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'registration', 'New student registered', ?)";
        $stmt = mysqli_prepare($conn, $log_sql);
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($stmt, 'is', $userId, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        redirect('../index.html?success=Registration successful! Please login.');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        redirect('../register.html?error=Registration failed: ' . urlencode($e->getMessage()));
    }
} else {
    redirect('../register.html');
}
?>