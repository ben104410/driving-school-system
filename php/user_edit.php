<?php
$pageTitle = 'Edit User Account';
require_once __DIR__ . '/includes/header.php';
require_role('Administrator');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    set_flash('danger', 'Invalid user ID.');
    redirect('users.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'User not found.');
    redirect('users.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $role = trim($_POST['role'] ?? 'Receptionist');
    $status = trim($_POST['status'] ?? 'Active');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($full_name)) {
        $error = 'Full Name is required.';
    } else {
        try {
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $email, $phone, $role, $status, $hash, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $email, $phone, $role, $status, $userId]);
            }

            set_flash('success', "User account updated successfully!");
            redirect('users.php');
        } catch (Exception $e) {
            $error = 'Error updating user: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="users.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Users</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit User: <?= htmlspecialchars($user['username']) ?></h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="user_edit.php?id=<?= $userId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reset Password (leave blank to keep current)</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Enter new password if changing">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">User Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="Administrator" <?= ($user['role'] === 'Administrator') ? 'selected' : '' ?>>Administrator</option>
                        <option value="Receptionist" <?= ($user['role'] === 'Receptionist') ? 'selected' : '' ?>>Receptionist</option>
                        <option value="Instructor" <?= ($user['role'] === 'Instructor') ? 'selected' : '' ?>>Instructor</option>
                        <option value="Student" <?= ($user['role'] === 'Student') ? 'selected' : '' ?>>Student</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" <?= ($user['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($user['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="users.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
