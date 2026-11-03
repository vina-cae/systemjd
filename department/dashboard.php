<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Get department head's department
$deptStmt = mysqli_prepare($conn, "SELECT d.* FROM users u JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
mysqli_stmt_bind_param($deptStmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($deptStmt);
$deptResult = mysqli_stmt_get_result($deptStmt);
$department = mysqli_fetch_assoc($deptResult);
mysqli_stmt_close($deptStmt);

if (!$department) {
    // If the admin hasn't assigned a department to this user yet
    die("Department not assigned to this account. Please contact the administrator.");
}

// Get pending clearances for this department (Sequential Logic: Only show if previous department approved)
$pendingSql = "
    SELECT c.id as clearance_id, c.student_id, c.created_at,
           u.user_id as student_number, u.full_name as student_name, u.email,
           prev_dept.id as prev_dept_id,
           prev_ci.status as prev_status
    FROM clearance_items ci
    JOIN clearances c ON ci.clearance_id = c.id
    JOIN users u ON c.student_id = u.id
    LEFT JOIN departments prev_dept ON prev_dept.sequence_order = (? - 1)
    LEFT JOIN clearance_items prev_ci ON prev_ci.clearance_id = c.id AND prev_ci.department_id = prev_dept.id
    WHERE ci.department_id = ?
    AND ci.status IN ('pending', 'on_hold')
    AND (prev_dept.id IS NULL OR prev_ci.status = 'approved')
    ORDER BY c.created_at ASC
";
$pendingStmt = mysqli_prepare($conn, $pendingSql);
mysqli_stmt_bind_param($pendingStmt, 'ii', $department['sequence_order'], $department['id']);
mysqli_stmt_execute($pendingStmt);
$pendingResult = mysqli_stmt_get_result($pendingStmt);

$pendingClearances = [];
if ($pendingResult) {
    while ($row = mysqli_fetch_assoc($pendingResult)) {
        $pendingClearances[] = $row;
    }
}
mysqli_stmt_close($pendingStmt);

// Get statistics
$statsSql = "
    SELECT 
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
    FROM clearance_items 
    WHERE department_id = ?
";
$statsStmt = mysqli_prepare($conn, $statsSql);
mysqli_stmt_bind_param($statsStmt, 'i', $department['id']);
mysqli_stmt_execute($statsStmt);
$statsResult = mysqli_stmt_get_result($statsStmt);
$stats = mysqli_fetch_assoc($statsResult);
mysqli_stmt_close($statsStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Dashboard | <?php echo htmlspecialchars($department['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlide 0.3s ease-out;
        }
        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-3">
                        <i data-lucide="shield-check" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <span class="text-xl font-bold text-gray-900">Clear-Pass</span>
                        <span class="text-sm text-gray-500 ml-2">| <?php echo htmlspecialchars($department['name']); ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../profile.php" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Profile</a>
                    <a href="../auth/logout.php" class="text-gray-500 hover:text-red-600">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Department Dashboard</h1>
            <p class="text-gray-600 mt-1">Manage student clearances and approvals</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Review</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['pending_count']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="clock" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Approved Today</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['approved_count']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Rejected/Hold</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['rejected_count']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="x-circle" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Processed</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_count']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="layers" class="w-6 h-6 text-gray-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sequential Locking Notice -->
        <?php if ($department['sequence_order'] > 1): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg">
            <div class="flex">
                <i data-lucide="info" class="w-5 h-5 text-blue-500 mr-3"></i>
                <div>
                    <p class="text-sm text-blue-700">
                        <strong>Sequential Locking Active:</strong> You can only see students who have been cleared by the previous department(s).
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Clearances Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Pending Clearances</h2>
                <span class="text-sm text-gray-500"><?php echo count($pendingClearances); ?> students waiting</span>
            </div>
            
            <?php if (empty($pendingClearances)): ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="check-check" class="w-8 h-8 text-gray-400"></i>
                </div>
                <p class="text-gray-500">No pending clearances at the moment.</p>
                <p class="text-sm text-gray-400 mt-1">Students will appear here once they reach this step.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($pendingClearances as $clearance): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <span class="text-indigo-600 font-semibold text-sm">
                                            <?php echo strtoupper(substr($clearance['student_name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($clearance['student_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($clearance['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($clearance['student_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($clearance['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="openActionModal(<?php echo $clearance['clearance_id']; ?>, '<?php echo htmlspecialchars($clearance['student_name']); ?>', <?php echo $clearance['student_id']; ?>)" 
                                    class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1 rounded-full transition-colors">
                                    Review
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Review Clearance</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="clearanceForm" action="process_clearance.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="clearance_id" id="modalClearanceId">
                <input type="hidden" name="student_id" id="modalStudentId">
                
                <div>
                    <p class="text-sm text-gray-600 mb-4">Student: <span id="modalStudentName" class="font-semibold text-gray-900"></span></p>
                    
                    <label class="block text-sm font-medium text-gray-700 mb-2">Decision</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="approved" class="sr-only peer" checked>
                            <div class="p-3 text-center border-2 border-gray-200 rounded-lg peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                                <i data-lucide="check" class="w-6 h-6 mx-auto mb-1 text-green-600"></i>
                                <span class="text-sm font-medium text-gray-900">Approve</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="on_hold" class="sr-only peer">
                            <div class="p-3 text-center border-2 border-gray-200 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-50 transition-all">
                                <i data-lucide="pause" class="w-6 h-6 mx-auto mb-1 text-yellow-600"></i>
                                <span class="text-sm font-medium text-gray-900">Hold</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="rejected" class="sr-only peer">
                            <div class="p-3 text-center border-2 border-gray-200 rounded-lg peer-checked:border-red-500 peer-checked:bg-red-50 transition-all">
                                <i data-lucide="x" class="w-6 h-6 mx-auto mb-1 text-red-600"></i>
                                <span class="text-sm font-medium text-gray-900">Reject</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks / Reason</label>
                    <textarea name="remarks" rows="3" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                        placeholder="e.g., Unreturned book: Harry Potter, Page 394..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Required if placing on hold or rejecting</p>
                </div>

                <div class="pt-4 flex space-x-3">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        Submit Decision
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openActionModal(clearanceId, studentName, studentId) {
            document.getElementById('modalClearanceId').value = clearanceId;
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('actionModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('actionModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Form validation
        document.getElementById('clearanceForm').addEventListener('submit', function(e) {
            const status = document.querySelector('input[name="status"]:checked').value;
            const remarks = document.querySelector('textarea[name="remarks"]').value;
            
            if ((status === 'on_hold' || status === 'rejected') && !remarks.trim()) {
                e.preventDefault();
                alert('Please provide remarks for hold or rejection actions.');
                return false;
            }
        });
    </script>
</body>
</html>