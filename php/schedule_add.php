<?php
$pageTitle = 'Book Driving Lesson';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$preStudentId = (int)($_GET['student_id'] ?? 0);
$error = '';

$students = $pdo->query("SELECT student_id, student_reg_no, full_name, course_category FROM students WHERE status IN ('Enrolled', 'In Training', 'Test Ready') ORDER BY full_name ASC")->fetchAll();
$instructors = $pdo->query("SELECT instructor_id, instructor_no, full_name, specialization FROM instructors WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
$vehicles = $pdo->query("SELECT vehicle_id, reg_no, model, transmission FROM vehicles WHERE status != 'Maintenance' ORDER BY model ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $instructor_id = (int)($_POST['instructor_id'] ?? 0);
    $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
    $lesson_topic = trim($_POST['lesson_topic'] ?? 'Basic Controls & Steering');
    $lesson_date = trim($_POST['lesson_date'] ?? date('Y-m-d'));
    $lesson_time = trim($_POST['lesson_time'] ?? '09:00');
    $duration = (int)($_POST['duration_minutes'] ?? 60);
    $notes = trim($_POST['instructor_notes'] ?? '');

    if (!$student_id || !$instructor_id || empty($lesson_date) || empty($lesson_time)) {
        $error = 'Please select a Student, Instructor, Date, and Time.';
    } else {
        // Double-booking Conflict Check:
        // Check instructor conflict on same date and exact or near time (within duration)
        $stmtChkIns = $pdo->prepare("
            SELECT ls.*, s.full_name AS student_name 
            FROM lesson_schedules ls
            JOIN students s ON ls.student_id = s.student_id
            WHERE ls.instructor_id = ? AND ls.lesson_date = ? 
              AND ls.status NOT IN ('Cancelled')
              AND ABS(TIME_TO_SEC(TIMEDIFF(ls.lesson_time, ?))) < (? * 60)
        ");
        $stmtChkIns->execute([$instructor_id, $lesson_date, $lesson_time, $duration]);
        $insConflict = $stmtChkIns->fetch();

        // Check vehicle conflict
        $vehConflict = null;
        if ($vehicle_id) {
            $stmtChkVeh = $pdo->prepare("
                SELECT ls.*, i.full_name AS instructor_name
                FROM lesson_schedules ls
                JOIN instructors i ON ls.instructor_id = i.instructor_id
                WHERE ls.vehicle_id = ? AND ls.lesson_date = ? 
                  AND ls.status NOT IN ('Cancelled')
                  AND ABS(TIME_TO_SEC(TIMEDIFF(ls.lesson_time, ?))) < (? * 60)
            ");
            $stmtChkVeh->execute([$vehicle_id, $lesson_date, $lesson_time, $duration]);
            $vehConflict = $stmtChkVeh->fetch();
        }

        // Check student conflict
        $stmtChkStu = $pdo->prepare("
            SELECT ls.* 
            FROM lesson_schedules ls
            WHERE ls.student_id = ? AND ls.lesson_date = ? 
              AND ls.status NOT IN ('Cancelled')
              AND ABS(TIME_TO_SEC(TIMEDIFF(ls.lesson_time, ?))) < (? * 60)
        ");
        $stmtChkStu->execute([$student_id, $lesson_date, $lesson_time, $duration]);
        $stuConflict = $stmtChkStu->fetch();

        if ($insConflict) {
            $error = "Scheduling Conflict: The selected Instructor is already booked with student {$insConflict['student_name']} at " . date('h:i A', strtotime($insConflict['lesson_time'])) . " on $lesson_date.";
        } elseif ($vehConflict) {
            $error = "Scheduling Conflict: The selected Vehicle is already assigned to instructor {$vehConflict['instructor_name']} at " . date('h:i A', strtotime($vehConflict['lesson_time'])) . " on $lesson_date.";
        } elseif ($stuConflict) {
            $error = "Scheduling Conflict: This student already has another driving lesson booked at " . date('h:i A', strtotime($stuConflict['lesson_time'])) . " on $lesson_date.";
        } else {
            try {
                $stmtIns = $pdo->prepare("
                    INSERT INTO lesson_schedules (student_id, instructor_id, vehicle_id, lesson_topic, lesson_date, lesson_time, duration_minutes, status, instructor_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)
                ");
                $stmtIns->execute([$student_id, $instructor_id, $vehicle_id, $lesson_topic, $lesson_date, $lesson_time, $duration, $notes]);

                set_flash('success', 'Driving lesson booked successfully with no conflicts detected!');
                redirect('schedules.php');
            } catch (Exception $e) {
                $error = 'Error booking lesson: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="mb-4">
    <a href="schedules.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Schedules</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-calendar-plus text-primary me-2"></i> Book Driving Lesson</h3>
    <p class="text-muted mb-0">Schedule practical driving lesson with real-time conflict checking.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-9">
    <div class="card-body p-4">
        <form method="POST" action="schedule_add.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['student_id'] ?>" <?= ($preStudentId === (int)$stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?> - <?= htmlspecialchars($stu['course_category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Instructor <span class="text-danger">*</span></label>
                    <select name="instructor_id" class="form-select" required>
                        <option value="">-- Choose Instructor --</option>
                        <?php foreach ($instructors as $ins): ?>
                            <option value="<?= $ins['instructor_id'] ?>">
                                <?= htmlspecialchars($ins['full_name']) ?> (<?= htmlspecialchars($ins['instructor_no']) ?> &bull; <?= htmlspecialchars($ins['specialization']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Assign Vehicle</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">-- No Vehicle Assigned / TBD --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['vehicle_id'] ?>">
                                <?= htmlspecialchars($v['reg_no']) ?> - <?= htmlspecialchars($v['model']) ?> (<?= htmlspecialchars($v['transmission']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Lesson Topic / Maneuver <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="lesson_topic" list="topic_suggestions" placeholder="e.g. Hill Starts & Clutch Control" value="Basic Controls & Steering" required>
                    <datalist id="topic_suggestions">
                        <option value="Basic Controls, Cockpit Drill & Steering">
                        <option value="Clutch Control & Smooth Hill Starts">
                        <option value="Gear Changing & Speed Judgement">
                        <option value="Three-Point Turn & Reversing">
                        <option value="Parallel Parking & Reverse Bay Parking">
                        <option value="Roundabouts, Lane Discipline & Traffic Signs">
                        <option value="Defensive Highway Driving (Mombasa CBD/Nyali)">
                        <option value="Pre-Test Mock Practical Driving Assessment">
                    </datalist>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lesson Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="lesson_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lesson Time <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" name="lesson_time" value="09:00" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Duration (Minutes)</label>
                    <select name="duration_minutes" class="form-select">
                        <option value="45">45 Minutes</option>
                        <option value="60" selected>60 Minutes (1 Hour)</option>
                        <option value="90">90 Minutes (1.5 Hours)</option>
                        <option value="120">120 Minutes (2 Hours)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Initial Briefing / Instructor Notes</label>
                    <textarea class="form-control" name="instructor_notes" rows="2" placeholder="e.g. Meet at Mombasa office gate. Bring learner logbook."></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="schedules.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check me-1"></i> Confirm & Book Lesson</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
