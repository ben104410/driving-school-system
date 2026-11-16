<?php
$pageTitle = 'Instructor Management';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$stmt = $pdo->query("
    SELECT i.*, 
    COUNT(DISTINCT ls.student_id) AS student_count
    FROM instructors i
    LEFT JOIN lesson_schedules ls ON i.instructor_id = ls.instructor_id
    GROUP BY i.instructor_id
    ORDER BY i.instructor_id ASC
");
$instructors = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-chalkboard-user text-primary me-2"></i> Driving Instructors</h3>
        <p class="text-muted mb-0">Certified NTSA instructors at AA Driving School, Mombasa.</p>
    </div>
    <a href="instructor_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-user-plus me-1"></i> Add New Instructor
    </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Instructor No</th>
                        <th>Full Name</th>
                        <th>Phone / Email</th>
                        <th>NTSA License No</th>
                        <th>License Expiry</th>
                        <th>Specialization</th>
                        <th>Assigned Students</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instructors)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No instructors registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($instructors as $ins): ?>
                            <tr>
                                <td><span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($ins['instructor_no']) ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($ins['full_name']) ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-venus-mars me-1"></i> <?= htmlspecialchars($ins['gender']) ?></small>
                                </td>
                                <td>
                                    <div><i class="fa-solid fa-phone me-1 text-muted small"></i> <?= htmlspecialchars($ins['phone']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($ins['email']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border font-monospace">
                                        <?= htmlspecialchars($ins['license_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $expiry = $ins['license_expiry'];
                                    $isExpired = $expiry && strtotime($expiry) < time();
                                    ?>
                                    <span class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                        <?= format_date($expiry) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($ins['specialization']) ?></small></td>
                                <td><span class="badge bg-primary rounded-pill"><?= $ins['student_count'] ?> active</span></td>
                                <td>
                                    <span class="badge bg-<?= ($ins['status'] === 'Active') ? 'success' : (($ins['status'] === 'On Leave') ? 'warning text-dark' : 'secondary') ?>">
                                        <?= htmlspecialchars($ins['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="instructor_edit.php?id=<?= $ins['instructor_id'] ?>" class="btn btn-outline-secondary" title="Edit Instructor">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if (has_role('Administrator')): ?>
                                        <a href="instructor_delete.php?id=<?= $ins['instructor_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this instructor record?')" title="Delete Instructor">
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
