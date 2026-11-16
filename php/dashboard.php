<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$role = $_SESSION['role'];

// -------------------------------------------------------------
// ADMIN & RECEPTIONIST DATA
// -------------------------------------------------------------
if ($role === 'Administrator' || $role === 'Receptionist') {
    // KPI metrics
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $activeInstructors = $pdo->query("SELECT COUNT(*) FROM instructors WHERE status = 'Active'")->fetchColumn();
    $fleetCount = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
    
    // Lessons today
    $today = date('Y-m-d');
    $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM lesson_schedules WHERE lesson_date = ?");
    $stmtToday->execute([$today]);
    $lessonsTodayCount = $stmtToday->fetchColumn();

    // Today's lessons list
    $stmtSchedule = $pdo->prepare("
        SELECT ls.*, s.full_name AS student_name, s.student_reg_no, i.full_name AS instructor_name, v.reg_no AS vehicle_reg, v.model AS vehicle_model
        FROM lesson_schedules ls
        JOIN students s ON ls.student_id = s.student_id
        JOIN instructors i ON ls.instructor_id = i.instructor_id
        LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
        WHERE ls.lesson_date = ?
        ORDER BY ls.lesson_time ASC
    ");
    $stmtSchedule->execute([$today]);
    $todayLessons = $stmtSchedule->fetchAll();

    // Recent payments
    $recentPayments = $pdo->query("
        SELECT p.*, s.full_name AS student_name, s.student_reg_no
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        ORDER BY p.payment_date DESC, p.payment_id DESC
        LIMIT 5
    ")->fetchAll();

    // Course Category distribution for chart
    $courseDist = $pdo->query("
        SELECT course_category, COUNT(*) as count 
        FROM students 
        GROUP BY course_category
    ")->fetchAll();
    $chartLabels = array_column($courseDist, 'course_category');
    $chartData = array_column($courseDist, 'count');
}

// -------------------------------------------------------------
// INSTRUCTOR DATA
// -------------------------------------------------------------
if ($role === 'Instructor') {
    $stmtInst = $pdo->prepare("SELECT instructor_id FROM instructors WHERE user_id = ?");
    $stmtInst->execute([$_SESSION['user_id']]);
    $instructorId = $stmtInst->fetchColumn();

    $myStudentsCount = 0;
    $myLessonsToday = [];
    $myUpcomingLessons = [];

    if ($instructorId) {
        $stmtMyStudents = $pdo->prepare("
            SELECT COUNT(DISTINCT student_id) FROM lesson_schedules WHERE instructor_id = ?
        ");
        $stmtMyStudents->execute([$instructorId]);
        $myStudentsCount = $stmtMyStudents->fetchColumn();

        $stmtToday = $pdo->prepare("
            SELECT ls.*, s.full_name AS student_name, s.phone AS student_phone, v.reg_no AS vehicle_reg
            FROM lesson_schedules ls
            JOIN students s ON ls.student_id = s.student_id
            LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
            WHERE ls.instructor_id = ? AND ls.lesson_date = ?
            ORDER BY ls.lesson_time ASC
        ");
        $stmtToday->execute([$instructorId, date('Y-m-d')]);
        $myLessonsToday = $stmtToday->fetchAll();
    }
}

// -------------------------------------------------------------
// STUDENT DATA
// -------------------------------------------------------------
if ($role === 'Student') {
    $stmtStudent = $pdo->prepare("SELECT * FROM students WHERE user_id = ? OR email = ?");
    $stmtStudent->execute([$_SESSION['user_id'], $_SESSION['email'] ?? '']);
    $studentInfo = $stmtStudent->fetch();

    $studentId = $studentInfo['student_id'] ?? null;
    $balanceInfo = $studentId ? get_student_balance($studentId, $pdo) : null;

    $mySchedules = [];
    $myTestResults = [];

    if ($studentId) {
        $stmtSched = $pdo->prepare("
            SELECT ls.*, i.full_name AS instructor_name, i.phone AS instructor_phone, v.model AS vehicle_model, v.reg_no AS vehicle_reg
            FROM lesson_schedules ls
            JOIN instructors i ON ls.instructor_id = i.instructor_id
            LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
            WHERE ls.student_id = ?
            ORDER BY ls.lesson_date DESC, ls.lesson_time DESC
        ");
        $stmtSched->execute([$studentId]);
        $mySchedules = $stmtSched->fetchAll();

        $stmtTest = $pdo->prepare("
            SELECT * FROM driving_test_results WHERE student_id = ? ORDER BY test_date DESC
        ");
        $stmtTest->execute([$studentId]);
        $myTestResults = $stmtTest->fetchAll();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h3>
        <p class="text-muted mb-0">Overview of AA Driving School Mombasa operations & records.</p>
    </div>
    <?php if ($role === 'Administrator' || $role === 'Receptionist'): ?>
    <div class="d-flex gap-2">
        <a href="student_add.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Register Student</a>
        <a href="payment_add.php" class="btn btn-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Record Payment</a>
        <a href="schedule_add.php" class="btn btn-warning text-dark"><i class="fa-solid fa-calendar-plus me-1"></i> Book Lesson</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($role === 'Administrator' || $role === 'Receptionist'): ?>
<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Students</span>
                    <h2 class="fw-bold mb-0 mt-1"><?= number_format($totalStudents) ?></h2>
                    <small class="text-success"><i class="fa-solid fa-arrow-trend-up me-1"></i> Active Enrollments</small>
                </div>
                <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Licensed Instructors</span>
                    <h2 class="fw-bold mb-0 mt-1"><?= number_format($activeInstructors) ?></h2>
                    <small class="text-primary"><i class="fa-solid fa-id-card me-1"></i> NTSA Certified</small>
                </div>
                <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Today's Lessons</span>
                    <h2 class="fw-bold mb-0 mt-1"><?= number_format($lessonsTodayCount) ?></h2>
                    <small class="text-info"><i class="fa-solid fa-car-side me-1"></i> <?= date('d M Y') ?></small>
                </div>
                <div class="kpi-icon-box bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Fees Paid</span>
                    <h2 class="fw-bold mb-0 mt-1 text-success"><?= format_currency($totalRevenue) ?></h2>
                    <small class="text-muted"><i class="fa-solid fa-receipt me-1"></i> Revenue Collected</small>
                </div>
                <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Driving Lessons -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-calendar-day text-primary me-2"></i> Today's Driving Lessons</h5>
                <a href="schedules.php" class="btn btn-sm btn-outline-primary">View All Schedules</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Instructor</th>
                                <th>Vehicle</th>
                                <th>Lesson Topic</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todayLessons)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fa-regular fa-calendar-xmark fs-3 d-block mb-2"></i>
                                        No driving lessons scheduled for today (<?= date('d M Y') ?>).
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($todayLessons as $l): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= date('h:i A', strtotime($l['lesson_time'])) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($l['student_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($l['student_reg_no']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($l['instructor_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['vehicle_reg'] ?? 'N/A') ?></span></td>
                                        <td><?= htmlspecialchars($l['lesson_topic']) ?></td>
                                        <td>
                                            <?php
                                            $stClass = [
                                                'Pending' => 'warning text-dark',
                                                'Scheduled' => 'primary',
                                                'In Progress' => 'info text-dark',
                                                'Completed' => 'success',
                                                'Cancelled' => 'danger',
                                                'No Show' => 'secondary'
                                            ][$l['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $stClass ?>"><?= htmlspecialchars($l['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Distribution Chart -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-chart-pie text-warning me-2"></i> Enrollments by Course</h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div style="width: 100%; max-height: 250px;">
                    <canvas id="courseChart"></canvas>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('courseChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: <?= json_encode($chartLabels) ?>,
                                    datasets: [{
                                        data: <?= json_encode($chartData) ?>,
                                        backgroundColor: ['#0d3b66', '#f4a261', '#2a9d8f', '#e76f51', '#457b9d']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
                                }
                            });
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-money-check-dollar text-success me-2"></i> Recent Payment Transactions</h5>
        <a href="payments.php" class="btn btn-sm btn-outline-success">View All Payments</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Student Name</th>
                        <th>Payment Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference / Code</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No payments recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td><span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($p['receipt_no']) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($p['student_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['student_reg_no']) ?></small>
                                </td>
                                <td><?= format_date($p['payment_date']) ?></td>
                                <td class="fw-bold text-success"><?= format_currency($p['amount']) ?></td>
                                <td>
                                    <?php
                                    $mBadge = [
                                        'M-Pesa' => 'success',
                                        'Cash' => 'primary',
                                        'Bank Transfer' => 'info text-dark'
                                    ][$p['payment_method']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $mBadge ?>"><?= htmlspecialchars($p['payment_method']) ?></span>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($p['reference_no'] ?? '-') ?></td>
                                <td>
                                    <a href="receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-print me-1"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($role === 'Instructor'): ?>
<!-- INSTRUCTOR DASHBOARD VIEW -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-4">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Assigned Students</span>
                    <h2 class="fw-bold mb-0 mt-1"><?= number_format($myStudentsCount) ?></h2>
                </div>
                <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card kpi-card bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Today's Lessons</span>
                    <h2 class="fw-bold mb-0 mt-1"><?= count($myLessonsToday) ?></h2>
                </div>
                <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-car text-primary me-2"></i> My Driving Lessons for Today (<?= date('d M, Y') ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Student</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Lesson Topic</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myLessonsToday)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No lessons assigned to you for today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($myLessonsToday as $l): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= date('h:i A', strtotime($l['lesson_time'])) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($l['student_name']) ?></td>
                                <td><?= htmlspecialchars($l['student_phone']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['vehicle_reg'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($l['lesson_topic']) ?></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($l['status']) ?></span></td>
                                <td><a href="schedule_status.php?id=<?= $l['schedule_id'] ?>" class="btn btn-sm btn-outline-primary">Update Status</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($role === 'Student'): ?>
<!-- STUDENT PORTAL DASHBOARD -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3">
            <span class="text-muted small fw-semibold text-uppercase">Registration No.</span>
            <h4 class="fw-bold text-primary mt-1"><?= htmlspecialchars($studentInfo['student_reg_no'] ?? 'N/A') ?></h4>
            <div class="small text-muted"><i class="fa-solid fa-book-open me-1"></i> <?= htmlspecialchars($studentInfo['course_category'] ?? 'N/A') ?></div>
            <div class="mt-2"><span class="badge bg-success"><?= htmlspecialchars($studentInfo['status'] ?? 'Active') ?></span></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3">
            <span class="text-muted small fw-semibold text-uppercase">Tuition Fee</span>
            <h4 class="fw-bold text-dark mt-1"><?= format_currency($balanceInfo['course_fee'] ?? 0) ?></h4>
            <div class="small text-success"><i class="fa-solid fa-circle-check me-1"></i> Paid: <?= format_currency($balanceInfo['total_paid'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3">
            <span class="text-muted small fw-semibold text-uppercase">Outstanding Balance</span>
            <h4 class="fw-bold <?= ($balanceInfo && $balanceInfo['balance'] > 0) ? 'text-danger' : 'text-success' ?> mt-1">
                <?= format_currency($balanceInfo['balance'] ?? 0) ?>
            </h4>
            <div class="small text-muted">
                <?= ($balanceInfo && $balanceInfo['is_cleared']) ? '<span class="badge bg-success">Fees Fully Cleared</span>' : '<span class="badge bg-warning text-dark">Payment Pending</span>' ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-calendar-days text-primary me-2"></i> My Driving Lesson Timetable</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Instructor</th>
                                <th>Vehicle</th>
                                <th>Lesson Topic</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mySchedules)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No scheduled driving lessons yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mySchedules as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= format_date($s['lesson_date']) ?></div>
                                            <small class="text-muted"><?= date('h:i A', strtotime($s['lesson_time'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['vehicle_reg'] ?? 'TBD') ?></span></td>
                                        <td><?= htmlspecialchars($s['lesson_topic']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-award text-warning me-2"></i> Driving Test Assessment</h5>
            </div>
            <div class="card-body">
                <?php if (empty($myTestResults)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-clipboard-question fs-2 d-block mb-2"></i>
                        No driving test results recorded yet. Complete your practical lessons to sit for the internal mock test!
                    </div>
                <?php else: ?>
                    <?php foreach ($myTestResults as $t): ?>
                        <div class="p-3 mb-2 rounded border bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($t['test_type']) ?></h6>
                                <span class="badge bg-<?= ($t['status'] === 'Pass') ? 'success' : 'danger' ?>"><?= htmlspecialchars($t['status']) ?></span>
                            </div>
                            <div class="small text-muted mb-2"><i class="fa-regular fa-calendar me-1"></i> <?= format_date($t['test_date']) ?> &bull; Examiner: <?= htmlspecialchars($t['examiner_name']) ?></div>
                            <div class="progress mb-2" style="height: 10px;">
                                <div class="progress-bar bg-<?= ($t['score'] >= 70) ? 'success' : 'warning' ?>" role="progressbar" style="width: <?= min(100, $t['score']) ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span>Score: <strong><?= $t['score'] ?> / 100</strong></span>
                                <span class="text-muted"><?= htmlspecialchars($t['remarks']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
