<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Add New User
    if ($_POST['action'] === 'add') {
        $user_id = sanitize($_POST['user_id']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check for duplicates
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR user_id = ?");
        mysqli_stmt_bind_param($checkStmt, 'ss', $email, $user_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_fetch_assoc($checkResult)) {
            redirect('users.php?error=Email or ID already exists');
        }
        mysqli_stmt_close($checkStmt);
        
        // Insert
        $insertStmt = mysqli_prepare($conn, "INSERT INTO users (user_id, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        mysqli_stmt_bind_param($insertStmt, 'sssss', $user_id, $email, $password, $full_name, $role);
        
        if (mysqli_stmt_execute($insertStmt)) {
            logActivity($_SESSION['user_id'], 'add_user', "Added new $role: $full_name");
            redirect('users.php?success=User added successfully');
        } else {
            redirect('users.php?error=Failed to add user');
        }
    }
    
    // 2. Edit Existing User
    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        
        $updateStmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        mysqli_stmt_bind_param($updateStmt, 'sssi', $full_name, $email, $role, $id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            logActivity($_SESSION['user_id'], 'edit_user', "Updated user ID: $id");
            redirect('users.php?success=User updated successfully');
        } else {
            redirect('users.php?error=Failed to update user');
        }
    }
    
    // 3. Toggle Active/Inactive Status
    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        
        $toggleStmt = mysqli_prepare($conn, "UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
        mysqli_stmt_bind_param($toggleStmt, 'i', $id);
        
        if (mysqli_stmt_execute($toggleStmt)) {
            logActivity($_SESSION['user_id'], 'toggle_user', "Toggled status for user ID: $id");
            redirect('users.php?success=User status updated');
        } else {
            redirect('users.php?error=Failed to update status');
        }
    }
}

// Fetch All Users
$users = [];
$query = "SELECT * FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Clear-Pass Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-indigo-900 text-white flex-shrink-0">
            <div class="p-6 flex items-center space-x-3">
                <i data-lucide="shield-check" class="w-8 h-8"></i>
                <span class="text-xl font-bold">Clear-Pass</span>
            </div>
            
            <nav class="mt-6 px-4 space-y-1">
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
                    Dashboard
                </a>
                <a href="departments.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="building-2" class="w-5 h-5 mr-3"></i>
                    Departments
                </a>
                <a href="users.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg bg-indigo-800 transition-colors">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i>
                    Users
                </a>
                <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors text-red-300 hover:text-red-200">
                    <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                    <button onclick="openAddModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center">
                        <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i>
                        Add User
                    </button>
                </div>
            </header>

            <div class="p-8">
                <!-- Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">User Details</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['user_id']); ?> | <?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 capitalize">
                                        <?php echo str_replace('_', ' ', $user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> capitalize">
                                        <?php echo $user['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-indigo-600 hover:text-indigo-900 p-2 bg-indigo-50 rounded" title="Edit User">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-gray-600 hover:text-gray-900 p-2 bg-gray-100 rounded" title="Toggle Status">
                                            <i data-lucide="<?php echo $user['status'] === 'active' ? 'user-x' : 'user-check'; ?>" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add New User</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                    <input type="text" name="user_id" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" class="w-full border rounded-lg px-4 py-2">
                        <option value="student">Student</option>
                        <option value="department_head">Department Head</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div class="pt-2 flex space-x-3">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 px-4 py-2 border rounded-lg text-gray-700">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit User</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="edit_email" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" id="edit_role" class="w-full border rounded-lg px-4 py-2">
                        <option value="student">Student</option>
                        <option value="department_head">Department Head</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="pt-2 flex space-x-3">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 px-4 py-2 border rounded-lg text-gray-700">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function openEditModal(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('editModal').classList.remove('hidden');
        }
    </script>
</body>
</html>