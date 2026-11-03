<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// Get student clearance data
$clearance = null;
$sql = "
    SELECT c.*, 
           COUNT(CASE WHEN ci.status = 'approved' THEN 1 END) as approved_count,
           COUNT(ci.id) as total_departments
    FROM clearances c
    LEFT JOIN clearance_items ci ON c.id = ci.clearance_id
    WHERE c.student_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$clearance = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Default values
$clearanceItems = [];
$progress = 0;
$daysActive = 0;

if ($clearance) {
    // Prevent division by zero
    if ($clearance['total_departments'] > 0) {
        $progress = ($clearance['approved_count'] / $clearance['total_departments']) * 100;
    }

    // Calculate days active
    if (!empty($clearance['created_at'])) {
        $daysActive = floor((time() - strtotime($clearance['created_at'])) / 86400);
    }

    // Get detailed clearance items
    $itemsSql = "
        SELECT ci.*, 
               d.name as dept_name, 
               d.icon, 
               d.color, 
               d.sequence_order,
               u.full_name as approved_by_name
        FROM clearance_items ci
        JOIN departments d ON ci.department_id = d.id
        LEFT JOIN users u ON ci.approved_by = u.id
        WHERE ci.clearance_id = ?
        ORDER BY d.sequence_order ASC
    ";
    $itemsStmt = mysqli_prepare($conn, $itemsSql);
    mysqli_stmt_bind_param($itemsStmt, 'i', $clearance['id']);
    mysqli_stmt_execute($itemsStmt);
    $itemsResult = mysqli_stmt_get_result($itemsStmt);
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $clearanceItems[] = $row;
    }
    mysqli_stmt_close($itemsStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Clear-Pass</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .progress-bar { transition: width 0.5s ease-in-out; }
        .step-connector { transition: all 0.3s ease; }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 0.5; }
            100% { transform: scale(1.2); opacity: 0; }
        }
        .status-pulse::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            animation: pulse-ring 2s infinite;
            z-index: -1;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- NAV -->
<nav class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
        <span class="font-bold text-xl">Clear-Pass</span>
        <div>
            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <a href="../auth/logout.php" class="ml-3 text-red-500">Logout</a>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto p-6">

<!-- HEADER -->
<h1 class="text-2xl font-bold mb-6">
    Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?> 👋
</h1>

<!-- PROGRESS -->
<div class="bg-white p-6 rounded-xl shadow mb-6">
    <div class="flex justify-between mb-2">
        <span>Progress</span>
        <span><?php echo round($progress); ?>%</span>
    </div>

    <div class="w-full bg-gray-200 h-3 rounded">
        <div class="bg-indigo-600 h-3 rounded progress-bar"
             style="width: <?php echo $progress; ?>%"></div>
    </div>

    <p class="text-sm text-gray-500 mt-2">
        <?php
        if ($clearance) {
            echo $clearance['approved_count'] . " of " . $clearance['total_departments'] . " completed";
        } else {
            echo "No clearance yet";
        }
        ?>
    </p>

    <?php if ($clearance && $clearance['status'] === 'completed'): ?>
        <a href="download_permit.php?id=<?php echo $clearance['id']; ?>"
           class="inline-block mt-3 bg-green-600 text-white px-4 py-2 rounded">
           Download Permit
        </a>
    <?php endif; ?>
</div>

<!-- TIMELINE -->
<div class="bg-white p-6 rounded-xl shadow">

<h2 class="font-semibold mb-4">Clearance Steps</h2>

<?php if (empty($clearanceItems)): ?>

    <p>No clearance found.</p>
    <a href="initiate_clearance.php" class="text-indigo-600">Start Clearance</a>

<?php else: ?>

    <?php foreach ($clearanceItems as $item): 

        $status = $item['status'];

        $isLocked   = $status === 'locked';
        $isPending  = $status === 'pending';
        $isApproved = $status === 'approved';
        $isRejected = $status === 'rejected';
        $isHold     = $status === 'on_hold';

    ?>

    <div class="mb-4 border-l-4 pl-4
        <?php echo $isApproved ? 'border-green-500' : ($isRejected ? 'border-red-500' : 'border-gray-300'); ?>">

        <h3 class="font-semibold">
            <?php echo htmlspecialchars($item['dept_name']); ?>
        </h3>

        <p class="text-sm text-gray-600">
            <?php
            if ($isLocked) echo "Locked";
            elseif ($isPending) echo "Pending...";
            elseif ($isApproved) echo "Approved";
            elseif ($isRejected) echo "Rejected";
            elseif ($isHold) echo "On Hold";
            ?>
        </p>

        <?php if (!empty($item['remarks']) || !empty($item['hold_reason'])): ?>
            <p class="text-sm mt-1 text-gray-700">
                <?php echo htmlspecialchars($item['remarks'] ?: $item['hold_reason']); ?>
            </p>
        <?php endif; ?>

        <p class="text-xs text-gray-400">
            Step <?php echo $item['sequence_order']; ?>
        </p>

    </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

<!-- STATS -->
<div class="grid grid-cols-3 gap-4 mt-6">

<div class="bg-white p-4 rounded shadow">
    <p class="text-sm text-gray-500">Days Active</p>
    <p class="text-lg font-bold"><?php echo $daysActive; ?></p>
</div>

<div class="bg-white p-4 rounded shadow">
    <p class="text-sm text-gray-500">Status</p>
    <p class="text-lg font-bold">
        <?php echo $clearance ? ucfirst($clearance['status']) : 'N/A'; ?>
    </p>
</div>

<div class="bg-white p-4 rounded shadow">
    <p class="text-sm text-gray-500">Departments</p>
    <p class="text-lg font-bold">
        <?php echo $clearance ? $clearance['total_departments'] : 0; ?>
    </p>
</div>

</div>

</main>

<script>
lucide.createIcons();
</script>

</body>
</html>