<?php
$pageTitle = 'Edit Student Details';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    set_flash('danger', 'Invalid student ID.');
    redirect('students.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Student not found.');
    redirect('students.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Male');
    $dob = trim($_POST['date_of_birth'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $national_id = trim($_POST['national_id'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? 'Mombasa');
    $course_category = trim($_POST['course_category'] ?? '');
    $course_fee = (float)($_POST['course_fee'] ?? 15000);
    $status = trim($_POST['status'] ?? 'Enrolled');

    if (empty($full_name) || empty($phone) || empty($course_category)) {
        $error = 'Full Name, Phone Number, and Course Category are required.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE students SET
                    full_name = ?,
                    gender = ?,
                    date_of_birth = ?,
                    phone = ?,
                    email = ?,
                    national_id = ?,
                    address = ?,
                    course_category = ?,
                    course_fee = ?,
                    status = ?
                WHERE student_id = ?
            ");
            $stmtUpdate->execute([$full_name, $gender, $dob, $phone, $email, $national_id, $address, $course_category, $course_fee, $status, $studentId]);

            set_flash('success', "Student $full_name updated successfully!");
            redirect("student_view.php?id=$studentId");
        } catch (Exception $e) {
            $error = 'Error updating student: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="student_view.php?id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Student Profile</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Student: <?= htmlspecialchars($student['full_name']) ?></h3>
    <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($student['student_reg_no']) ?></span>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form method="POST" action="student_edit.php?id=<?= $studentId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?= ($student['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($student['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($student['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date of Birth</label>
                    <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($student['date_of_birth'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">National ID / Passport</label>
                    <input type="text" class="form-control" name="national_id" value="<?= htmlspecialchars($student['national_id'] ?? '') ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Home / Residential Address</label>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($student['address']) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-semibold">Course Category <span class="text-danger">*</span></label>
                    <select name="course_category" class="form-select" required>
                        <option value="Category B (Light Vehicle - Manual)" <?= ($student['course_category'] === 'Category B (Light Vehicle - Manual)') ? 'selected' : '' ?>>Category B (Light Vehicle - Manual)</option>
                        <option value="Category B (Light Vehicle - Automatic)" <?= ($student['course_category'] === 'Category B (Light Vehicle - Automatic)') ? 'selected' : '' ?>>Category B (Light Vehicle - Automatic)</option>
                        <option value="Category A (Motorcycle)" <?= ($student['course_category'] === 'Category A (Motorcycle)') ? 'selected' : '' ?>>Category A (Motorcycle)</option>
                        <option value="Category C1 (Light Truck)" <?= ($student['course_category'] === 'Category C1 (Light Truck)') ? 'selected' : '' ?>>Category C1 (Light Truck)</option>
                        <option value="Refresher Course" <?= ($student['course_category'] === 'Refresher Course') ? 'selected' : '' ?>>Refresher Course</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Agreed Tuition Fee (KES)</label>
                    <input type="number" step="0.01" class="form-control fw-bold" name="course_fee" value="<?= htmlspecialchars($student['course_fee']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Training Status</label>
                    <select name="status" class="form-select">
                        <option value="Enrolled" <?= ($student['status'] === 'Enrolled') ? 'selected' : '' ?>>Enrolled</option>
                        <option value="In Training" <?= ($student['status'] === 'In Training') ? 'selected' : '' ?>>In Training</option>
                        <option value="Test Ready" <?= ($student['status'] === 'Test Ready') ? 'selected' : '' ?>>Test Ready</option>
                        <option value="Completed" <?= ($student['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="Dropped" <?= ($student['status'] === 'Dropped') ? 'selected' : '' ?>>Dropped</option>
                    </select>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="student_view.php?id=<?= $studentId ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
