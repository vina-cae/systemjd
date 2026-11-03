<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// Fetch Statistics using procedural mysqli
$stats = [
    'total_students' => 0,
    'total_clearances' => 0,
    'completed_clearances' => 0,
    'active_departments' => 0
];

$res = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'student'");
if ($row = mysqli_fetch_row($res)) { $stats['total_students'] = $row[0]; }

$res = mysqli_query($conn, "SELECT COUNT(*) FROM clearances");
if ($row = mysqli_fetch_row($res)) { $stats['total_clearances'] = $row[0]; }

$res = mysqli_query($conn, "SELECT COUNT(*) FROM clearances WHERE status = 'completed'");
if ($row = mysqli_fetch_row($res)) { $stats['completed_clearances'] = $row[0]; }

$res = mysqli_query($conn, "SELECT COUNT(*) FROM departments WHERE is_active = 1");
if ($row = mysqli_fetch_row($res)) { $stats['active_departments'] = $row[0]; }

// Fetch Recent Activity
$activities = [];
$activityQuery = "
    SELECT al.*, u.full_name, u.role 
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
";
$activityResult = mysqli_query($conn, $activityQuery);
if ($activityResult) {
    while ($row = mysqli_fetch_assoc($activityResult)) {
        $activities[] = $row;
    }
}

// Fetch Department Performance
$deptPerformance = [];
$deptQuery = "
    SELECT d.name,
           COUNT(CASE WHEN ci.status = 'approved' THEN 1 END) as approved,
           COUNT(CASE WHEN ci.status = 'pending' THEN 1 END) as pending,
           AVG(DATEDIFF(ci.approved_at, c.created_at)) as avg_days
    FROM departments d
    LEFT JOIN clearance_items ci ON d.id = ci.department_id
    LEFT JOIN clearances c ON ci.clearance_id = c.id
    GROUP BY d.id
    ORDER BY d.sequence_order
";
$deptResult = mysqli_query($conn, $deptQuery);
if ($deptResult) {
    while ($row = mysqli_fetch_assoc($deptResult)) {
        $deptPerformance[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Clear-Pass</title>
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
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg bg-indigo-800 transition-colors">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
                    Dashboard
                </a>
                <a href="departments.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="building-2" class="w-5 h-5 mr-3"></i>
                    Departments
                </a>
                <a href="users.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i>
                    Users
                </a>
                <a href="../profile.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="user" class="w-5 h-5 mr-3"></i>
                    My Profile
                </a>
                <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors text-red-300 hover:text-red-200">
                    <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                            <i data-lucide="user" class="w-4 h-4 text-indigo-600"></i>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total Students</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="graduation-cap" class="w-6 h-6 text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total Clearances</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_clearances']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="clipboard-list" class="w-6 h-6 text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Completed</p>
                                <p class="text-3xl font-bold text-green-600"><?php echo $stats['completed_clearances']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Departments</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['active_departments']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="building" class="w-6 h-6 text-orange-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Department Performance -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Department Performance</h2>
                        <div class="space-y-4">
                            <?php foreach ($deptPerformance as $dept): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-2 h-12 bg-indigo-600 rounded-full mr-3"></div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($dept['name']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            Avg. Processing: <?php echo $dept['avg_days'] ? round($dept['avg_days'], 1) : '0'; ?> days
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-green-600 font-semibold"><?php echo $dept['approved']; ?></span>
                                    <span class="text-gray-400">/</span>
                                    <span class="text-orange-600 font-semibold"><?php echo $dept['pending']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php if (empty($activities)): ?>
                                <p class="text-sm text-gray-500">No recent activity found.</p>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                <div class="flex items-start space-x-3 pb-3 border-b border-gray-100 last:border-0">
                                    <div class="w-8 h-8 rounded-full bg-<?php echo $activity['role'] === 'admin' ? 'red' : ($activity['role'] === 'student' ? 'blue' : 'green'); ?>-100 flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="<?php echo $activity['role'] === 'admin' ? 'user-cog' : ($activity['role'] === 'student' ? 'graduation-cap' : 'building-2'); ?>" class="w-4 h-4 text-<?php echo $activity['role'] === 'admin' ? 'red' : ($activity['role'] === 'student' ? 'blue' : 'green'); ?>-600"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($activity['full_name']); ?>
                                            <span class="text-xs font-normal text-gray-500 ml-1">(<?php echo $activity['role']; ?>)</span>
                                        </p>
                                        <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($activity['action']); ?></p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="departments.php?action=add" class="bg-indigo-600 text-white p-6 rounded-xl shadow-lg hover:bg-indigo-700 transition-colors flex items-center justify-between">
                        <div>
                            <p class="font-semibold">Manage Departments</p>
                            <p class="text-sm text-indigo-200 mt-1">Reorder sequence, add new</p>
                        </div>
                        <i data-lucide="arrow-right" class="w-6 h-6"></i>
                    </a>
                    
                    <a href="users.php?action=add" class="bg-white text-gray-900 p-6 rounded-xl shadow-lg border border-gray-200 hover:border-indigo-300 transition-colors flex items-center justify-between">
                        <div>
                            <p class="font-semibold">Add User</p>
                            <p class="text-sm text-gray-500 mt-1">Student or Department Head</p>
                        </div>
                        <i data-lucide="user-plus" class="w-6 h-6 text-indigo-600"></i>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>