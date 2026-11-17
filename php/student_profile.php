<?php
$pageTitle = 'My Course Profile';
require_once __DIR__ . '/includes/header.php';
require_role('Student');

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? OR email = ?");
$stmt->execute([$_SESSION['user_id'], $_SESSION['email'] ?? '']);
$student = $stmt->fetch();

if (!$student) {
    echo '<div class="alert alert-warning">Student profile record not found. Please contact administration.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$balance = get_student_balance($student['student_id'], $pdo);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> My Driving Course & Student Profile</h3>
        <p class="text-muted mb-0">Official learner records at Anza Auto Driving School, Mombasa Branch.</p>
    </div>
    <a href="profile.php" class="btn btn-primary shadow-sm"><i class="fa-solid fa-user-pen me-1"></i> Edit My Profile</a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Learner Bio Data</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width: 40%;">Registration Number:</td>
                        <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($student['student_reg_no']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Full Name:</td>
                        <td class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Gender:</td>
                        <td><?= htmlspecialchars($student['gender']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">National ID:</td>
                        <td><?= htmlspecialchars($student['national_id'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Phone Number:</td>
                        <td><?= htmlspecialchars($student['phone']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Email Address:</td>
                        <td><?= htmlspecialchars($student['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Residential Address:</td>
                        <td><?= htmlspecialchars($student['address']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Registration Date:</td>
                        <td><?= format_date($student['registration_date']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Training Status:</td>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($student['status']) ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-car text-warning me-2"></i> Enrolled Driving Program</h5>
            </div>
            <div class="card-body">
                <h4 class="fw-bold text-dark mb-2"><?= htmlspecialchars($student['course_category']) ?></h4>
                <p class="text-muted small">
                    Comprehensive training including road signs, traffic regulations, model town board, dual-pedal practical driving, three-point turns, parallel parking, hill starts, and NTSA pre-test assessment.
                </p>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tuition Fee:</span>
                    <span class="fw-bold"><?= format_currency($balance['course_fee']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Amount Paid:</span>
                    <span class="fw-bold text-success"><?= format_currency($balance['total_paid']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">Fee Balance:</span>
                    <span class="fw-bold <?= ($balance['balance'] > 0) ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency($balance['balance']) ?>
                    </span>
                </div>

                <a href="payments.php" class="btn btn-outline-success w-100"><i class="fa-solid fa-receipt me-1"></i> View My Payment Receipts</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
