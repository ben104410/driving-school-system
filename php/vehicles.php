<?php
$pageTitle = 'Vehicle Fleet Management';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$stmt = $pdo->query("SELECT * FROM vehicles ORDER BY vehicle_id ASC");
$vehicles = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-car text-primary me-2"></i> Training Vehicles (Fleet)</h3>
        <p class="text-muted mb-0">Dual-pedal training cars and motorbikes at Anza Auto Driving School, Mombasa.</p>
    </div>
    <a href="vehicle_add.php" class="btn btn-primary shadow-sm">
        <i class="fa-solid fa-plus me-1"></i> Add New Vehicle
    </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">Photo</th>
                        <th>Registration No</th>
                        <th>Vehicle Model</th>
                        <th>Transmission</th>
                        <th>Vehicle Category</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No vehicles registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($v['photo'])): ?>
                                        <img src="<?= htmlspecialchars($v['photo']) ?>" alt="Vehicle Photo" class="rounded border shadow-sm" style="width: 56px; height: 42px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted shadow-sm" style="width: 56px; height: 42px;">
                                            <i class="fa-solid fa-car fs-5 text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-primary border font-monospace fs-6"><?= htmlspecialchars($v['reg_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($v['model']) ?></td>
                                <td>
                                    <span class="badge bg-<?= ($v['transmission'] === 'Manual') ? 'primary' : 'info text-dark' ?>">
                                        <?= htmlspecialchars($v['transmission']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($v['category']) ?></td>
                                <td>
                                    <?php
                                    $vBadge = [
                                        'Available' => 'success',
                                        'In Use' => 'warning text-dark',
                                        'Maintenance' => 'danger'
                                    ][$v['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $vBadge ?>"><?= htmlspecialchars($v['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="vehicle_edit.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline-secondary" title="Edit Vehicle">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if (has_role('Administrator')): ?>
                                        <a href="vehicle_delete.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this vehicle?')" title="Delete Vehicle">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
