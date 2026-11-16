<?php
$pageTitle = 'Add Driving Instructor';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$insNo = generate_instructor_no($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Male');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');
    $license_expiry = trim($_POST['license_expiry'] ?? '') ?: null;
    $specialization = trim($_POST['specialization'] ?? 'Manual & Automatic Light Vehicles');
    $address = trim($_POST['address'] ?? 'Mombasa');
    $status = trim($_POST['status'] ?? 'Active');

    $create_account = isset($_POST['create_account']);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? 'password123');

    if (empty($full_name) || empty($phone) || empty($email) || empty($license_no)) {
        $error = 'Full Name, Phone Number, Email, and Instructor License No are required.';
    } else {
        try {
            $pdo->beginTransaction();

            $userId = null;
            if ($create_account) {
                if (empty($username)) {
                    $username = 'inst_' . strtolower(explode(' ', $full_name)[0]);
                }
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'Instructor', 'Active')");
                $stmtUser->execute([$username, $hashed, $full_name, $email, $phone]);
                $userId = $pdo->lastInsertId();
            }

            $stmtIns = $pdo->prepare("
                INSERT INTO instructors (user_id, instructor_no, full_name, gender, phone, email, license_no, license_expiry, specialization, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$userId, $insNo, $full_name, $gender, $phone, $email, $license_no, $license_expiry, $specialization, $address, $status]);

            $pdo->commit();

            set_flash('success', "Instructor $full_name added successfully with No: $insNo!");
            redirect('instructors.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adding instructor: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="instructors.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Instructors</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Instructor</h3>
    <p class="text-muted mb-0">Record licensed driving instructor credentials and assign login rights.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="instructor_add.php">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-id-card text-primary me-2"></i> Instructor Credentials</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Instructor Number</label>
                            <input type="text" class="form-control bg-light font-monospace fw-bold" name="ins_no" value="<?= htmlspecialchars($insNo) ?>" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" placeholder="e.g. David Ochieng" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" placeholder="e.g. +254712345678" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="e.g. instructor@aadrivingschool.co.ke" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NTSA Instructor License No. <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="license_no" placeholder="e.g. NTSA/DI/2022/482" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">License Expiry Date</label>
                            <input type="date" class="form-control" name="license_expiry">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Training Specialization</label>
                            <input type="text" class="form-control" name="specialization" placeholder="e.g. Class B (Light Vehicle) & Defensive Driving" value="Class B (Light Manual & Automatic)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employment Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Residential Address</label>
                            <input type="text" class="form-control" name="address" placeholder="e.g. Nyali, Mombasa" value="Mombasa">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-key text-warning me-2"></i> Portal Access</h5>
                </div>
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="create_account" id="create_account" checked>
                        <label class="form-check-label fw-semibold" for="create_account">Create Login for Instructor</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="Leave blank for auto-generate">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Default Password</label>
                        <input type="text" class="form-control" name="password" value="password123">
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="fa-solid fa-save me-1"></i> Save Instructor
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
