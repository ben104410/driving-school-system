<?php
$pageTitle = 'Edit Lesson Schedule';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$scheduleId = (int)($_GET['id'] ?? 0);
if (!$scheduleId) {
    set_flash('danger', 'Invalid schedule ID.');
    redirect('schedules.php');
}

$stmt = $pdo->prepare("SELECT * FROM lesson_schedules WHERE schedule_id = ?");
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch();

if (!$schedule) {
    set_flash('danger', 'Schedule not found.');
    redirect('schedules.php');
}

$students = $pdo->query("SELECT student_id, student_reg_no, full_name, course_category FROM students ORDER BY full_name ASC")->fetchAll();
$instructors = $pdo->query("SELECT instructor_id, instructor_no, full_name, specialization FROM instructors WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
$vehicles = $pdo->query("SELECT vehicle_id, reg_no, model, transmission FROM vehicles WHERE status != 'Maintenance' ORDER BY model ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $instructor_id = (int)($_POST['instructor_id'] ?? 0);
    $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
    $lesson_topic = trim($_POST['lesson_topic'] ?? '');
    $lesson_date = trim($_POST['lesson_date'] ?? '');
    $lesson_time = trim($_POST['lesson_time'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 60);
    $status = trim($_POST['status'] ?? 'Scheduled');
    $notes = trim($_POST['instructor_notes'] ?? '');

    if (!$student_id || !$instructor_id || empty($lesson_date) || empty($lesson_time)) {
        $error = 'Please select a Student, Instructor, Date, and Time.';
    } else {
        // Conflict checks (exclude current schedule_id)
        $stmtChkIns = $pdo->prepare("
            SELECT ls.*, s.full_name AS student_name 
            FROM lesson_schedules ls
            JOIN students s ON ls.student_id = s.student_id
            WHERE ls.instructor_id = ? AND ls.lesson_date = ? 
              AND ls.schedule_id != ?
              AND ls.status NOT IN ('Cancelled')
              AND ABS(TIME_TO_SEC(TIMEDIFF(ls.lesson_time, ?))) < (? * 60)
        ");
        $stmtChkIns->execute([$instructor_id, $lesson_date, $scheduleId, $lesson_time, $duration]);
        $insConflict = $stmtChkIns->fetch();

        if ($insConflict) {
            $error = "Conflict: Instructor already booked with {$insConflict['student_name']} at " . date('h:i A', strtotime($insConflict['lesson_time'])) . " on $lesson_date.";
        } else {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE lesson_schedules SET
                        student_id = ?,
                        instructor_id = ?,
                        vehicle_id = ?,
                        lesson_topic = ?,
                        lesson_date = ?,
                        lesson_time = ?,
                        duration_minutes = ?,
                        status = ?,
                        instructor_notes = ?
                    WHERE schedule_id = ?
                ");
                $stmtUpdate->execute([$student_id, $instructor_id, $vehicle_id, $lesson_topic, $lesson_date, $lesson_time, $duration, $status, $notes, $scheduleId]);

                set_flash('success', 'Lesson schedule updated successfully!');
                redirect('schedules.php');
            } catch (Exception $e) {
                $error = 'Error updating schedule: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="mb-4">
    <a href="schedules.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Schedules</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Driving Lesson Booking</h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-9">
    <div class="card-body p-4">
        <form method="POST" action="schedule_edit.php?id=<?= $scheduleId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['student_id'] ?>" <?= ($schedule['student_id'] == $stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Instructor <span class="text-danger">*</span></label>
                    <select name="instructor_id" class="form-select" required>
                        <?php foreach ($instructors as $ins): ?>
                            <option value="<?= $ins['instructor_id'] ?>" <?= ($schedule['instructor_id'] == $ins['instructor_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ins['full_name']) ?> (<?= htmlspecialchars($ins['instructor_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Training Vehicle</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">-- No Vehicle / TBD --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['vehicle_id'] ?>" <?= ($schedule['vehicle_id'] == $v['vehicle_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['reg_no']) ?> - <?= htmlspecialchars($v['model']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Lesson Topic</label>
                    <input type="text" class="form-control" name="lesson_topic" value="<?= htmlspecialchars($schedule['lesson_topic']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lesson Date</label>
                    <input type="date" class="form-control" name="lesson_date" value="<?= htmlspecialchars($schedule['lesson_date']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lesson Time</label>
                    <input type="time" class="form-control" name="lesson_time" value="<?= htmlspecialchars($schedule['lesson_time']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Scheduled" <?= ($schedule['status'] === 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
                        <option value="In Progress" <?= ($schedule['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="Completed" <?= ($schedule['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= ($schedule['status'] === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="No Show" <?= ($schedule['status'] === 'No Show') ? 'selected' : '' ?>>No Show</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Instructor Notes</label>
                    <textarea class="form-control" name="instructor_notes" rows="2"><?= htmlspecialchars($schedule['instructor_notes'] ?? '') ?></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="schedules.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
