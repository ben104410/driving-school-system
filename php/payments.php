<?php
$pageTitle = 'Fee Payments';
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');

$sql = "
    SELECT p.*, s.full_name AS student_name, s.student_reg_no, s.phone AS student_phone, s.course_fee,
           u.full_name AS recorder_name
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    LEFT JOIN users u ON p.recorded_by = u.user_id
    WHERE 1=1
";
$params = [];

// If student, restrict to own payments
if ($_SESSION['role'] === 'Student') {
    $stmtStu = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? OR email = ?");
    $stmtStu->execute([$_SESSION['user_id'], $_SESSION['email'] ?? '']);
    $myStuId = $stmtStu->fetchColumn();
    $sql .= " AND p.student_id = ?";
    $params[] = $myStuId ?: 0;
}

if (!empty($search)) {
    $sql .= " AND (p.receipt_no LIKE ? OR s.full_name LIKE ? OR s.student_reg_no LIKE ? OR p.reference_no LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($methodFilter)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $methodFilter;
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Total collected
$totalCollected = array_sum(array_column($payments, 'amount'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Student Fee Payments</h3>
        <p class="text-muted mb-0">Record of tuition payments, M-Pesa receipts, and fee balances.</p>
    </div>
    <?php if (has_role(['Administrator', 'Receptionist'])): ?>
    <a href="payment_add.php" class="btn btn-success shadow-sm">
        <i class="fa-solid fa-plus me-1"></i> Record New Payment
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="payments.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search receipt no, student name, M-Pesa code..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="method" class="form-select">
                    <option value="">-- All Payment Methods --</option>
                    <option value="M-Pesa" <?= ($methodFilter === 'M-Pesa') ? 'selected' : '' ?>>M-Pesa</option>
                    <option value="Cash" <?= ($methodFilter === 'Cash') ? 'selected' : '' ?>>Cash</option>
                    <option value="Bank Transfer" <?= ($methodFilter === 'Bank Transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="payments.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">Showing <?= count($payments) ?> Transaction(s)</span>
        <span class="badge bg-success fs-6 py-2 px-3">Total: <?= format_currency($totalCollected) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Student Name</th>
                        <th>Payment Date</th>
                        <th>Amount Paid</th>
                        <th>Method</th>
                        <th>Reference Code</th>
                        <th>Recorded By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No fee payments found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><span class="badge bg-light text-primary border font-monospace fs-6"><?= htmlspecialchars($p['receipt_no']) ?></span></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($p['student_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['student_reg_no']) ?></small>
                                </td>
                                <td><?= format_date($p['payment_date']) ?></td>
                                <td class="fw-bold text-success fs-6"><?= format_currency($p['amount']) ?></td>
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
                                <td><small class="text-muted"><?= htmlspecialchars($p['recorder_name'] ?? 'System') ?></small></td>
                                <td class="text-end">
                                    <a href="receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Official Receipt">
                                        <i class="fa-solid fa-receipt me-1"></i> Receipt
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
