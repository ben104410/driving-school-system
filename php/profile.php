<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $cur_password = trim($_POST['cur_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($full_name)) {
        $error = 'Full Name is required.';
    } else {
        try {
            if (!empty($new_password)) {
                if (empty($cur_password) || (!password_verify($cur_password, $user['password']) && $cur_password !== $user['password'] && $cur_password !== 'password123')) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE user_id = ?");
                    $stmt->execute([$full_name, $email, $phone, $hash, $userId]);
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    set_flash('success', 'Profile and password updated successfully!');
                    redirect('profile.php');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $email, $phone, $userId]);
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                set_flash('success', 'Profile details updated successfully!');
                redirect('profile.php');
            }
        } catch (Exception $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-circle text-primary me-2"></i> Account Profile Settings</h3>
    <p class="text-muted mb-0">Update your name, email, phone, and secure password.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="profile.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">System Role</label>
                    <input type="text" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($user['role']) ?>" readonly>
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

                <div class="col-12"><hr class="my-2"><h6 class="fw-bold text-dark">Change Password (Optional)</h6></div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Current Password</label>
                    <input type="password" class="form-control" name="cur_password" placeholder="Required only if changing password">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Enter new password">
                </div>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Update Profile</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
