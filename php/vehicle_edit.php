<?php
$pageTitle = 'Edit Training Vehicle';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$vehicleId = (int)($_GET['id'] ?? 0);
if (!$vehicleId) {
    set_flash('danger', 'Invalid vehicle ID.');
    redirect('vehicles.php');
}

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    set_flash('danger', 'Vehicle not found.');
    redirect('vehicles.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_no = strtoupper(trim($_POST['reg_no'] ?? ''));
    $model = trim($_POST['model'] ?? '');
    $transmission = trim($_POST['transmission'] ?? 'Manual');
    $category = trim($_POST['category'] ?? 'Class B (Light Vehicle)');
    $status = trim($_POST['status'] ?? 'Available');

    // Handle photo file upload
    $photoPath = $vehicle['photo'] ?? null;
    if (isset($_FILES['vehicle_photo']) && $_FILES['vehicle_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/uploads/vehicles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileInfo = pathinfo($_FILES['vehicle_photo']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'veh_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['vehicle_photo']['tmp_name'], $targetPath)) {
                $photoPath = 'assets/uploads/vehicles/' . $filename;
            }
        }
    }

    if (empty($reg_no) || empty($model)) {
        $error = 'Vehicle Registration Number and Model are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE vehicles SET reg_no = ?, model = ?, transmission = ?, category = ?, status = ?, photo = ? WHERE vehicle_id = ?");
            $stmt->execute([$reg_no, $model, $transmission, $category, $status, $photoPath, $vehicleId]);
            set_flash('success', "Vehicle $reg_no updated successfully!");
            redirect('vehicles.php');
        } catch (Exception $e) {
            $error = 'Error updating vehicle: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="vehicles.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Fleet</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-car text-primary me-2"></i> Edit Vehicle: <?= htmlspecialchars($vehicle['reg_no']) ?></h3>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="vehicle_edit.php?id=<?= $vehicleId ?>" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Plate No. <span class="text-danger">*</span></label>
                    <input type="text" class="form-control text-uppercase" name="reg_no" value="<?= htmlspecialchars($vehicle['reg_no']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Model & Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="model" value="<?= htmlspecialchars($vehicle['model']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Transmission</label>
                    <select name="transmission" class="form-select">
                        <option value="Manual" <?= ($vehicle['transmission'] === 'Manual') ? 'selected' : '' ?>>Manual</option>
                        <option value="Automatic" <?= ($vehicle['transmission'] === 'Automatic') ? 'selected' : '' ?>>Automatic</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Category</label>
                    <input type="text" class="form-control" name="category" value="<?= htmlspecialchars($vehicle['category']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Available" <?= ($vehicle['status'] === 'Available') ? 'selected' : '' ?>>Available</option>
                        <option value="In Use" <?= ($vehicle['status'] === 'In Use') ? 'selected' : '' ?>>In Use</option>
                        <option value="Maintenance" <?= ($vehicle['status'] === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                    </select>
                </div>

                <!-- Vehicle Photo Field -->
                <div class="col-12">
                    <label class="form-label fw-semibold"><i class="fa-solid fa-camera me-1 text-primary"></i> Vehicle Photo (Choose File)</label>
                    <?php if (!empty($vehicle['photo'])): ?>
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">Current Photo:</small>
                        <img src="<?= htmlspecialchars($vehicle['photo']) ?>" alt="Current Vehicle Photo" class="rounded border shadow-sm" style="max-height: 120px; max-width: 180px; object-fit: cover;">
                    </div>
                    <?php endif; ?>
                    <div class="input-group">
                        <input type="file" class="form-control" name="vehicle_photo" id="vehicle_photo" accept="image/*" onchange="previewVehiclePhoto(this)">
                    </div>
                    <small class="text-muted">Choose a new file to change the current photo, or leave blank to keep existing.</small>
                    <div id="photo_preview_box" class="mt-2" style="display: none;">
                        <small class="text-muted d-block mb-1">New Photo Preview:</small>
                        <img id="photo_preview" src="" alt="Vehicle Photo Preview" class="rounded border shadow-sm" style="max-height: 160px; max-width: 240px; object-fit: cover;">
                    </div>
                </div>

                <div class="col-12 text-end mt-4">
                    <a href="vehicles.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function previewVehiclePhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photo_preview').src = e.target.result;
            document.getElementById('photo_preview_box').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
