<?php
$pageTitle = 'Edit Instructor';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$insId = (int)($_GET['id'] ?? 0);
if (!$insId) {
    set_flash('danger', 'Invalid instructor ID.');
    redirect('instructors.php');
}

$stmt = $pdo->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->execute([$insId]);
$instructor = $stmt->fetch();

if (!$instructor) {
    set_flash('danger', 'Instructor not found.');
    redirect('instructors.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Male');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');
    $license_expiry = trim($_POST['license_expiry'] ?? '') ?: null;
    $specialization = trim($_POST['specialization'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    if (empty($full_name) || empty($phone) || empty($email) || empty($license_no)) {
        $error = 'Full Name, Phone Number, Email, and Instructor License No are required.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE instructors SET
                    full_name = ?,
                    gender = ?,
                    phone = ?,
                    email = ?,
                    license_no = ?,
                    license_expiry = ?,
                    specialization = ?,
                    address = ?,
                    status = ?
                WHERE instructor_id = ?
            ");
            $stmtUpdate->execute([$full_name, $gender, $phone, $email, $license_no, $license_expiry, $specialization, $address, $status, $insId]);

            set_flash('success', "Instructor $full_name updated successfully!");
            redirect('instructors.php');
        } catch (Exception $e) {
            $error = 'Error updating instructor: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="instructors.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Instructors</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Instructor: <?= htmlspecialchars($instructor['full_name']) ?></h3>
    <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($instructor['instructor_no']) ?></span>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form method="POST" action="instructor_edit.php?id=<?= $insId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($instructor['full_name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?= ($instructor['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($instructor['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($instructor['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Employment Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" <?= ($instructor['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="On Leave" <?= ($instructor['status'] === 'On Leave') ? 'selected' : '' ?>>On Leave</option>
                        <option value="Inactive" <?= ($instructor['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($instructor['phone']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($instructor['email']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">NTSA License Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="license_no" value="<?= htmlspecialchars($instructor['license_no']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">License Expiry Date</label>
                    <input type="date" class="form-control" name="license_expiry" value="<?= htmlspecialchars($instructor['license_expiry'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Specialization</label>
                    <input type="text" class="form-control" name="specialization" value="<?= htmlspecialchars($instructor['specialization']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Residential Address</label>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($instructor['address']) ?>">
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="instructors.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
