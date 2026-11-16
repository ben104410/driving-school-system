<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$vehicleId = (int)($_GET['id'] ?? 0);
if ($vehicleId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([$vehicleId]);
        set_flash('success', 'Vehicle record deleted successfully.');
    } catch (Exception $e) {
        set_flash('danger', 'Unable to delete vehicle: ' . $e->getMessage());
    }
} else {
    set_flash('danger', 'Invalid vehicle ID.');
}

redirect('vehicles.php');
?>
