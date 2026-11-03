<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clearance_id = (int)$_POST['clearance_id'];
    $student_id = (int)$_POST['student_id'];
    $status = sanitize($_POST['status']);
    $remarks = sanitize($_POST['remarks']);
    
    // Begin Transaction
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Update the clearance item for the current department
        $update_sql = "
            UPDATE clearance_items 
            SET status = ?, 
                remarks = ?, 
                approved_by = ?, 
                approved_at = NOW(),
                hold_reason = ?
            WHERE clearance_id = ? 
            AND department_id = (SELECT department_id FROM users WHERE id = ?)
        ";
        
        $hold_reason = ($status === 'on_hold') ? $remarks : null;
        
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'ssisii', $status, $remarks, $_SESSION['user_id'], $hold_reason, $clearance_id, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // 2. If approved, unlock the next department
        if ($status === 'approved') {
            // Get current department sequence
            $seq_sql = "SELECT d.sequence_order FROM clearance_items ci JOIN departments d ON ci.department_id = d.id WHERE ci.clearance_id = ? AND ci.approved_by = ? LIMIT 1";
            $seq_stmt = mysqli_prepare($conn, $seq_sql);
            mysqli_stmt_bind_param($seq_stmt, 'ii', $clearance_id, $_SESSION['user_id']);
            mysqli_stmt_execute($seq_stmt);
            $seq_result = mysqli_stmt_get_result($seq_stmt);
            $seq_row = mysqli_fetch_row($seq_result);
            $current_seq = $seq_row[0] ?? 0;
            mysqli_stmt_close($seq_stmt);
            
            // Unlock next department if it exists
            if ($current_seq > 0) {
                $next_seq = $current_seq + 1;
                $unlock_sql = "UPDATE clearance_items ci JOIN departments d ON ci.department_id = d.id SET ci.status = 'pending' WHERE ci.clearance_id = ? AND d.sequence_order = ?";
                $unlock_stmt = mysqli_prepare($conn, $unlock_sql);
                mysqli_stmt_bind_param($unlock_stmt, 'ii', $clearance_id, $next_seq);
                mysqli_stmt_execute($unlock_stmt);
                mysqli_stmt_close($unlock_stmt);
            }
        }
        
        // 3. Update overall progress of the clearance
        $progress_sql = "
            UPDATE clearances c
            SET progress_percent = (
                SELECT (COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100.0 / COUNT(*))
                FROM clearance_items 
                WHERE clearance_id = ?
            ),
            status = CASE 
                WHEN (SELECT COUNT(*) FROM clearance_items WHERE clearance_id = ? AND status = 'approved') = 
                     (SELECT COUNT(*) FROM clearance_items WHERE clearance_id = ?) 
                THEN 'completed'
                ELSE 'in_progress'
            END
            WHERE c.id = ?
        ";
        $progress_stmt = mysqli_prepare($conn, $progress_sql);
        mysqli_stmt_bind_param($progress_stmt, 'iiii', $clearance_id, $clearance_id, $clearance_id, $clearance_id);
        mysqli_stmt_execute($progress_stmt);
        mysqli_stmt_close($progress_stmt);
        
        // 4. Log activity
        logActivity($_SESSION['user_id'], 'clearance_action', "Updated clearance $clearance_id to $status");
        
        // 5. Send email notification
        $student_sql = "SELECT u.email, u.full_name, d.name as dept_name 
                        FROM clearances c 
                        JOIN users u ON c.student_id = u.id 
                        JOIN departments d ON d.id = (SELECT department_id FROM users WHERE id = ?)
                        WHERE c.id = ?";
        $student_stmt = mysqli_prepare($conn, $student_sql);
        mysqli_stmt_bind_param($student_stmt, 'ii', $_SESSION['user_id'], $clearance_id);
        mysqli_stmt_execute($student_stmt);
        $student_result = mysqli_stmt_get_result($student_stmt);
        $student = mysqli_fetch_assoc($student_result);
        mysqli_stmt_close($student_stmt);
        
        if ($student) {
            // Soft failure: if mail is not configured locally, this won't crash the page
            @sendStatusEmail($student['email'], $student['full_name'], $student['dept_name'], $status, $remarks);
        }
        
        // Commit Transaction
        mysqli_commit($conn);
        
        redirect('dashboard.php?success=Action completed successfully');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        redirect('dashboard.php?error=' . urlencode($e->getMessage()));
    }
} else {
    redirect('dashboard.php');
}
?>