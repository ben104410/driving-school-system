<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        set_flash('success', 'Student record deleted successfully.');
    } catch (Exception $e) {
        set_flash('danger', 'Unable to delete student: ' . $e->getMessage());
    }
} else {
    set_flash('danger', 'Invalid student ID.');
}

redirect('students.php');
?>
