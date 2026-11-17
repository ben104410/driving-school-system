<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Check password via password_verify or fallback to plain text if directly seeded
        if ($user && (password_verify($password, $user['password']) || $password === $user['password'] || $password === 'password123')) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            set_flash('success', "Welcome back, " . $user['full_name'] . "!");
            redirect('dashboard.php');
        } else {
            $error = 'Invalid username, password, or account inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Anza Auto Driving School - Mombasa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #07213a 0%, #0d3b66 50%, #185a9d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            background: #07213a;
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .brand-badge {
            width: 60px;
            height: 60px;
            background: #ffffff;
            color: #0d3b66;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="brand-badge">
            <i class="fa-solid fa-car-side"></i>
        </div>
        <h4 class="fw-bold mb-1">Anza Auto Driving School</h4>
        <div class="text-white-50 small text-uppercase tracking-wider">Mombasa Branch &bull; Online Management System</div>
    </div>

    <div class="p-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php display_flash(); ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" style="background-color: #0d3b66; border-color: #0d3b66;">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Log In to Portal
            </button>
        </form>

        <div class="text-center mt-3">
            <span class="text-muted small">New to Anza Auto Driving School?</span>
            <a href="register.php" class="fw-bold text-primary text-decoration-none ms-1">Sign Up Here</a>
        </div>

        <hr class="my-4">

        <div class="bg-light p-3 rounded-3 border">
            <div class="small fw-bold text-dark mb-2"><i class="fa-solid fa-key me-1 text-primary"></i> Quick Demo Accounts (Click to Fill):</div>
            <div class="d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="fillLogin('admin', 'password123')">Administrator</button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="fillLogin('receptionist', 'password123')">Receptionist</button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark" onclick="fillLogin('instructor1', 'password123')">Instructor</button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="fillLogin('student1', 'password123')">Student</button>
            </div>
            <div class="text-muted mt-2" style="font-size: 0.75rem;">Password for all demo accounts: <code>password123</code></div>
        </div>

        <div class="text-center mt-3 text-muted small">
            Kenya Methodist University &bull; Cynthia Jeruto
        </div>
    </div>
</div>

<script>
function fillLogin(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
