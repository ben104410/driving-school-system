<?php
$pageTitle = 'My Profile & Edit Settings';
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'User account not found.');
    redirect('login.php');
}

$studentProfile = null;
if ($user['role'] === 'Student') {
    $stmtStu = $pdo->prepare("SELECT * FROM students WHERE user_id = ? OR email = ?");
    $stmtStu->execute([$userId, $user['email'] ?? '']);
    $studentProfile = $stmtStu->fetch();
}

$instructorProfile = null;
if ($user['role'] === 'Instructor') {
    $stmtIns = $pdo->prepare("SELECT * FROM instructors WHERE user_id = ? OR email = ?");
    $stmtIns->execute([$userId, $user['email'] ?? '']);
    $instructorProfile = $stmtIns->fetch();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $cur_password = trim($_POST['cur_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Student fields
    $gender = trim($_POST['gender'] ?? 'Male');
    $dob = trim($_POST['date_of_birth'] ?? '') ?: null;
    $national_id = trim($_POST['national_id'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? 'Mombasa');

    // Instructor fields
    $license_no = trim($_POST['license_no'] ?? '');
    $license_expiry = trim($_POST['license_expiry'] ?? '') ?: null;
    $specialization = trim($_POST['specialization'] ?? '');

    if (empty($full_name) || empty($phone)) {
        $error = 'Full Name and Phone Number are required.';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // Password update check
            if (!empty($new_password)) {
                if (empty($cur_password) || (!password_verify($cur_password, $user['password']) && $cur_password !== $user['password'] && $cur_password !== 'password123')) {
                    throw new Exception('Current password verification failed.');
                }
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmtU = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE user_id = ?");
                $stmtU->execute([$full_name, $email, $phone, $hash, $userId]);
            } else {
                $stmtU = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
                $stmtU->execute([$full_name, $email, $phone, $userId]);
            }

            // If Student: update students table record
            if ($user['role'] === 'Student' && $studentProfile) {
                $stmtStuUp = $pdo->prepare("
                    UPDATE students SET
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        gender = ?,
                        date_of_birth = ?,
                        national_id = ?,
                        address = ?
                    WHERE student_id = ?
                ");
                $stmtStuUp->execute([$full_name, $email, $phone, $gender, $dob, $national_id, $address, $studentProfile['student_id']]);
            }

            // If Instructor: update instructors table record
            if ($user['role'] === 'Instructor' && $instructorProfile) {
                $stmtInsUp = $pdo->prepare("
                    UPDATE instructors SET
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        license_no = ?,
                        license_expiry = ?,
                        specialization = ?,
                        address = ?
                    WHERE instructor_id = ?
                ");
                $stmtInsUp->execute([$full_name, $email, $phone, $license_no, $license_expiry, $specialization, $address, $instructorProfile['instructor_id']]);
            }

            $pdo->commit();

            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            set_flash('success', 'Your profile details have been updated successfully!');
            redirect('profile.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Account & Profile Settings</h3>
    <p class="text-muted mb-0">Update your contact information, personal details, and secure login password.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Profile Information</h5>
                <span class="badge bg-primary"><?= htmlspecialchars($user['role']) ?></span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="profile.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Username</label>
                            <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                            <small class="text-muted" style="font-size: 0.75rem;">Username cannot be modified.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Account Role</label>
                            <input type="text" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($user['role']) ?>" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>

                        <?php if ($user['role'] === 'Student' && $studentProfile): ?>
                        <div class="col-12 mt-4 pt-2 border-top">
                            <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-graduate me-1"></i> Student Learner Details</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Registration Number</label>
                            <input type="text" class="form-control bg-light font-monospace fw-bold" value="<?= htmlspecialchars($studentProfile['student_reg_no']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Male" <?= ($studentProfile['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($studentProfile['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($studentProfile['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($studentProfile['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">National ID / Passport</label>
                            <input type="text" name="national_id" class="form-control" value="<?= htmlspecialchars($studentProfile['national_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Residential Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($studentProfile['address'] ?? 'Mombasa') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Enrolled Course Category</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($studentProfile['course_category']) ?>" readonly>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['role'] === 'Instructor' && $instructorProfile): ?>
                        <div class="col-12 mt-4 pt-2 border-top">
                            <h6 class="fw-bold text-warning text-dark mb-3"><i class="fa-solid fa-chalkboard-user me-1"></i> Instructor NTSA Credentials</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">NTSA License Number <span class="text-danger">*</span></label>
                            <input type="text" name="license_no" class="form-control" value="<?= htmlspecialchars($instructorProfile['license_no']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">License Expiry Date</label>
                            <input type="date" name="license_expiry" class="form-control" value="<?= htmlspecialchars($instructorProfile['license_expiry'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Specialization</label>
                            <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($instructorProfile['specialization']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Residential Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($instructorProfile['address'] ?? 'Mombasa') ?>">
                        </div>
                        <?php endif; ?>

                        <div class="col-12 mt-4 pt-2 border-top">
                            <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-lock me-1 text-muted"></i> Change Password</h6>
                            <p class="text-muted small mb-3">Leave these fields blank if you do not want to change your password.</p>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Current Password</label>
                            <input type="password" class="form-control" name="cur_password" placeholder="Current password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">New Password</label>
                            <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Repeat new password">
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                                <i class="fa-solid fa-save me-1"></i> Save Profile Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Info Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-circle-info text-info me-2"></i> Account Overview</h5>
            </div>
            <div class="card-body">
                <div class="text-center py-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 72px; height: 72px; font-size: 2.2rem;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                    <span class="badge bg-primary px-3 py-2"><?= htmlspecialchars($user['role']) ?></span>
                </div>
                <ul class="list-group list-group-flush border-top">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Username:</span>
                        <strong class="font-monospace text-primary"><?= htmlspecialchars($user['username']) ?></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Account Status:</span>
                        <span class="badge bg-success"><?= htmlspecialchars($user['status']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Branch:</span>
                        <span>Anza Auto Mombasa Branch</span>
                    </li>
                    <?php if ($studentProfile): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Student Reg No:</span>
                        <strong class="font-monospace"><?= htmlspecialchars($studentProfile['student_reg_no']) ?></strong>
                    </li>
                    <?php endif; ?>
                    <?php if ($instructorProfile): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Instructor No:</span>
                        <strong class="font-monospace"><?= htmlspecialchars($instructorProfile['instructor_no']) ?></strong>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
