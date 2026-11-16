<?php
$pageTitle = 'Student Profile & Dossier';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist', 'Instructor']);

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    set_flash('danger', 'Invalid student ID.');
    redirect('students.php');
}

$stmt = $pdo->prepare("SELECT s.*, u.username FROM students s LEFT JOIN users u ON s.user_id = u.user_id WHERE s.student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Student not found.');
    redirect('students.php');
}

$balance = get_student_balance($studentId, $pdo);

// Payments history
$stmtPay = $pdo->prepare("SELECT p.*, u.full_name AS recorder_name FROM payments p LEFT JOIN users u ON p.recorded_by = u.user_id WHERE p.student_id = ? ORDER BY p.payment_date DESC, p.payment_id DESC");
$stmtPay->execute([$studentId]);
$payments = $stmtPay->fetchAll();

// Lesson schedules
$stmtSched = $pdo->prepare("
    SELECT ls.*, i.full_name AS instructor_name, i.phone AS instructor_phone, v.model AS vehicle_model, v.reg_no AS vehicle_reg
    FROM lesson_schedules ls
    JOIN instructors i ON ls.instructor_id = i.instructor_id
    LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
    WHERE ls.student_id = ?
    ORDER BY ls.lesson_date DESC, ls.lesson_time DESC
");
$stmtSched->execute([$studentId]);
$schedules = $stmtSched->fetchAll();

// Test results
$stmtTest = $pdo->prepare("SELECT * FROM driving_test_results WHERE student_id = ? ORDER BY test_date DESC");
$stmtTest->execute([$studentId]);
$testResults = $stmtTest->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="students.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Student Directory</a>
        <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($student['full_name']) ?></h3>
        <span class="badge bg-light text-primary border font-monospace me-2"><?= htmlspecialchars($student['student_reg_no']) ?></span>
        <span class="badge bg-primary"><?= htmlspecialchars($student['status']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="student_edit.php?id=<?= $studentId ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Student</a>
        <a href="payment_add.php?student_id=<?= $studentId ?>" class="btn btn-success"><i class="fa-solid fa-money-bill me-1"></i> Record Payment</a>
        <a href="schedule_add.php?student_id=<?= $studentId ?>" class="btn btn-warning text-dark"><i class="fa-solid fa-calendar-plus me-1"></i> Book Lesson</a>
        <a href="test_result_add.php?student_id=<?= $studentId ?>" class="btn btn-info text-dark"><i class="fa-solid fa-award me-1"></i> Record Test</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Student Profile Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-user text-primary me-2"></i> Personal Bio</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Gender:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($student['gender']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Date of Birth:</span>
                        <span class="fw-semibold"><?= format_date($student['date_of_birth']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">National ID:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($student['national_id'] ?? 'N/A') ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Phone Number:</span>
                        <span class="fw-semibold text-primary"><?= htmlspecialchars($student['phone']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Email:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($student['email'] ?? 'N/A') ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Address:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($student['address']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Registration Date:</span>
                        <span class="fw-semibold"><?= format_date($student['registration_date']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Portal Login:</span>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($student['username'] ?? 'No Login') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Fee Balance Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-wallet text-success me-2"></i> Fee Account Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Agreed Course Fee:</span>
                    <span class="fw-bold"><?= format_currency($balance['course_fee']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Paid:</span>
                    <span class="fw-bold text-success"><?= format_currency($balance['total_paid']) ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">Outstanding Balance:</span>
                    <span class="fw-bold <?= ($balance['balance'] > 0) ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency($balance['balance']) ?>
                    </span>
                </div>
                <?php
                $pct = ($balance['course_fee'] > 0) ? min(100, round(($balance['total_paid'] / $balance['course_fee']) * 100)) : 0;
                ?>
                <div class="progress mb-2" style="height: 12px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%"><?= $pct ?>%</div>
                </div>
                <div class="text-center small text-muted">
                    <?= $balance['is_cleared'] ? '<span class="text-success fw-bold"><i class="fa-solid fa-check-circle me-1"></i> Tuition Cleared</span>' : 'Pending Installment Balance' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side Tabs: Lessons, Payments, Test Results -->
    <div class="col-lg-8">
        <!-- Driving Lessons Table -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-car text-primary me-2"></i> Driving Lessons Timetable (<?= count($schedules) ?>)</h5>
                <a href="schedule_add.php?student_id=<?= $studentId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-plus me-1"></i> Book Lesson</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Instructor</th>
                                <th>Vehicle</th>
                                <th>Lesson Topic</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No driving lessons scheduled yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= format_date($s['lesson_date']) ?></div>
                                            <small class="text-muted"><?= date('h:i A', strtotime($s['lesson_time'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['vehicle_reg'] ?? 'TBD') ?></span></td>
                                        <td><?= htmlspecialchars($s['lesson_topic']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($s['status']) ?></span></td>
                                        <td class="small text-muted"><?= htmlspecialchars($s['instructor_notes'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fee Payment Records -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Payment Receipts (<?= count($payments) ?>)</h5>
                <a href="payment_add.php?student_id=<?= $studentId ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-plus me-1"></i> Add Payment</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No payments recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($p['receipt_no']) ?></td>
                                        <td><?= format_date($p['payment_date']) ?></td>
                                        <td class="fw-bold text-success"><?= format_currency($p['amount']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                                        <td class="font-monospace small"><?= htmlspecialchars($p['reference_no'] ?? '-') ?></td>
                                        <td>
                                            <a href="receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fa-solid fa-print me-1"></i> Print Receipt
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

        <!-- Driving Test Results -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-square-poll-vertical text-warning me-2"></i> Driving Test Assessments (<?= count($testResults) ?>)</h5>
                <a href="test_result_add.php?student_id=<?= $studentId ?>" class="btn btn-sm btn-outline-warning text-dark"><i class="fa-solid fa-plus me-1"></i> Record Test</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Test Type</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Examiner</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($testResults)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No test results recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($testResults as $t): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($t['test_type']) ?></td>
                                        <td><?= format_date($t['test_date']) ?></td>
                                        <td class="fw-bold fs-6"><?= $t['score'] ?> / 100</td>
                                        <td><span class="badge bg-<?= ($t['status'] === 'Pass') ? 'success' : 'danger' ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                                        <td><?= htmlspecialchars($t['examiner_name']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($t['remarks'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
