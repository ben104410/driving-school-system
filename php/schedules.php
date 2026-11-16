<?php
$pageTitle = 'Lesson Schedules';
require_once __DIR__ . '/includes/header.php';

$dateFilter = trim($_GET['date'] ?? '');
$instructorFilter = (int)($_GET['instructor_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT ls.*, 
           s.full_name AS student_name, s.student_reg_no, s.phone AS student_phone,
           i.full_name AS instructor_name, i.phone AS instructor_phone,
           v.reg_no AS vehicle_reg, v.model AS vehicle_model
    FROM lesson_schedules ls
    JOIN students s ON ls.student_id = s.student_id
    JOIN instructors i ON ls.instructor_id = i.instructor_id
    LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
    WHERE 1=1
";
$params = [];

// If logged in as instructor, restrict to own schedules
if ($_SESSION['role'] === 'Instructor') {
    $stmtInst = $pdo->prepare("SELECT instructor_id FROM instructors WHERE user_id = ?");
    $stmtInst->execute([$_SESSION['user_id']]);
    $myInstId = $stmtInst->fetchColumn();
    $sql .= " AND ls.instructor_id = ?";
    $params[] = $myInstId ?: 0;
}

// If logged in as student, restrict to own schedules
if ($_SESSION['role'] === 'Student') {
    $stmtStu = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? OR email = ?");
    $stmtStu->execute([$_SESSION['user_id'], $_SESSION['email'] ?? '']);
    $myStuId = $stmtStu->fetchColumn();
    $sql .= " AND ls.student_id = ?";
    $params[] = $myStuId ?: 0;
}

if (!empty($dateFilter)) {
    $sql .= " AND ls.lesson_date = ?";
    $params[] = $dateFilter;
}

if ($instructorFilter > 0 && $_SESSION['role'] !== 'Instructor') {
    $sql .= " AND ls.instructor_id = ?";
    $params[] = $instructorFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND ls.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY ls.lesson_date DESC, ls.lesson_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$allInstructors = $pdo->query("SELECT instructor_id, full_name FROM instructors WHERE status = 'Active'")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-calendar-days text-primary me-2"></i> Practical Driving Lesson Timetable</h3>
        <p class="text-muted mb-0">Organized schedules with automatic double-booking prevention.</p>
    </div>
    <?php if (has_role(['Administrator', 'Receptionist'])): ?>
    <a href="schedule_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-calendar-plus me-1"></i> Book Driving Lesson
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="schedules.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Filter by Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
            </div>
            <?php if ($_SESSION['role'] !== 'Instructor'): ?>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Filter by Instructor</label>
                <select name="instructor_id" class="form-select">
                    <option value="">-- All Instructors --</option>
                    <?php foreach ($allInstructors as $ins): ?>
                        <option value="<?= $ins['instructor_id'] ?>" <?= ($instructorFilter === (int)$ins['instructor_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ins['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">-- All Statuses --</option>
                    <option value="Scheduled" <?= ($statusFilter === 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
                    <option value="In Progress" <?= ($statusFilter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed" <?= ($statusFilter === 'Completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= ($statusFilter === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="No Show" <?= ($statusFilter === 'No Show') ? 'selected' : '' ?>>No Show</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2 align-items-end" style="margin-top: 2rem;">
                <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="schedules.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Student</th>
                        <th>Instructor</th>
                        <th>Vehicle</th>
                        <th>Topic / Practical Skill</th>
                        <th>Status</th>
                        <th>Instructor Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No driving lesson schedules found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= format_date($s['lesson_date']) ?></div>
                                    <small class="text-primary fw-semibold"><i class="fa-regular fa-clock me-1"></i> <?= date('h:i A', strtotime($s['lesson_time'])) ?> (<?= $s['duration_minutes'] ?>m)</small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($s['student_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['student_reg_no']) ?> &bull; <?= htmlspecialchars($s['student_phone']) ?></small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($s['instructor_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['instructor_phone']) ?></small>
                                </td>
                                <td>
                                    <?php if ($s['vehicle_reg']): ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($s['vehicle_reg']) ?></span>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($s['vehicle_model']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['lesson_topic']) ?></td>
                                <td>
                                    <?php
                                    $sBadge = [
                                        'Scheduled' => 'primary',
                                        'In Progress' => 'warning text-dark',
                                        'Completed' => 'success',
                                        'Cancelled' => 'danger',
                                        'No Show' => 'secondary'
                                    ][$s['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $sBadge ?>"><?= htmlspecialchars($s['status']) ?></span>
                                </td>
                                <td class="small text-muted" style="max-width: 180px;">
                                    <?= htmlspecialchars($s['instructor_notes'] ?? '-') ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="schedule_status.php?id=<?= $s['schedule_id'] ?>" class="btn btn-outline-primary" title="Update Status / Notes">
                                            <i class="fa-solid fa-list-check"></i>
                                        </a>
                                        <?php if (has_role(['Administrator', 'Receptionist'])): ?>
                                        <a href="schedule_edit.php?id=<?= $s['schedule_id'] ?>" class="btn btn-outline-secondary" title="Edit Booking">
                                            <i class="fa-solid fa-pen-to-square"></i>
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
