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

    // Handle photo file upload
    $photoPath = null;
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
            $stmt = $pdo->prepare("INSERT INTO vehicles (reg_no, model, transmission, category, status, photo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$reg_no, $model, $transmission, $category, $status, $photoPath]);
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
        <form method="POST" action="vehicle_add.php" enctype="multipart/form-data">
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

                <!-- Vehicle Photo Upload with Preview -->
                <div class="col-12">
                    <label class="form-label fw-semibold"><i class="fa-solid fa-camera me-1 text-primary"></i> Vehicle Photo (Choose File)</label>
                    <div class="input-group">
                        <input type="file" class="form-control" name="vehicle_photo" id="vehicle_photo" accept="image/*" onchange="previewVehiclePhoto(this)">
                    </div>
                    <small class="text-muted">Upload an image file (JPG, PNG, WebP) of the training car or motorbike.</small>
                    <div id="photo_preview_box" class="mt-2" style="display: none;">
                        <img id="photo_preview" src="" alt="Vehicle Photo Preview" class="rounded border shadow-sm" style="max-height: 160px; max-width: 240px; object-fit: cover;">
                    </div>
                </div>

                <div class="col-12 text-end mt-4">
                    <a href="vehicles.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Vehicle</button>
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
