<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.html?error=Please login first');
}

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    die("User not found.");
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Update Info
    if ($_POST['action'] === 'update_info') {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        
        $updateStmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        mysqli_stmt_bind_param($updateStmt, 'ssi', $full_name, $email, $userId);
        
        if (mysqli_stmt_execute($updateStmt)) {
            $_SESSION['user_name'] = $full_name;
            $message = "Profile updated successfully!";
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            logActivity($userId, 'profile_update', 'Updated profile information');
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Change Password
    if ($_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if (!password_verify($old_pass, $user['password'])) {
            $error = "Incorrect current password.";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_pass) < 8) {
            $error = "New password must be at least 8 characters.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $passStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($passStmt, 'si', $hashed, $userId);
            if (mysqli_stmt_execute($passStmt)) {
                $message = "Password changed successfully!";
                $user['password'] = $hashed;
                logActivity($userId, 'password_change', 'Changed account password');
            } else {
                $error = "Failed to change password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Clear-Pass</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
            <span class="font-bold text-xl text-indigo-900">Clear-Pass</span>
            <div class="flex items-center space-x-4">
                <a href="javascript:history.back()" class="text-sm text-gray-600 hover:text-indigo-600 flex items-center">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back
                </a>
                <a href="auth/logout.php" class="text-sm text-red-500 hover:text-red-700 font-medium">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto p-6 mt-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <i data-lucide="user-circle" class="w-8 h-8 mr-3 text-indigo-600"></i>
            My Profile
        </h1>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Profile Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled class="w-full bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-gray-500 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled class="w-full bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-gray-500 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition-colors">Update Info</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Change Password</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" name="old_password" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="8" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-900 transition-colors">Change Password</button>
                </form>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>