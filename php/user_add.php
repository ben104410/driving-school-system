<?php
$pageTitle = 'Add System User';
require_once __DIR__ . '/includes/header.php';
require_role('Administrator');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $role = trim($_POST['role'] ?? 'Receptionist');
    $status = trim($_POST['status'] ?? 'Active');

    if (empty($username) || empty($password) || empty($full_name)) {
        $error = 'Username, Password, and Full Name are required.';
    } else {
        // Check if username taken
        $stmtChk = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmtChk->execute([$username]);
        if ($stmtChk->fetch()) {
            $error = "Username '$username' is already registered. Please choose another.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $full_name, $email, $phone, $role, $status]);

                set_flash('success', "User account '$username' created successfully!");
                redirect('users.php');
            } catch (Exception $e) {
                $error = 'Error adding user: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="mb-4">
    <a href="users.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Users</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Create System User Account</h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="user_add.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="username" placeholder="e.g. jdoe" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password" placeholder="Enter secure password" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" placeholder="e.g. John Doe" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" name="email" placeholder="e.g. user@anzaautodrivingschool.co.ke">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" class="form-control" name="phone" placeholder="e.g. +254712345678">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">User Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="Administrator">Administrator (Full Access)</option>
                        <option value="Receptionist" selected>Receptionist (Front Desk, Registration, Fees)</option>
                        <option value="Instructor">Instructor (Schedules, Assessments)</option>
                        <option value="Student">Student (Portal Access)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Status</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="users.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Create Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
