<?php
$pageTitle = 'Record Fee Payment';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$preStudentId = (int)($_GET['student_id'] ?? 0);
$receiptNo = generate_receipt_no($pdo);
$error = '';

$students = $pdo->query("SELECT student_id, student_reg_no, full_name, course_category, course_fee FROM students ORDER BY full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'M-Pesa');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Tuition fee installment');

    if (!$student_id || $amount <= 0) {
        $error = 'Please select a student and enter a valid payment amount greater than zero.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (receipt_no, student_id, amount, payment_date, payment_method, reference_no, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$receiptNo, $student_id, $amount, $payment_date, $payment_method, $reference_no, $notes, $_SESSION['user_id']]);
            $paymentId = $pdo->lastInsertId();

            set_flash('success', "Payment of " . format_currency($amount) . " recorded successfully! Receipt: $receiptNo");
            redirect("receipt.php?id=$paymentId");
        } catch (Exception $e) {
            $error = 'Error recording payment: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="payments.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Payments</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Record Student Fee Payment</h3>
    <p class="text-muted mb-0">Record cash, M-Pesa, or bank deposit and generate an official receipt.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="payment_add.php">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Receipt Number</label>
                    <input type="text" class="form-control bg-light font-monospace fw-bold text-primary" name="receipt_no" value="<?= htmlspecialchars($receiptNo) ?>" readonly>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                    <select name="student_id" id="student_id" class="form-select" onchange="showStudentBalance()" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['student_id'] ?>" <?= ($preStudentId === (int)$stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?> &bull; <?= htmlspecialchars($stu['course_category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Amount (KES) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">KES</span>
                        <input type="number" step="0.01" class="form-control fw-bold fs-5 text-success" name="amount" placeholder="e.g. 5000" required autofocus>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer / Deposit Slip</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reference Code / Transaction No.</label>
                    <input type="text" class="form-control" name="reference_no" placeholder="e.g. QA819KL92Z or Bank Slip No.">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Payment Description / Notes</label>
                    <input type="text" class="form-control" name="notes" placeholder="e.g. Tuition fee installment 2" value="Tuition fee installment">
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="payments.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-success fw-semibold"><i class="fa-solid fa-receipt me-1"></i> Save Payment & Generate Receipt</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
