<?php
// =============================================================
// Helper Functions
// =============================================================

require_once __DIR__ . '/../config/db.php';

function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function display_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($flash['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash']);
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'email' => $_SESSION['email'] ?? ''
    ];
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to access this page.');
        redirect('login.php');
    }
}

function has_role($roles) {
    if (!is_logged_in()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['role'], $roles, true);
}

function require_role($roles) {
    require_login();
    if (!has_role($roles)) {
        set_flash('danger', 'Access denied. You do not have permission to view this resource.');
        redirect('dashboard.php');
    }
}

function format_currency($amount) {
    return CURRENCY . ' ' . number_format((float)$amount, 2);
}

function format_date($date, $format = 'd M, Y') {
    if (!$date || $date === '0000-00-00') return 'N/A';
    $time = strtotime($date);
    return $time ? date($format, $time) : $date;
}

function get_student_balance($student_id, $pdo) {
    $stmt = $pdo->prepare("SELECT course_fee FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) return 0;

    $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE student_id = ?");
    $stmt2->execute([$student_id]);
    $paid = $stmt2->fetchColumn();

    $fee = (float)$student['course_fee'];
    $paid = (float)$paid;
    $balance = $fee - $paid;

    return [
        'course_fee' => $fee,
        'total_paid' => $paid,
        'balance' => $balance,
        'is_cleared' => ($balance <= 0)
    ];
}

function generate_receipt_no($pdo) {
    $stmt = $pdo->query("SELECT MAX(payment_id) FROM payments");
    $next = ((int)$stmt->fetchColumn()) + 1;
    return 'RCT-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function generate_student_reg_no($pdo) {
    $stmt = $pdo->query("SELECT MAX(student_id) FROM students");
    $next = ((int)$stmt->fetchColumn()) + 1;
    return 'AA/MBA/' . date('Y') . '/' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

function generate_instructor_no($pdo) {
    $stmt = $pdo->query("SELECT MAX(instructor_id) FROM instructors");
    $next = ((int)$stmt->fetchColumn()) + 1;
    return 'AA-INS-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}
?>
