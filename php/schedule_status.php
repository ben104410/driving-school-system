<?php
$pageTitle = 'Update Lesson Status';
require_once __DIR__ . '/includes/header.php';

$scheduleId = (int)($_GET['id'] ?? 0);
if (!$scheduleId) {
    set_flash('danger', 'Invalid schedule ID.');
    redirect('schedules.php');
}

$stmt = $pdo->prepare("
    SELECT ls.*, s.full_name AS student_name, s.student_reg_no, i.full_name AS instructor_name, v.reg_no AS vehicle_reg
    FROM lesson_schedules ls
    JOIN students s ON ls.student_id = s.student_id
    JOIN instructors i ON ls.instructor_id = i.instructor_id
    LEFT JOIN vehicles v ON ls.vehicle_id = v.vehicle_id
    WHERE ls.schedule_id = ?
");
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch();

if (!$schedule) {
    set_flash('danger', 'Schedule not found.');
    redirect('schedules.php');
}

// Check authorization: ONLY Administrator or assigned Instructor can approve that a lesson is finished
if ($_SESSION['role'] === 'Instructor') {
    $stmtInst = $pdo->prepare("SELECT instructor_id FROM instructors WHERE user_id = ?");
    $stmtInst->execute([$_SESSION['user_id']]);
    $myInstId = $stmtInst->fetchColumn();
    if ($myInstId != $schedule['instructor_id']) {
        set_flash('danger', 'Authorization Denied: You can only approve or update lessons assigned to you.');
        redirect('schedules.php');
    }
} elseif ($_SESSION['role'] !== 'Administrator') {
    set_flash('danger', 'Authorization Denied: Only an Administrator or the assigned Instructor can approve that a driving lesson is finished.');
    redirect('schedules.php');
}

// 1-Click Quick Approval via GET parameter
if (isset($_GET['approve']) && $_GET['approve'] == 1) {
    try {
        $approver = $_SESSION['full_name'] . ' (' . $_SESSION['role'] . ')';
        $stmtApprove = $pdo->prepare("UPDATE lesson_schedules SET status = 'Completed', approved_by = ?, approved_at = NOW() WHERE schedule_id = ?");
        $stmtApprove->execute([$approver, $scheduleId]);

        set_flash('success', "Lesson has been approved as finished and marked Completed by $approver!");
        redirect('schedules.php');
    } catch (Exception $e) {
        set_flash('danger', 'Error approving lesson: ' . $e->getMessage());
        redirect('schedules.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status'] ?? 'Pending');
    $notes = trim($_POST['instructor_notes'] ?? '');

    try {
        if ($status === 'Completed') {
            $approver = $_SESSION['full_name'] . ' (' . $_SESSION['role'] . ')';
            $stmtUpdate = $pdo->prepare("UPDATE lesson_schedules SET status = 'Completed', approved_by = ?, approved_at = NOW(), instructor_notes = ? WHERE schedule_id = ?");
            $stmtUpdate->execute([$approver, $notes, $scheduleId]);
            set_flash('success', "Driving lesson approved as finished and marked Completed by $approver!");
        } else {
            // Remains Pending or other status without completion approval
            $stmtUpdate = $pdo->prepare("UPDATE lesson_schedules SET status = ?, approved_by = NULL, approved_at = NULL, instructor_notes = ? WHERE schedule_id = ?");
            $stmtUpdate->execute([$status, $notes, $scheduleId]);
            set_flash('warning', "Lesson status updated to '$status'. It remains pending completion until approved as finished by an Administrator or Instructor.");
        }
        redirect('schedules.php');
    } catch (Exception $e) {
        $error = 'Error updating schedule: ' . $e->getMessage();
    }
}
?>

<div class="mb-4">
    <a href="schedules.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Schedules</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> Approve / Update Driving Lesson Status</h3>
    <p class="text-muted mb-0">Lessons remain <strong>Pending</strong> until an Administrator or the assigned Instructor approves that they are finished.</p>
</div>

<div class="card border-0 shadow-sm rounded-3 col-lg-7">
    <div class="card-body p-4">
        <div class="bg-light p-3 rounded-3 mb-4 border">
            <div class="row g-2">
                <div class="col-sm-6">
                    <small class="text-muted d-block">Student:</small>
                    <strong><?= htmlspecialchars($schedule['student_name']) ?></strong> (<?= htmlspecialchars($schedule['student_reg_no']) ?>)
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Assigned Instructor:</small>
                    <strong><?= htmlspecialchars($schedule['instructor_name']) ?></strong>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Scheduled Date & Time:</small>
                    <strong><?= format_date($schedule['lesson_date']) ?></strong> at <?= date('h:i A', strtotime($schedule['lesson_time'])) ?>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Lesson Topic:</small>
                    <strong><?= htmlspecialchars($schedule['lesson_topic']) ?></strong>
                </div>
                <div class="col-12 mt-2 pt-2 border-top">
                    <small class="text-muted d-block">Current Status:</small>
                    <?php if ($schedule['status'] === 'Completed'): ?>
                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Completed</span>
                        <small class="text-muted ms-2">Approved by: <?= htmlspecialchars($schedule['approved_by'] ?? 'Administrator') ?></small>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fa-regular fa-clock me-1"></i> Pending Approval</span>
                        <small class="text-muted ms-2">(Not yet approved as finished)</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" action="schedule_status.php?id=<?= $scheduleId ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Lesson Completion Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                    <option value="Pending" <?= ($schedule['status'] === 'Pending') ? 'selected' : '' ?>>Pending (Not finished or awaiting approval)</option>
                    <option value="Completed" <?= ($schedule['status'] === 'Completed') ? 'selected' : '' ?>>Completed (Approved as Finished)</option>
                    <option value="In Progress" <?= ($schedule['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress (Lesson in session)</option>
                    <option value="Cancelled" <?= ($schedule['status'] === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="No Show" <?= ($schedule['status'] === 'No Show') ? 'selected' : '' ?>>No Show (Student Absent)</option>
                </select>
                <small class="text-muted">If neither Administrator nor Instructor approves as Completed, the status remains <strong>Pending</strong>.</small>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Instructor Assessment Notes / Feedback</label>
                <textarea name="instructor_notes" class="form-control" rows="4" placeholder="Detail learner vehicle handling, mirror checks, clutch control, lane discipline, or areas needing more practice..."><?= htmlspecialchars($schedule['instructor_notes'] ?? '') ?></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="schedules.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Status</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
