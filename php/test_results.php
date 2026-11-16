<?php
$pageTitle = 'Driving Test Results';
require_once __DIR__ . '/includes/header.php';

$sql = "
    SELECT tr.*, s.full_name AS student_name, s.student_reg_no, s.course_category
    FROM driving_test_results tr
    JOIN students s ON tr.student_id = s.student_id
    WHERE 1=1
";
$params = [];

// If student, restrict to own results
if ($_SESSION['role'] === 'Student') {
    $stmtStu = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? OR email = ?");
    $stmtStu->execute([$_SESSION['user_id'], $_SESSION['email'] ?? '']);
    $myStuId = $stmtStu->fetchColumn();
    $sql .= " AND tr.student_id = ?";
    $params[] = $myStuId ?: 0;
}

$sql .= " ORDER BY tr.test_date DESC, tr.result_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-square-poll-vertical text-warning me-2"></i> Driving Test Assessments</h3>
        <p class="text-muted mb-0">Theory, internal practical mock exams, and official NTSA driving test results.</p>
    </div>
    <?php if (has_role(['Administrator', 'Receptionist', 'Instructor'])): ?>
    <a href="test_result_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-plus me-1"></i> Record Test Result
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Test Date</th>
                        <th>Student Name</th>
                        <th>Test Category</th>
                        <th>Score</th>
                        <th>Outcome</th>
                        <th>Examiner / Assessor</th>
                        <th>Remarks / Feedback</th>
                        <?php if (has_role(['Administrator', 'Receptionist', 'Instructor'])): ?>
                        <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No driving test results recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= format_date($r['test_date']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($r['student_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['student_reg_no']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['test_type']) ?></span>
                                    <div class="small text-muted"><?= htmlspecialchars($r['course_category']) ?></div>
                                </td>
                                <td>
                                    <span class="fw-bold fs-6 <?= ($r['score'] >= 70) ? 'text-success' : 'text-danger' ?>">
                                        <?= $r['score'] ?> / 100
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= ($r['status'] === 'Pass') ? 'success' : (($r['status'] === 'Fail') ? 'danger' : 'warning text-dark') ?> px-2 py-1">
                                        <?= htmlspecialchars($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['examiner_name']) ?></td>
                                <td class="small text-muted" style="max-width: 200px;"><?= htmlspecialchars($r['remarks'] ?? '-') ?></td>
                                <?php if (has_role(['Administrator', 'Receptionist', 'Instructor'])): ?>
                                <td class="text-end">
                                    <a href="test_result_edit.php?id=<?= $r['result_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit Result">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
