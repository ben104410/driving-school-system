<?php
$pageTitle = 'Register New Student';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$regNo = generate_student_reg_no($pdo);
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
    $reg_date = trim($_POST['registration_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'Enrolled');

    $initial_payment = (float)($_POST['initial_payment'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? 'M-Pesa');
    $ref_no = trim($_POST['reference_no'] ?? '');

    $create_account = isset($_POST['create_account']);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? 'password123');

    if (empty($full_name) || empty($phone) || empty($course_category)) {
        $error = 'Full Name, Phone Number, and Course Category are required.';
    } else {
        try {
            $pdo->beginTransaction();

            $userId = null;
            if ($create_account) {
                if (empty($username)) {
                    $username = strtolower(explode(' ', $full_name)[0]) . rand(100, 999);
                }
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'Student', 'Active')");
                $stmtUser->execute([$username, $hashed, $full_name, $email, $phone]);
                $userId = $pdo->lastInsertId();
            }

            $stmtStudent = $pdo->prepare("
                INSERT INTO students (user_id, student_reg_no, full_name, gender, date_of_birth, phone, email, national_id, address, course_category, course_fee, registration_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtStudent->execute([$userId, $regNo, $full_name, $gender, $dob, $phone, $email, $national_id, $address, $course_category, $course_fee, $reg_date, $status]);
            $studentId = $pdo->lastInsertId();

            // Record initial fee payment if provided
            if ($initial_payment > 0) {
                $receiptNo = generate_receipt_no($pdo);
                $stmtPay = $pdo->prepare("
                    INSERT INTO payments (receipt_no, student_id, amount, payment_date, payment_method, reference_no, notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'Initial deposit on registration', ?)
                ");
                $stmtPay->execute([$receiptNo, $studentId, $initial_payment, $reg_date, $payment_method, $ref_no, $_SESSION['user_id']]);
            }

            $pdo->commit();

            set_flash('success', "Student $full_name registered successfully with Reg No: $regNo!");
            redirect('student_view.php?id=' . $studentId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error registering student: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="students.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Student Directory</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Student</h3>
    <p class="text-muted mb-0">Enroll a new learner at AA Driving School, Mombasa Branch.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="student_add.php">
    <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Personal & Contact Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Registration Number</label>
                            <input type="text" class="form-control bg-light font-monospace fw-bold" name="reg_no" value="<?= htmlspecialchars($regNo) ?>" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" placeholder="e.g. Cynthia Jeruto" required>
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
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">National ID / Passport No.</label>
                            <input type="text" class="form-control" name="national_id" placeholder="e.g. 38491024">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" placeholder="e.g. +254712345678" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" placeholder="e.g. student@gmail.com">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Residential / Home Address</label>
                            <input type="text" class="form-control" name="address" placeholder="e.g. Nyali, Mombasa" value="Mombasa">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Initial Fee Payment -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Initial Tuition Deposit (Optional)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Deposit Amount (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="initial_payment" placeholder="e.g. 5000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reference Code / M-Pesa Code</label>
                            <input type="text" class="form-control" name="reference_no" placeholder="e.g. QA189KL93Z">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Details & Portal Account -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-car text-warning me-2"></i> Course Enrollment</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Driving Course Category <span class="text-danger">*</span></label>
                        <select name="course_category" id="course_category" class="form-select" onchange="updateFee()" required>
                            <option value="Category B (Light Vehicle - Manual)" data-fee="15000">Category B (Light Vehicle - Manual) - KES 15,000</option>
                            <option value="Category B (Light Vehicle - Automatic)" data-fee="16000">Category B (Light Vehicle - Automatic) - KES 16,000</option>
                            <option value="Category A (Motorcycle)" data-fee="9500">Category A (Motorcycle) - KES 9,500</option>
                            <option value="Category C1 (Light Truck)" data-fee="18500">Category C1 (Light Truck) - KES 18,500</option>
                            <option value="Refresher Course" data-fee="8000">Refresher Course (10 Lessons) - KES 8,000</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Agreed Tuition Fee (KES)</label>
                        <input type="number" step="0.01" class="form-control fw-bold" name="course_fee" id="course_fee" value="15000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enrollment Date</label>
                        <input type="date" class="form-control" name="registration_date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Training Status</label>
                        <select name="status" class="form-select">
                            <option value="Enrolled">Enrolled</option>
                            <option value="In Training">In Training</option>
                            <option value="Test Ready">Test Ready</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Student Portal Account Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-user-lock text-info me-2"></i> Student Portal Access</h5>
                </div>
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="create_account" id="create_account" checked onchange="toggleAccountFields()">
                        <label class="form-check-label fw-semibold" for="create_account">Create Student Login Account</label>
                    </div>
                    <div id="account_fields">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Portal Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Leave blank for auto-generate">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Temporary Password</label>
                            <input type="text" class="form-control" name="password" value="password123">
                            <small class="text-muted" style="font-size: 0.75rem;">Default: password123</small>
                        </div>
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                        <i class="fa-solid fa-save me-1"></i> Complete Registration
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function updateFee() {
    const sel = document.getElementById('course_category');
    const fee = sel.options[sel.selectedIndex].getAttribute('data-fee');
    if (fee) {
        document.getElementById('course_fee').value = fee;
    }
}
function toggleAccountFields() {
    const checked = document.getElementById('create_account').checked;
    document.getElementById('account_fields').style.display = checked ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
