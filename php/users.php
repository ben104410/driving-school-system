<?php
$pageTitle = 'System Users';
require_once __DIR__ . '/includes/header.php';
require_role('Administrator');

$users = $pdo->query("SELECT * FROM users ORDER BY user_id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-users-gear text-primary me-2"></i> System User Accounts</h3>
        <p class="text-muted mb-0">Role-based access control for Administrators, Receptionists, Instructors, and Students.</p>
    </div>
    <a href="user_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-user-plus me-1"></i> Add New User
    </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email / Phone</th>
                        <th>Role</th>
                        <th>Account Status</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="font-monospace">#<?= $u['user_id'] ?></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($u['email'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($u['phone'] ?? '-') ?></small>
                            </td>
                            <td>
                                <?php
                                $rColors = [
                                    'Administrator' => 'danger',
                                    'Receptionist' => 'primary',
                                    'Instructor' => 'warning text-dark',
                                    'Student' => 'success'
                                ];
                                $bColor = $rColors[$u['role']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $bColor ?>"><?= htmlspecialchars($u['role']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($u['status'] === 'Active') ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars($u['status']) ?>
                                </span>
                            </td>
                            <td><small class="text-muted"><?= format_date($u['created_at']) ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="user_edit.php?id=<?= $u['user_id'] ?>" class="btn btn-outline-secondary" title="Edit User">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                    </a>
                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="user_delete.php?id=<?= $u['user_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Permanently delete user account <?= htmlspecialchars(addslashes($u['username'])) ?> (<?= htmlspecialchars($u['role']) ?>)? This cannot be undone.')" title="Delete User">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="btn btn-outline-secondary disabled" title="Current Login"><i class="fa-solid fa-user-lock"></i></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
