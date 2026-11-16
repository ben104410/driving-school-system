<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId) {
    try {
        $pdo->beginTransaction();

        $stmtFetch = $pdo->prepare("SELECT full_name, student_reg_no, user_id FROM students WHERE student_id = ?");
        $stmtFetch->execute([$studentId]);
        $student = $stmtFetch->fetch();

        if ($student) {
            // Delete student record (cascade deletes payments, schedules, test results)
            $stmtDel = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmtDel->execute([$studentId]);

            // If user login account exists, delete it too
            if (!empty($student['user_id'])) {
                $stmtUserDel = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmtUserDel->execute([$student['user_id']]);
            }

            $pdo->commit();
            set_flash('success', "Student {$student['full_name']} ({$student['student_reg_no']}) was permanently deleted from the database.");
        } else {
            $pdo->rollBack();
            set_flash('danger', 'Student record not found in the database.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Unable to delete student: ' . $e->getMessage());
    }
} else {
    set_flash('danger', 'Invalid student ID.');
}

redirect('students.php');
?>
