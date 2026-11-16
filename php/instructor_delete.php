<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$insId = (int)($_GET['id'] ?? 0);
if ($insId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM instructors WHERE instructor_id = ?");
        $stmt->execute([$insId]);
        set_flash('success', 'Instructor record deleted successfully.');
    } catch (Exception $e) {
        set_flash('danger', 'Unable to delete instructor: ' . $e->getMessage());
    }
} else {
    set_flash('danger', 'Invalid instructor ID.');
}

redirect('instructors.php');
?>
