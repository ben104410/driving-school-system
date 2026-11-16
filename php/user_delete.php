<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_role('Administrator');

$userId = (int)($_GET['id'] ?? 0);

if ($userId) {
    if ($userId === (int)$_SESSION['user_id']) {
        set_flash('danger', 'Security Restriction: You cannot delete your own currently logged-in administrator account.');
    } else {
        try {
            $stmtFetch = $pdo->prepare("SELECT username, full_name, role FROM users WHERE user_id = ?");
            $stmtFetch->execute([$userId]);
            $user = $stmtFetch->fetch();

            if ($user) {
                // Unlink student or instructor record if any
                $pdo->prepare("UPDATE students SET user_id = NULL WHERE user_id = ?")->execute([$userId]);
                $pdo->prepare("UPDATE instructors SET user_id = NULL WHERE user_id = ?")->execute([$userId]);

                // Delete the user
                $stmtDel = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmtDel->execute([$userId]);

                set_flash('success', "User account {$user['username']} ({$user['full_name']} - {$user['role']}) has been permanently deleted from the database.");
            } else {
                set_flash('danger', 'User account not found.');
            }
        } catch (Exception $e) {
            set_flash('danger', 'Unable to delete user: ' . $e->getMessage());
        }
    }
} else {
    set_flash('danger', 'Invalid user ID.');
}

redirect('users.php');
?>
