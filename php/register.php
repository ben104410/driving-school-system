<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$role = $_POST['role'] ?? 'Student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $role = trim($_POST['role'] ?? 'Student');

    // Role-specific inputs
    $gender = trim($_POST['gender'] ?? 'Male');
    $dob = trim($_POST['date_of_birth'] ?? '') ?: null;
    $national_id = trim($_POST['national_id'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? 'Mombasa');
    $course_category = trim($_POST['course_category'] ?? 'Category B (Light Vehicle - Manual)');

    // Instructor inputs
    $license_no = trim($_POST['license_no'] ?? '');
    $license_expiry = trim($_POST['license_expiry'] ?? '') ?: null;
    $specialization = trim($_POST['specialization'] ?? 'Class B (Light Manual & Automatic)');

    // Admin security key (default allows instant registration with key or easy setup)
    $admin_code = trim($_POST['admin_code'] ?? '');

    // Validations
    if (empty($full_name) || empty($username) || empty($password) || empty($phone)) {
        $error = 'Full Name, Username, Password, and Phone Number are required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and Confirm Password do not match.';
    } elseif ($role === 'Administrator' && !empty($admin_code) && $admin_code !== 'AA-ADMIN-2026') {
        $error = 'Invalid Administrator Passcode. Use code: AA-ADMIN-2026';
    } elseif ($role === 'Instructor' && empty($license_no)) {
        $error = 'NTSA Instructor License Number is required for Instructor registration.';
    } else {
        // Check if username already exists
        $stmtChk = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmtChk->execute([$username]);
        if ($stmtChk->fetch()) {
            $error = "Username '$username' is already taken. Please choose another.";
        } else {
            try {
                $pdo->beginTransaction();

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                $stmtUser->execute([$username, $hashed, $full_name, $email, $phone, $role]);
                $userId = $pdo->lastInsertId();

                // If signing up as Student: create student profile
                if ($role === 'Student') {
                    $regNo = generate_student_reg_no($pdo);
                    $feeMap = [
                        'Category B (Light Vehicle - Manual)' => 15000,
                        'Category B (Light Vehicle - Automatic)' => 16000,
                        'Category A (Motorcycle)' => 9500,
                        'Category C1 (Light Truck)' => 18500,
                        'Refresher Course' => 8000
                    ];
                    $courseFee = $feeMap[$course_category] ?? 15000;

                    $stmtStudent = $pdo->prepare("
                        INSERT INTO students (user_id, student_reg_no, full_name, gender, date_of_birth, phone, email, national_id, address, course_category, course_fee, registration_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Enrolled')
                    ");
                    $stmtStudent->execute([$userId, $regNo, $full_name, $gender, $dob, $phone, $email, $national_id, $address, $course_category, $courseFee]);
                }

                // If signing up as Instructor: create instructor profile
                if ($role === 'Instructor') {
                    $insNo = generate_instructor_no($pdo);
                    $stmtIns = $pdo->prepare("
                        INSERT INTO instructors (user_id, instructor_no, full_name, gender, phone, email, license_no, license_expiry, specialization, address, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                    ");
                    $stmtIns->execute([$userId, $insNo, $full_name, $gender, $phone, $email, $license_no, $license_expiry, $specialization, $address]);
                }

                $pdo->commit();

                // Automatically log in the user
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;

                set_flash('success', "Welcome to Anza Auto Driving School, $full_name! Your $role account has been registered successfully.");
                redirect('dashboard.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Registration error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an Account | Anza Auto Driving School - Mombasa</title>
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
            padding: 2rem 1rem;
        }
        .signup-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 680px;
            overflow: hidden;
        }
        .signup-header {
            background: #07213a;
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .brand-badge {
            width: 54px;
            height: 54px;
            background: #ffffff;
            color: #0d3b66;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .role-btn-group input[type="radio"]:checked + label {
            background-color: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="signup-card">
    <div class="signup-header">
        <div class="brand-badge"><i class="fa-solid fa-car-side"></i></div>
        <h4 class="fw-bold mb-1">Anza Auto Driving School</h4>
        <div class="text-white-50 small text-uppercase">Mombasa Branch &bull; Online Registration Portal</div>
    </div>

    <div class="p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-user-plus text-primary me-2"></i> Create an Account</h5>
                <small class="text-muted">Register as a Student, Instructor, Receptionist, or Admin</small>
            </div>
            <a href="login.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="signupForm">
            <!-- Role Selection -->
            <div class="mb-4">
                <label class="form-label fw-bold small text-uppercase text-muted">Select Your Role / Purpose <span class="text-danger">*</span></label>
                <div class="btn-group w-100 role-btn-group" role="group">
                    <input type="radio" class="btn-check" name="role" id="role_student" value="Student" <?= ($role === 'Student') ? 'checked' : '' ?> onchange="toggleRoleFields('Student')">
                    <label class="btn btn-outline-primary py-2" for="role_student">
                        <i class="fa-solid fa-user-graduate d-block mb-1 fs-5"></i> Student
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_instructor" value="Instructor" <?= ($role === 'Instructor') ? 'checked' : '' ?> onchange="toggleRoleFields('Instructor')">
                    <label class="btn btn-outline-primary py-2" for="role_instructor">
                        <i class="fa-solid fa-chalkboard-user d-block mb-1 fs-5"></i> Instructor
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_receptionist" value="Receptionist" <?= ($role === 'Receptionist') ? 'checked' : '' ?> onchange="toggleRoleFields('Receptionist')">
                    <label class="btn btn-outline-primary py-2" for="role_receptionist">
                        <i class="fa-solid fa-clipboard-user d-block mb-1 fs-5"></i> Receptionist
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_admin" value="Administrator" <?= ($role === 'Administrator') ? 'checked' : '' ?> onchange="toggleRoleFields('Administrator')">
                    <label class="btn btn-outline-primary py-2" for="role_admin">
                        <i class="fa-solid fa-shield-halved d-block mb-1 fs-5"></i> Admin
                    </label>
                </div>
            </div>

            <!-- Common Basic Info -->
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label fw-semibold small">Full Legal Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Cynthia Jeruto" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. cynthia" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +254712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. student@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <!-- Student Specific Fields -->
            <div id="student_fields" class="p-3 bg-light rounded-3 border mb-3">
                <div class="fw-bold text-primary small mb-2"><i class="fa-solid fa-id-card me-1"></i> Learner Course & Bio Data</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Gender</label>
                        <select name="gender" class="form-select form-select-sm">
                            <option value="Male">Male</option>
                            <option value="Female" selected>Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">National ID / Passport</label>
                        <input type="text" name="national_id" class="form-control form-control-sm" placeholder="e.g. 38491024" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Driving Course Category <span class="text-danger">*</span></label>
                        <select name="course_category" class="form-select form-select-sm">
                            <option value="Category B (Light Vehicle - Manual)">Category B (Light Vehicle - Manual) - KES 15,000</option>
                            <option value="Category B (Light Vehicle - Automatic)">Category B (Light Vehicle - Automatic) - KES 16,000</option>
                            <option value="Category A (Motorcycle)">Category A (Motorcycle) - KES 9,500</option>
                            <option value="Category C1 (Light Truck)">Category C1 (Light Truck) - KES 18,500</option>
                            <option value="Refresher Course">Refresher Course (10 Lessons) - KES 8,000</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Residential Area</label>
                        <input type="text" name="address" class="form-control form-control-sm" placeholder="e.g. Nyali, Mombasa" value="Mombasa">
                    </div>
                </div>
            </div>

            <!-- Instructor Specific Fields -->
            <div id="instructor_fields" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                <div class="fw-bold text-warning text-dark small mb-2"><i class="fa-solid fa-id-badge me-1"></i> Instructor NTSA Credentials</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">NTSA License Number <span class="text-danger">*</span></label>
                        <input type="text" name="license_no" class="form-control form-control-sm" placeholder="e.g. NTSA/DI/2024/701" value="<?= htmlspecialchars($_POST['license_no'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">License Expiry Date</label>
                        <input type="date" name="license_expiry" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['license_expiry'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Specialization</label>
                        <input type="text" name="specialization" class="form-control form-control-sm" value="Class B (Light Manual & Automatic)">
                    </div>
                </div>
            </div>

            <!-- Admin Specific Fields -->
            <div id="admin_fields" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                <div class="fw-bold text-danger small mb-2"><i class="fa-solid fa-key me-1"></i> Administrator Authorization</div>
                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold small">Admin Security Passcode</label>
                        <input type="text" name="admin_code" class="form-control form-control-sm" placeholder="Default code: AA-ADMIN-2026" value="AA-ADMIN-2026">
                        <small class="text-muted" style="font-size: 0.75rem;">Authorization passcode configured for academic defense: <code>AA-ADMIN-2026</code></small>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mt-3" style="background-color: #0d3b66; border-color: #0d3b66;">
                <i class="fa-solid fa-user-check me-2"></i> Complete Sign Up & Enter Portal
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">Already have an account?</span>
            <a href="login.php" class="fw-bold text-primary text-decoration-none ms-1">Log In Here</a>
        </div>
    </div>
</div>

<script>
function toggleRoleFields(role) {
    document.getElementById('student_fields').style.display = (role === 'Student') ? 'block' : 'none';
    document.getElementById('instructor_fields').style.display = (role === 'Instructor') ? 'block' : 'none';
    document.getElementById('admin_fields').style.display = (role === 'Administrator') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="role"]:checked');
    if (checked) {
        toggleRoleFields(checked.value);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
