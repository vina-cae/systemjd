<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id'])) {
    die("Clearance ID required");
}

$clearanceId = (int)$_GET['id'];

// Verify this clearance belongs to the student and is completed
$sql = "
    SELECT c.*, u.full_name, u.user_id as student_number, u.email,
           DATE_FORMAT(c.completed_at, '%M %d, %Y') as completed_date
    FROM clearances c
    JOIN users u ON c.student_id = u.id
    WHERE c.id = ? AND c.student_id = ? AND c.status = 'completed'
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $clearanceId, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$clearance = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$clearance) {
    die("Invalid clearance or not yet completed");
}

// Get all approved departments
$deptSql = "
    SELECT d.name, ci.approved_at, u.full_name as approved_by
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.id
    LEFT JOIN users u ON ci.approved_by = u.id
    WHERE ci.clearance_id = ? AND ci.status = 'approved'
    ORDER BY d.sequence_order
";
$deptStmt = mysqli_prepare($conn, $deptSql);
mysqli_stmt_bind_param($deptStmt, 'i', $clearanceId);
mysqli_stmt_execute($deptStmt);
$deptResult = mysqli_stmt_get_result($deptStmt);

$departments = [];
if ($deptResult) {
    while ($row = mysqli_fetch_assoc($deptResult)) {
        $departments[] = $row;
    }
}
mysqli_stmt_close($deptStmt);

// Generate unique permit code
$permitCode = 'CP-' . strtoupper(substr(md5($clearanceId . $clearance['student_id']), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permit to Enroll - <?php echo htmlspecialchars($clearance['full_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .permit-container {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 4rem;
            color: rgba(0,0,0,0.03);
            font-weight: bold;
            pointer-events: none;
            z-index: 0;
        }
        .border-pattern {
            background-image: repeating-linear-gradient(
                45deg,
                #4f46e5,
                #4f46e5 10px,
                #6366f1 10px,
                #6366f1 20px
            );
        }
        @media print {
            .no-print { display: none; }
            .permit-container { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <!-- Actions -->
    <div class="max-w-4xl mx-auto mb-4 flex justify-between no-print">
        <button onclick="history.back()" class="flex items-center text-gray-600 hover:text-gray-900">
            <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
            Back to Dashboard
        </button>
        <button onclick="downloadPDF()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 flex items-center shadow-lg">
            <i data-lucide="download" class="w-5 h-5 mr-2"></i>
            Download PDF
        </button>
    </div>

    <!-- Permit Content -->
    <div id="permit" class="permit-container shadow-2xl rounded-lg overflow-hidden">
        <!-- Header Border -->
        <div class="h-4 border-pattern"></div>
        
        <div class="p-8 relative">
            <div class="watermark">CLEAR-PASS</div>
            
            <!-- Header -->
            <div class="flex justify-between items-start mb-8 relative z-10">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-indigo-600 rounded-lg flex items-center justify-center text-white">
                        <i data-lucide="shield-check" class="w-8 h-8"></i>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-2xl font-bold text-gray-900">Clear-Pass System</h1>
                        <p class="text-sm text-gray-500">Official Student Clearance Certificate</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-indigo-600"><?php echo $permitCode; ?></div>
                    <p class="text-sm text-gray-500">Permit ID</p>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="flex justify-center mb-8 relative z-10">
                <div class="bg-white p-4 rounded-xl shadow-lg border-2 border-indigo-100">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('CLEARPASS-' . $permitCode . '-' . $clearance['student_number']); ?>" 
                         alt="Verification QR" class="w-32 h-32">
                    <p class="text-xs text-center text-gray-500 mt-2">Scan to Verify</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="bg-gray-50 rounded-xl p-6 mb-6 relative z-10 border border-gray-200">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 uppercase tracking-wide">Permit to Enroll</h2>
                    <p class="text-gray-600 mt-2">This certifies that the student below has satisfied all clearance requirements</p>
                </div>

                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider">Student Name</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($clearance['full_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider">Student ID</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($clearance['student_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider">Email</p>
                        <p class="text-md font-semibold text-gray-900"><?php echo htmlspecialchars($clearance['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider">Completion Date</p>
                        <p class="text-md font-semibold text-gray-900"><?php echo $clearance['completed_date']; ?></p>
                    </div>
                </div>

                <!-- Clearance Details -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3">Cleared Departments</h3>
                    <div class="space-y-2">
                        <?php foreach ($departments as $dept): ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                            <span class="text-sm text-gray-700 flex items-center">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-500 mr-2"></i>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </span>
                            <span class="text-xs text-gray-500">
                                <?php echo date('M d, Y', strtotime($dept['approved_at'])); ?> 
                                by <?php echo htmlspecialchars($dept['approved_by'] ?? 'System'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-between items-end relative z-10 text-sm text-gray-500 border-t border-gray-200 pt-4">
                <div>
                    <p>This permit is electronically generated and valid without signature.</p>
                    <p class="mt-1">Verify authenticity at: <span class="text-indigo-600 font-medium">clearpass.edu/verify</span></p>
                </div>
                <div class="text-right">
                    <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
                    <p>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Footer Border -->
        <div class="h-4 border-pattern"></div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
        
        function downloadPDF() {
            const element = document.getElementById('permit');
            const opt = {
                margin: 0.5,
                filename: 'Permit-to-Enroll-<?php echo $clearance['student_number']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            // Show loading
            const btn = document.querySelector('button[onclick="downloadPDF()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Generating...';
            
            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalText;
                lucide.createIcons();
            });
        }
    </script>
</body>
</html>