<?php
$pageTitle = 'Edit Driving Test Result';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist', 'Instructor']);

$resultId = (int)($_GET['id'] ?? 0);
if (!$resultId) {
    set_flash('danger', 'Invalid result ID.');
    redirect('test_results.php');
}

$stmt = $pdo->prepare("SELECT * FROM driving_test_results WHERE result_id = ?");
$stmt->execute([$resultId]);
$result = $stmt->fetch();

if (!$result) {
    set_flash('danger', 'Test result not found.');
    redirect('test_results.php');
}

$students = $pdo->query("SELECT student_id, student_reg_no, full_name FROM students ORDER BY full_name ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $test_date = trim($_POST['test_date'] ?? '');
    $test_type = trim($_POST['test_type'] ?? '');
    $score = (int)($_POST['score'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pass');
    $examiner_name = trim($_POST['examiner_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$student_id || empty($examiner_name)) {
        $error = 'Please select a student and provide the examiner name.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE driving_test_results SET
                    student_id = ?,
                    test_date = ?,
                    test_type = ?,
                    score = ?,
                    status = ?,
                    examiner_name = ?,
                    remarks = ?
                WHERE result_id = ?
            ");
            $stmtUpdate->execute([$student_id, $test_date, $test_type, $score, $status, $examiner_name, $remarks, $resultId]);

            set_flash('success', 'Test result updated successfully!');
            redirect('test_results.php');
        } catch (Exception $e) {
            $error = 'Error updating test result: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="test_results.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Test Results</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Driving Test Assessment</h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="test_result_edit.php?id=<?= $resultId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['student_id'] ?>" <?= ($result['student_id'] == $stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Test Category <span class="text-danger">*</span></label>
                    <select name="test_type" class="form-select" required>
                        <option value="Internal Theory Test" <?= ($result['test_type'] === 'Internal Theory Test') ? 'selected' : '' ?>>Internal Theory Test</option>
                        <option value="Internal Practical Mock Test" <?= ($result['test_type'] === 'Internal Practical Mock Test') ? 'selected' : '' ?>>Internal Practical Mock Test</option>
                        <option value="Final NTSA/Police Driving Test" <?= ($result['test_type'] === 'Final NTSA/Police Driving Test') ? 'selected' : '' ?>>Final NTSA/Police Driving Test</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Test Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="test_date" value="<?= htmlspecialchars($result['test_date']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Score (0 - 100) <span class="text-danger">*</span></label>
                    <input type="number" min="0" max="100" class="form-control fw-bold" name="score" value="<?= htmlspecialchars($result['score']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Outcome <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="Pass" <?= ($result['status'] === 'Pass') ? 'selected' : '' ?>>Pass</option>
                        <option value="Fail" <?= ($result['status'] === 'Fail') ? 'selected' : '' ?>>Fail</option>
                        <option value="Pending" <?= ($result['status'] === 'Pending') ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Examiner Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="examiner_name" value="<?= htmlspecialchars($result['examiner_name']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Examiner Remarks</label>
                    <textarea class="form-control" name="remarks" rows="3"><?= htmlspecialchars($result['remarks'] ?? '') ?></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="test_results.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
