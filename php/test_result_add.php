<?php
$pageTitle = 'Record Driving Test Result';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist', 'Instructor']);

$preStudentId = (int)($_GET['student_id'] ?? 0);
$error = '';

$students = $pdo->query("SELECT student_id, student_reg_no, full_name, course_category FROM students ORDER BY full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $test_date = trim($_POST['test_date'] ?? date('Y-m-d'));
    $test_type = trim($_POST['test_type'] ?? 'Internal Practical Mock Test');
    $score = (int)($_POST['score'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pass');
    $examiner_name = trim($_POST['examiner_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$student_id || empty($examiner_name)) {
        $error = 'Please select a student and provide the examiner name.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO driving_test_results (student_id, test_date, test_type, score, status, examiner_name, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $test_date, $test_type, $score, $status, $examiner_name, $remarks]);

            // If final test passed, optionally update student status to Completed
            if ($test_type === 'Final NTSA/Police Driving Test' && $status === 'Pass') {
                $stmtUpdateStu = $pdo->prepare("UPDATE students SET status = 'Completed' WHERE student_id = ?");
                $stmtUpdateStu->execute([$student_id]);
            }

            set_flash('success', 'Driving test result recorded successfully!');
            redirect('test_results.php');
        } catch (Exception $e) {
            $error = 'Error saving test result: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="test_results.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Test Results</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-award text-warning me-2"></i> Record Driving Test Assessment</h3>
    <p class="text-muted mb-0">Record scores and examiner remarks for internal and official driving evaluations.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="test_result_add.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['student_id'] ?>" <?= ($preStudentId === (int)$stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Test Category <span class="text-danger">*</span></label>
                    <select name="test_type" class="form-select" required>
                        <option value="Internal Theory Test">Internal Theory Test (Road Signs & Model Town)</option>
                        <option value="Internal Practical Mock Test" selected>Internal Practical Mock Test (AA Pre-Test)</option>
                        <option value="Final NTSA/Police Driving Test">Final NTSA / Police Driving Test</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Test Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="test_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Score Achieved (0 - 100) <span class="text-danger">*</span></label>
                    <input type="number" min="0" max="100" class="form-control fw-bold" name="score" id="score" value="75" required oninput="autoOutcome()">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Outcome / Result <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Examiner / Assessor Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="examiner_name" value="<?= htmlspecialchars($_SESSION['full_name']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Examiner Remarks & Assessment Notes</label>
                    <textarea class="form-control" name="remarks" rows="3" placeholder="e.g. Excellent vehicle positioning and lane discipline. Passed three-point turn on first attempt."></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="test_results.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Test Assessment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function autoOutcome() {
    const sc = parseInt(document.getElementById('score').value) || 0;
    document.getElementById('status').value = (sc >= 70) ? 'Pass' : 'Fail';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
