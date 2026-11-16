<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$insId = (int)($_GET['id'] ?? 0);
if ($insId) {
    try {
        $pdo->beginTransaction();

        $stmtFetch = $pdo->prepare("SELECT full_name, instructor_no, user_id FROM instructors WHERE instructor_id = ?");
        $stmtFetch->execute([$insId]);
        $instructor = $stmtFetch->fetch();

        if ($instructor) {
            $stmt = $pdo->prepare("DELETE FROM instructors WHERE instructor_id = ?");
            $stmt->execute([$insId]);

            // If user login account exists, delete it too
            if (!empty($instructor['user_id'])) {
                $stmtUserDel = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmtUserDel->execute([$instructor['user_id']]);
            }

            $pdo->commit();
            set_flash('success', "Instructor {$instructor['full_name']} ({$instructor['instructor_no']}) was permanently deleted from the database.");
        } else {
            $pdo->rollBack();
            set_flash('danger', 'Instructor record not found in the database.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Unable to delete instructor: ' . $e->getMessage());
    }
} else {
    set_flash('danger', 'Invalid instructor ID.');
}

redirect('instructors.php');
?>
