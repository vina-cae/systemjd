<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Handle Drag & Drop Reordering
    if ($_POST['action'] === 'reorder' && isset($_POST['orders'])) {
        $orders = json_decode($_POST['orders'], true);
        
        $updateStmt = mysqli_prepare($conn, "UPDATE departments SET sequence_order = ? WHERE id = ?");
        
        foreach ($orders as $id => $order) {
            mysqli_stmt_bind_param($updateStmt, 'ii', $order, $id);
            mysqli_stmt_execute($updateStmt);
        }
        mysqli_stmt_close($updateStmt);
        
        logActivity($_SESSION['user_id'], 'reorder_departments', 'Updated department sequence');
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // 2. Handle Adding a New Department
    if ($_POST['action'] === 'add') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $color = sanitize($_POST['color']);
        
        // Get next sequence order
        $maxRes = mysqli_query($conn, "SELECT MAX(sequence_order) as max_order FROM departments");
        $maxRow = mysqli_fetch_assoc($maxRes);
        $nextOrder = ($maxRow['max_order'] ?? 0) + 1;
        
        $insertStmt = mysqli_prepare($conn, "INSERT INTO departments (name, description, sequence_order, color) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($insertStmt, 'ssis', $name, $description, $nextOrder, $color);
        
        if (mysqli_stmt_execute($insertStmt)) {
            logActivity($_SESSION['user_id'], 'add_department', "Added department: $name");
            redirect('departments.php?success=Department added successfully');
        } else {
            redirect('departments.php?error=Failed to add department');
        }
    }
    
    // 3. Handle Toggling Active/Inactive Status
    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        
        $toggleStmt = mysqli_prepare($conn, "UPDATE departments SET is_active = NOT is_active WHERE id = ?");
        mysqli_stmt_bind_param($toggleStmt, 'i', $id);
        
        if (mysqli_stmt_execute($toggleStmt)) {
            logActivity($_SESSION['user_id'], 'toggle_department', "Toggled status for department ID: $id");
            redirect('departments.php?success=Department status updated');
        } else {
            redirect('departments.php?error=Failed to update status');
        }
    }
}

// Fetch all departments
$departments = [];
$deptQuery = mysqli_query($conn, "SELECT * FROM departments ORDER BY sequence_order ASC");
if ($deptQuery) {
    while ($row = mysqli_fetch_assoc($deptQuery)) {
        $departments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments | Clear-Pass Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); }
        .sortable-ghost { opacity: 0.4; background: #e0e7ff; }
        .sortable-drag { cursor: grabbing; }
        .dept-item { transition: all 0.2s; }
        .dept-item:hover { transform: translateX(4px); }
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
                <a href="departments.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg bg-indigo-800 transition-colors">
                    <i data-lucide="building-2" class="w-5 h-5 mr-3"></i>
                    Departments
                </a>
                <a href="users.php" class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-colors">
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
                    <h1 class="text-2xl font-bold text-gray-900">Manage Departments</h1>
                    <button onclick="openAddModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        Add Department
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

                <!-- Info Banner -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg">
                    <div class="flex">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500 mr-3"></i>
                        <div>
                            <p class="text-sm text-blue-700">
                                <strong>Sequential Workflow:</strong> Drag and drop to reorder departments. 
                                Students must clear each step before proceeding to the next.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Departments List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-900">Clearance Sequence</h2>
                        <span class="text-sm text-gray-500" id="saveStatus"></span>
                    </div>
                    
                    <div id="departmentList" class="divide-y divide-gray-200">
                        <?php foreach ($departments as $dept): ?>
                        <div class="dept-item p-6 flex items-center justify-between bg-white cursor-move" data-id="<?php echo $dept['id']; ?>">
                            <div class="flex items-center flex-1">
                                <div class="mr-4 text-gray-400">
                                    <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-<?php echo $dept['color']; ?>-100 flex items-center justify-center mr-4">
                                    <i data-lucide="building-2" class="w-5 h-5 text-<?php echo $dept['color']; ?>-600"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($dept['name']); ?></h3>
                                        <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full bg-<?php echo $dept['is_active'] ? 'green' : 'gray'; ?>-100 text-<?php echo $dept['is_active'] ? 'green' : 'gray'; ?>-800">
                                            <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span class="ml-2 text-xs text-gray-400">Step <?php echo $dept['sequence_order']; ?></span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($dept['description']); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                    <button type="submit" class="p-2 text-gray-400 hover:text-gray-600" title="Toggle Active">
                                        <i data-lucide="<?php echo $dept['is_active'] ? 'eye' : 'eye-off'; ?>" class="w-5 h-5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sequence Preview -->
                <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Visual Workflow Preview</h3>
                    <div class="flex items-center space-x-2 overflow-x-auto pb-4">
                        <?php foreach ($departments as $index => $dept): ?>
                        <?php if ($dept['is_active']): ?>
                        <div class="flex items-center flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-<?php echo $dept['color']; ?>-100 flex items-center justify-center border-2 border-<?php echo $dept['color']; ?>-300">
                                <span class="text-<?php echo $dept['color']; ?>-600 font-bold text-sm"><?php echo $index + 1; ?></span>
                            </div>
                            <?php if ($index < count($departments) - 1): ?>
                            <div class="w-8 h-0.5 bg-gray-300 mx-2"></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Department Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Add New Department</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                    <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="e.g., Guidance Office">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Clearance requirements..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                    <select name="color" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="blue">Blue</option>
                        <option value="green">Green</option>
                        <option value="purple">Purple</option>
                        <option value="red">Red</option>
                        <option value="orange">Orange</option>
                        <option value="indigo">Indigo</option>
                    </select>
                </div>
                
                <div class="pt-4 flex space-x-3">
                    <button type="button" onclick="closeAddModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Add Department</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const el = document.getElementById('departmentList');
        const sortable = Sortable.create(el, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            handle: '.dept-item',
            onEnd: function() {
                saveOrder();
            }
        });
        
        function saveOrder() {
            const items = document.querySelectorAll('.dept-item');
            const orders = {};
            
            items.forEach((item, index) => {
                orders[item.dataset.id] = index + 1;
            });

            fetch('departments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reorder&orders=' + JSON.stringify(orders)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('saveStatus').textContent = 'Order saved!';
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                        location.reload(); // Reload to update step numbers
                    }, 1000);
                }
            });
        }
        
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });
    </script>
</body>
</html>