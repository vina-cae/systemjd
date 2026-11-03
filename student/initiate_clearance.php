<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if student already has active clearance
$check_sql = "SELECT id, status FROM clearances WHERE student_id = ? AND status IN ('pending', 'in_progress') ORDER BY created_at DESC LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$existing = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if ($existing) {
    redirect('dashboard.php?error=You already have an active clearance request');
}

// Get current semester and school year
$currentMonth = date('n');
$semester = ($currentMonth >= 6 && $currentMonth <= 10) ? '1st Semester' : '2nd Semester';
$schoolYear = date('Y') . '-' . (date('Y') + 1);

try {
    mysqli_begin_transaction($conn);
    
    // Create new clearance record
    $insert_sql = "INSERT INTO clearances (student_id, semester, school_year, status, progress_percent) VALUES (?, ?, ?, 'pending', 0)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, 'iss', $_SESSION['user_id'], $semester, $schoolYear);
    mysqli_stmt_execute($insert_stmt);
    $clearanceId = mysqli_insert_id($conn);
    mysqli_stmt_close($insert_stmt);
    
    // Get all active departments in sequence
    $dept_sql = "SELECT id, sequence_order FROM departments WHERE is_active = 1 ORDER BY sequence_order";
    $dept_result = mysqli_query($conn, $dept_sql);
    
    // Create clearance items (first one pending, rest locked)
    $item_sql = "INSERT INTO clearance_items (clearance_id, department_id, status, sequence_number) VALUES (?, ?, ?, ?)";
    $item_stmt = mysqli_prepare($conn, $item_sql);
    
    $index = 0;
    while ($dept = mysqli_fetch_assoc($dept_result)) {
        $status = ($index === 0) ? 'pending' : 'locked';
        mysqli_stmt_bind_param($item_stmt, 'iisi', $clearanceId, $dept['id'], $status, $dept['sequence_order']);
        mysqli_stmt_execute($item_stmt);
        $index++;
    }
    mysqli_stmt_close($item_stmt);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'initiate_clearance', 'Started new clearance process');
    
    // Commit transaction
    mysqli_commit($conn);
    redirect('dashboard.php?success=Clearance process initiated successfully!');
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    redirect('dashboard.php?error=' . urlencode('Failed to initiate clearance: ' . $e->getMessage()));
}
?>