<?php
// Email notification function
function sendStatusEmail($to, $studentName, $department, $status, $remarks = '') {
    $subject = "Clearance Update - $department";
    
    $statusText = [
        'approved' => 'APPROVED',
        'rejected' => 'REJECTED',
        'on_hold' => 'PLACED ON HOLD',
        'pending' => 'PENDING REVIEW'
    ];
    
    $color = [
        'approved' => '#10b981',
        'rejected' => '#ef4444',
        'on_hold' => '#f59e0b',
        'pending' => '#3b82f6'
    ];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
            .content { background: #f9fafb; padding: 20px; margin: 20px 0; }
            .status { color: {$color[$status]}; font-weight: bold; font-size: 18px; }
            .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Clear-Pass Notification</h2>
            </div>
            <div class='content'>
                <p>Hello $studentName,</p>
                <p>Your clearance request for <strong>$department</strong> has been updated.</p>
                
                <p>Status: <span class='status'>" . $statusText[$status] . "</span></p>
                
                " . ($remarks ? "<p><strong>Remarks:</strong><br>" . nl2br(htmlspecialchars($remarks)) . "</p>" : "") . "
                
                <p>Log in to your dashboard to view details:<br>
                <a href='https://clearpass.edu/student/dashboard.php' style='color: #4f46e5;'>https://clearpass.edu/student/dashboard.php</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from Clear-Pass System.<br>
                Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Clear-Pass <noreply@clearpass.edu>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Generate QR Code URL (using QRServer API)
function generateQRUrl($data, $size = 150) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
}

// Log activity using procedural mysqli
function logActivity($userId, $action, $details = '', $ip = null) {
    global $conn; // Use the global connection from db.php
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isss', $userId, $action, $details, $ip);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

// Get student progress using procedural mysqli
function getStudentProgress($studentId) {
    global $conn;
    
    $sql = "
        SELECT 
            COUNT(CASE WHEN ci.status = 'approved' THEN 1 END) as approved,
            COUNT(ci.id) as total,
            c.status as clearance_status
        FROM clearances c
        LEFT JOIN clearance_items ci ON c.id = ci.clearance_id
        WHERE c.student_id = ?
        ORDER BY c.created_at DESC
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $studentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$row || $row['total'] == 0) return 0;
        return round(($row['approved'] / $row['total']) * 100);
    }
    return 0;
}
?>