<?php
$pageTitle = 'Student Management';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist', 'Instructor']);

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$sql = "SELECT s.*, 
        COALESCE(SUM(p.amount), 0) AS total_paid
        FROM students s
        LEFT JOIN payments p ON s.student_id = p.student_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.full_name LIKE ? OR s.student_reg_no LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}

if (!empty($categoryFilter)) {
    $sql .= " AND s.course_category = ?";
    $params[] = $categoryFilter;
}

$sql .= " GROUP BY s.student_id ORDER BY s.student_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Student Directory</h3>
        <p class="text-muted mb-0">Manage registered driving students, enrollments, and tuition fees.</p>
    </div>
    <?php if (has_role(['Administrator', 'Receptionist'])): ?>
    <a href="student_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-user-plus me-1"></i> Register New Student
    </a>
    <?php endif; ?>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="students.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, reg no, phone, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- All Training Statuses --</option>
                    <option value="Enrolled" <?= ($statusFilter === 'Enrolled') ? 'selected' : '' ?>>Enrolled</option>
                    <option value="In Training" <?= ($statusFilter === 'In Training') ? 'selected' : '' ?>>In Training</option>
                    <option value="Test Ready" <?= ($statusFilter === 'Test Ready') ? 'selected' : '' ?>>Test Ready</option>
                    <option value="Completed" <?= ($statusFilter === 'Completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="Dropped" <?= ($statusFilter === 'Dropped') ? 'selected' : '' ?>>Dropped</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="students.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reg No</th>
                        <th>Student Name</th>
                        <th>Contact</th>
                        <th>Course Category</th>
                        <th>Fee / Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No student records found matching your query.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): 
                            $fee = (float)$s['course_fee'];
                            $paid = (float)$s['total_paid'];
                            $balance = $fee - $paid;
                        ?>
                            <tr>
                                <td><span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($s['student_reg_no']) ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['full_name']) ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-venus-mars me-1"></i> <?= htmlspecialchars($s['gender']) ?></small>
                                </td>
                                <td>
                                    <div><i class="fa-solid fa-phone me-1 text-muted small"></i> <?= htmlspecialchars($s['phone']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['email'] ?? 'No email') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                        <?= htmlspecialchars($s['course_category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= format_currency($fee) ?></div>
                                    <small class="text-success fw-semibold">Paid: <?= format_currency($paid) ?></small>
                                </td>
                                <td>
                                    <?php if ($balance <= 0): ?>
                                        <span class="badge bg-success">Cleared</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= format_currency($balance) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $stBadge = [
                                        'Enrolled' => 'primary',
                                        'In Training' => 'info text-dark',
                                        'Test Ready' => 'warning text-dark',
                                        'Completed' => 'success',
                                        'Dropped' => 'secondary'
                                    ][$s['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $stBadge ?>"><?= htmlspecialchars($s['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="student_view.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-primary" title="View Profile">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if (has_role(['Administrator', 'Receptionist'])): ?>
                                        <a href="student_edit.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-secondary" title="Edit Student">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="payment_add.php?student_id=<?= $s['student_id'] ?>" class="btn btn-outline-success" title="Record Payment">
                                            <i class="fa-solid fa-money-bill"></i>
                                        </a>
                                        <a href="schedule_add.php?student_id=<?= $s['student_id'] ?>" class="btn btn-outline-warning text-dark" title="Book Lesson">
                                            <i class="fa-solid fa-calendar-plus"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (has_role('Administrator')): ?>
                                        <a href="student_delete.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this student record? This cannot be undone.')" title="Delete Student">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
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
