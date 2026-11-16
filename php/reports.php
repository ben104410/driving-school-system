<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$reportType = trim($_GET['type'] ?? 'revenue');
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

// -------------------------------------------------------------
// 1. REVENUE REPORT
// -------------------------------------------------------------
if ($reportType === 'revenue') {
    $stmtRev = $pdo->prepare("
        SELECT p.*, s.full_name AS student_name, s.student_reg_no, s.course_category
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        WHERE p.payment_date BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
    ");
    $stmtRev->execute([$startDate, $endDate]);
    $revenueRows = $stmtRev->fetchAll();

    $totalRev = array_sum(array_column($revenueRows, 'amount'));

    // Method breakdown
    $methodBreakdown = [];
    foreach ($revenueRows as $r) {
        $m = $r['payment_method'];
        $methodBreakdown[$m] = ($methodBreakdown[$m] ?? 0) + (float)$r['amount'];
    }
}

// -------------------------------------------------------------
// 2. STUDENT ENROLLMENT REPORT
// -------------------------------------------------------------
if ($reportType === 'students') {
    $stmtStu = $pdo->prepare("
        SELECT s.*, COALESCE(SUM(p.amount), 0) AS total_paid
        FROM students s
        LEFT JOIN payments p ON s.student_id = p.student_id
        WHERE s.registration_date BETWEEN ? AND ?
        GROUP BY s.student_id
        ORDER BY s.registration_date DESC
    ");
    $stmtStu->execute([$startDate, $endDate]);
    $studentRows = $stmtStu->fetchAll();
}

// -------------------------------------------------------------
// 3. LESSON SCHEDULES REPORT
// -------------------------------------------------------------
if ($reportType === 'lessons') {
    $stmtLes = $pdo->prepare("
        SELECT ls.*, s.full_name AS student_name, s.student_reg_no, i.full_name AS instructor_name, v.reg_no AS vehicle_reg
        FROM lesson_schedules ls
        JOIN students s ON ls.student_id = s.student_id
        JOIN instructors i ON ls.instructor_id = i.instructor_id
        LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
        WHERE ls.lesson_date BETWEEN ? AND ?
        ORDER BY ls.lesson_date DESC, ls.lesson_time ASC
    ");
    $stmtLes->execute([$startDate, $endDate]);
    $lessonRows = $stmtLes->fetchAll();
}

// -------------------------------------------------------------
// 4. TEST RESULTS / PASS RATE REPORT
// -------------------------------------------------------------
if ($reportType === 'tests') {
    $stmtTest = $pdo->prepare("
        SELECT tr.*, s.full_name AS student_name, s.student_reg_no, s.course_category
        FROM driving_test_results tr
        JOIN students s ON tr.student_id = s.student_id
        WHERE tr.test_date BETWEEN ? AND ?
        ORDER BY tr.test_date DESC
    ");
    $stmtTest->execute([$startDate, $endDate]);
    $testRows = $stmtTest->fetchAll();

    $totalTests = count($testRows);
    $passCount = count(array_filter($testRows, fn($t) => $t['status'] === 'Pass'));
    $passRate = $totalTests > 0 ? round(($passCount / $totalTests) * 100, 1) : 0;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> Management Reports & Analytics</h3>
        <p class="text-muted mb-0">Generate operational, financial, and academic performance reports.</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-dark shadow-sm">
        <i class="fa-solid fa-print me-1"></i> Print / Export Report
    </button>
</div>

<!-- Report Selection & Date Range (Hidden on Print) -->
<div class="card border-0 shadow-sm rounded-3 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="reports.php" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Select Report Type</label>
                <select name="type" class="form-select fw-semibold" onchange="this.form.submit()">
                    <option value="revenue" <?= ($reportType === 'revenue') ? 'selected' : '' ?>>Fee Revenue & Collections Report</option>
                    <option value="students" <?= ($reportType === 'students') ? 'selected' : '' ?>>Student Enrollment & Progress Report</option>
                    <option value="lessons" <?= ($reportType === 'lessons') ? 'selected' : '' ?>>Practical Driving Lessons Report</option>
                    <option value="tests" <?= ($reportType === 'tests') ? 'selected' : '' ?>>Driving Test Results & Pass-Rate Report</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-arrows-rotate me-1"></i> Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Official Report Header (Visible on print & web) -->
<div class="card border-0 shadow-sm rounded-3 p-4">
    <div class="text-center pb-3 border-bottom mb-4">
        <h4 class="fw-bold text-dark mb-1">AUTOMOBILE ASSOCIATION OF KENYA (AA DRIVING SCHOOL)</h4>
        <h5 class="fw-semibold text-primary mb-1">MOMBASA BRANCH &bull; OFFICIAL OPERATIONAL REPORT</h5>
        <p class="text-muted small mb-0">
            <strong>Period:</strong> <?= format_date($startDate) ?> to <?= format_date($endDate) ?> &bull; 
            <strong>Generated on:</strong> <?= date('d M Y, h:i A') ?> by <?= htmlspecialchars($_SESSION['full_name']) ?>
        </p>
    </div>

    <?php if ($reportType === 'revenue'): ?>
        <!-- REVENUE REPORT VIEW -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">Total Collections</span>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_currency($totalRev) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">Total Transactions</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= count($revenueRows) ?> receipts</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">M-Pesa Collections</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= format_currency($methodBreakdown['M-Pesa'] ?? 0) ?></h3>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Payment Date</th>
                        <th>Student Name & Reg No</th>
                        <th>Course</th>
                        <th>Method</th>
                        <th>Reference Code</th>
                        <th class="text-end">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($revenueRows)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No revenue collected in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($revenueRows as $r): ?>
                            <tr>
                                <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($r['receipt_no']) ?></td>
                                <td><?= format_date($r['payment_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['student_name']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($r['student_reg_no']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($r['course_category']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['payment_method']) ?></span></td>
                                <td class="font-monospace small"><?= htmlspecialchars($r['reference_no'] ?? '-') ?></td>
                                <td class="text-end fw-bold text-success"><?= number_format($r['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="6" class="text-end fs-6">Grand Total Revenue:</td>
                            <td class="text-end fs-6 text-success"><?= format_currency($totalRev) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'students'): ?>
        <!-- STUDENT ENROLLMENT REPORT -->
        <h5 class="fw-bold mb-3">Enrolled Students in Period (Total: <?= count($studentRows) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Reg No</th>
                        <th>Student Full Name</th>
                        <th>Phone</th>
                        <th>Course Category</th>
                        <th>Registration Date</th>
                        <th>Fee Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentRows)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No students enrolled during this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($studentRows as $s): ?>
                            <tr>
                                <td class="font-monospace fw-bold"><?= htmlspecialchars($s['student_reg_no']) ?></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td><?= htmlspecialchars($s['course_category']) ?></td>
                                <td><?= format_date($s['registration_date']) ?></td>
                                <td class="fw-bold text-success"><?= format_currency($s['total_paid']) ?></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($s['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'lessons'): ?>
        <!-- LESSONS REPORT -->
        <h5 class="fw-bold mb-3">Driving Lesson Activity (Total: <?= count($lessonRows) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Student</th>
                        <th>Instructor</th>
                        <th>Vehicle</th>
                        <th>Practical Skill / Topic</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lessonRows)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No driving lessons recorded for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lessonRows as $l): ?>
                            <tr>
                                <td><?= format_date($l['lesson_date']) ?> at <?= date('h:i A', strtotime($l['lesson_time'])) ?></td>
                                <td><?= htmlspecialchars($l['student_name']) ?></td>
                                <td><?= htmlspecialchars($l['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($l['vehicle_reg'] ?? 'TBD') ?></td>
                                <td><?= htmlspecialchars($l['lesson_topic']) ?></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($l['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'tests'): ?>
        <!-- TEST ASSESSMENTS REPORT -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">Tests Conducted</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= $totalTests ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">Successful Passes</span>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= $passCount ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small text-uppercase">Pass Rate</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= $passRate ?>%</h3>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Test Date</th>
                        <th>Student</th>
                        <th>Test Type</th>
                        <th>Score</th>
                        <th>Outcome</th>
                        <th>Examiner</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($testRows)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No test assessments in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($testRows as $t): ?>
                            <tr>
                                <td><?= format_date($t['test_date']) ?></td>
                                <td><?= htmlspecialchars($t['student_name']) ?></td>
                                <td><?= htmlspecialchars($t['test_type']) ?></td>
                                <td class="fw-bold"><?= $t['score'] ?> / 100</td>
                                <td><span class="badge bg-<?= ($t['status'] === 'Pass') ? 'success' : 'danger' ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                                <td><?= htmlspecialchars($t['examiner_name']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($t['remarks'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-4 pt-3 border-top d-flex justify-content-between text-muted small">
        <span>AA Driving School &bull; Mombasa Branch Management System</span>
        <span>End of Report</span>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
