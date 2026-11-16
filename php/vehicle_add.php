<?php
$pageTitle = 'Add Training Vehicle';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_no = strtoupper(trim($_POST['reg_no'] ?? ''));
    $model = trim($_POST['model'] ?? '');
    $transmission = trim($_POST['transmission'] ?? 'Manual');
    $category = trim($_POST['category'] ?? 'Class B (Light Vehicle)');
    $status = trim($_POST['status'] ?? 'Available');

    if (empty($reg_no) || empty($model)) {
        $error = 'Vehicle Registration Number and Model are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicles (reg_no, model, transmission, category, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reg_no, $model, $transmission, $category, $status]);
            set_flash('success', "Vehicle $reg_no added successfully!");
            redirect('vehicles.php');
        } catch (Exception $e) {
            $error = 'Error adding vehicle: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="vehicles.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Fleet</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-car text-primary me-2"></i> Register New Training Vehicle</h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="vehicle_add.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Plate No. <span class="text-danger">*</span></label>
                    <input type="text" class="form-control text-uppercase" name="reg_no" placeholder="e.g. KDE 312M" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Model & Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="model" placeholder="e.g. Toyota Vitz (Dual Pedal Control)" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Transmission</label>
                    <select name="transmission" class="form-select">
                        <option value="Manual">Manual</option>
                        <option value="Automatic">Automatic</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Category</label>
                    <input type="text" class="form-control" name="category" value="Class B (Light Vehicle)">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Available">Available</option>
                        <option value="In Use">In Use</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="col-12 text-end mt-4">
                    <a href="vehicles.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Vehicle</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
