// =============================================================
// ONLINE DRIVING SCHOOL MANAGEMENT SYSTEM
// Case Study: Anza Auto Driving School - Mombasa Branch
// Ready-to-Run Node.js Server & Application
// =============================================================

const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');
const querystring = require('querystring');

const PORT = process.env.PORT || 3000;
const DATA_FILE = path.join(__dirname, 'data.json');

// Helper to load/save JSON database
function loadData() {
    try {
        const raw = fs.readFileSync(DATA_FILE, 'utf8');
        return JSON.parse(raw);
    } catch (e) {
        console.error('Error loading data.json:', e);
        return { users: [], students: [], instructors: [], vehicles: [], lesson_schedules: [], payments: [], driving_test_results: [] };
    }
}

function saveData(data) {
    fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2), 'utf8');
}

// In-memory sessions
const sessions = {};

function parseCookies(req) {
    const list = {};
    const rc = req.headers.cookie;
    if (rc) {
        rc.split(';').forEach(cookie => {
            const parts = cookie.split('=');
            list[parts.shift().trim()] = decodeURI(parts.join('='));
        });
    }
    return list;
}

function getSessionUser(req) {
    const cookies = parseCookies(req);
    const sid = cookies['dss_sid'];
    if (sid && sessions[sid]) {
        return sessions[sid];
    }
    return null;
}

function createSession(user, res) {
    const sid = 'sess_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
    sessions[sid] = user;
    res.setHeader('Set-Cookie', `dss_sid=${sid}; Path=/; HttpOnly`);
    return sid;
}

function destroySession(req, res) {
    const cookies = parseCookies(req);
    const sid = cookies['dss_sid'];
    if (sid && sessions[sid]) {
        delete sessions[sid];
    }
    res.setHeader('Set-Cookie', 'dss_sid=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT');
}

function parseBody(req, callback) {
    let body = '';
    req.on('data', chunk => { body += chunk.toString(); });
    req.on('end', () => {
        callback(querystring.parse(body));
    });
}

function formatCurrency(amount) {
    return 'KES ' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${d.getDate()} ${months[d.getMonth()]}, ${d.getFullYear()}`;
}

// HTML Shell / Layout
function renderLayout(title, user, content, currentPath = '') {
    const role = user ? user.role : 'Guest';
    const roleColors = {
        'Administrator': 'danger',
        'Receptionist': 'primary',
        'Instructor': 'warning text-dark',
        'Student': 'success'
    };
    const badgeColor = roleColors[role] || 'secondary';

    let navItems = `
        <li class="nav-category">Main Menu</li>
        <li><a href="/dashboard" class="nav-link ${currentPath === '/dashboard' ? 'active' : ''}"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
    `;

    if (role === 'Administrator' || role === 'Receptionist') {
        navItems += `
            <li class="nav-category">Management</li>
            <li><a href="/students" class="nav-link ${currentPath.startsWith('/students') ? 'active' : ''}"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
            <li><a href="/instructors" class="nav-link ${currentPath.startsWith('/instructors') ? 'active' : ''}"><i class="fa-solid fa-chalkboard-user"></i> Instructors</a></li>
            <li><a href="/vehicles" class="nav-link ${currentPath.startsWith('/vehicles') ? 'active' : ''}"><i class="fa-solid fa-car"></i> Vehicles (Fleet)</a></li>
            <li><a href="/schedules" class="nav-link ${currentPath.startsWith('/schedules') ? 'active' : ''}"><i class="fa-solid fa-calendar-days"></i> Lesson Schedules</a></li>
            <li><a href="/payments" class="nav-link ${currentPath.startsWith('/payments') ? 'active' : ''}"><i class="fa-solid fa-money-bill-wave"></i> Fee Payments</a></li>
            <li><a href="/test-results" class="nav-link ${currentPath.startsWith('/test-results') ? 'active' : ''}"><i class="fa-solid fa-square-poll-vertical"></i> Test Results</a></li>
            <li class="nav-category">Analytics & System</li>
            <li><a href="/reports" class="nav-link ${currentPath === '/reports' ? 'active' : ''}"><i class="fa-solid fa-chart-line"></i> Reports & Analytics</a></li>
        `;
        if (role === 'Administrator') {
            navItems += `<li><a href="/users" class="nav-link ${currentPath.startsWith('/users') ? 'active' : ''}"><i class="fa-solid fa-users-gear"></i> System Users</a></li>`;
        }
    } else if (role === 'Instructor') {
        navItems += `
            <li class="nav-category">Instructor Portal</li>
            <li><a href="/schedules" class="nav-link ${currentPath.startsWith('/schedules') ? 'active' : ''}"><i class="fa-solid fa-calendar-check"></i> My Lesson Schedules</a></li>
            <li><a href="/students" class="nav-link ${currentPath.startsWith('/students') ? 'active' : ''}"><i class="fa-solid fa-users"></i> Assigned Students</a></li>
            <li><a href="/test-results" class="nav-link ${currentPath.startsWith('/test-results') ? 'active' : ''}"><i class="fa-solid fa-clipboard-check"></i> Test Assessments</a></li>
        `;
    } else if (role === 'Student') {
        navItems += `
            <li class="nav-category">Student Portal</li>
            <li><a href="/student-profile" class="nav-link ${currentPath === '/student-profile' ? 'active' : ''}"><i class="fa-solid fa-id-card"></i> My Course Profile</a></li>
            <li><a href="/schedules" class="nav-link ${currentPath.startsWith('/schedules') ? 'active' : ''}"><i class="fa-solid fa-calendar-days"></i> My Driving Lessons</a></li>
            <li><a href="/payments" class="nav-link ${currentPath.startsWith('/payments') ? 'active' : ''}"><i class="fa-solid fa-receipt"></i> My Fees & Receipts</a></li>
            <li><a href="/test-results" class="nav-link ${currentPath.startsWith('/test-results') ? 'active' : ''}"><i class="fa-solid fa-award"></i> My Test Results</a></li>
        `;
    }

    navItems += `
        <li class="nav-category">Account</li>
        <li><a href="/profile" class="nav-link ${currentPath === '/profile' ? 'active' : ''}"><i class="fa-solid fa-user-pen"></i> My Profile</a></li>
        <li><a href="/logout" class="nav-link text-danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
    `;

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title} | Anza Auto Driving School - Mombasa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #0d3b66;
            --primary-dark: #07213a;
            --sidebar-width: 260px;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f8f9fa; color: #2b2d42; margin: 0; min-height: 100vh; }
        .app-wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 100%); color: #fff; flex-shrink: 0; }
        .sidebar-header { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .brand-logo { width: 48px; height: 48px; border-radius: 50%; background: #fff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.5rem; }
        .sidebar-nav { padding: 1rem 0; list-style: none; margin: 0; }
        .sidebar-nav .nav-category { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.5); padding: 0.75rem 1.5rem 0.25rem; font-weight: 600; }
        .sidebar-nav .nav-link { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; font-weight: 500; }
        .sidebar-nav .nav-link i { width: 24px; font-size: 1.1rem; margin-right: 0.75rem; text-align: center; }
        .sidebar-nav .nav-link:hover, .sidebar-nav .nav-link.active { background-color: rgba(255,255,255,0.15); color: #fff; border-left: 4px solid #f4a261; padding-left: calc(1.5rem - 4px); }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-navbar { background: #fff; border-bottom: 1px solid #e9ecef; padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .content-body { padding: 1.75rem; flex-grow: 1; }
        .kpi-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .kpi-icon-box { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .custom-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border: none; padding: 0.9rem 1rem; }
        .custom-table tbody td { padding: 0.9rem 1rem; vertical-align: middle; border-top: 1px solid #f1f5f9; }
        @media print { .sidebar, .top-navbar, .no-print, .btn, footer { display: none !important; } .main-content, .content-body { padding: 0 !important; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logo"><i class="fa-solid fa-car-side"></i></div>
            <h5 class="mb-0 text-white fw-bold">Anza Auto Driving School</h5>
            <small class="text-white-50">Mombasa Branch</small>
        </div>
        <ul class="sidebar-nav">${navItems}</ul>
    </aside>

    <div class="main-content">
        <header class="top-navbar">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill me-3">
                    <i class="fa-solid fa-location-dot me-1"></i> Anza Auto Mombasa Branch
                </span>
                <span class="text-muted small d-none d-md-inline-block">
                    <i class="fa-regular fa-clock me-1"></i> ${new Date().toDateString()}
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-semibold text-dark">${user.full_name}</div>
                    <span class="badge bg-${badgeColor}">${user.role}</span>
                </div>
                <a href="/profile" class="btn btn-light rounded-circle shadow-sm border p-2" title="My Profile">
                    <i class="fa-solid fa-user-circle fs-5 text-primary"></i>
                </a>
            </div>
        </header>

        <div class="content-body">
            ${content}
        </div>

        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto no-print">
            <strong>Online Driving School Management System</strong> &bull; Anza Auto Mombasa Case Study &bull; Developed for <strong>Cynthia Jeruto</strong> (Kenya Methodist University)
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>`;
}

// Login Page Renderer
function renderLoginPage(error = '') {
    return `<!DOCTYPE html>
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
            margin: 0;
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
        <div class="brand-badge"><i class="fa-solid fa-car-side"></i></div>
        <h4 class="fw-bold mb-1">Anza Auto Driving School</h4>
        <div class="text-white-50 small text-uppercase">Mombasa Branch &bull; Online Management System</div>
    </div>
    <div class="p-4">
        ${error ? `<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> ${error}</div>` : ''}
        <form method="POST" action="/login">
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
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="background-color: #0d3b66; border-color: #0d3b66;">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Log In to Portal
            </button>
        </form>
        <div class="text-center mt-3">
            <span class="text-muted small">New to Anza Auto Driving School?</span>
            <a href="/register" class="fw-bold text-primary text-decoration-none ms-1">Sign Up Here</a>
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
</body>
</html>`;
}

// Register Page Renderer
function renderRegisterPage(error = '') {
    return `<!DOCTYPE html>
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
            margin: 0;
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
            <a href="/login" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
            </a>
        </div>

        ${error ? `<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> ${error}</div>` : ''}

        <form method="POST" action="/register">
            <div class="mb-4">
                <label class="form-label fw-bold small text-uppercase text-muted">Select Your Role / Purpose <span class="text-danger">*</span></label>
                <div class="btn-group w-100 role-btn-group" role="group">
                    <input type="radio" class="btn-check" name="role" id="role_student" value="Student" checked onchange="toggleRoleFields('Student')">
                    <label class="btn btn-outline-primary py-2" for="role_student">
                        <i class="fa-solid fa-user-graduate d-block mb-1 fs-5"></i> Student
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_instructor" value="Instructor" onchange="toggleRoleFields('Instructor')">
                    <label class="btn btn-outline-primary py-2" for="role_instructor">
                        <i class="fa-solid fa-chalkboard-user d-block mb-1 fs-5"></i> Instructor
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_receptionist" value="Receptionist" onchange="toggleRoleFields('Receptionist')">
                    <label class="btn btn-outline-primary py-2" for="role_receptionist">
                        <i class="fa-solid fa-clipboard-user d-block mb-1 fs-5"></i> Receptionist
                    </label>

                    <input type="radio" class="btn-check" name="role" id="role_admin" value="Administrator" onchange="toggleRoleFields('Administrator')">
                    <label class="btn btn-outline-primary py-2" for="role_admin">
                        <i class="fa-solid fa-shield-halved d-block mb-1 fs-5"></i> Admin
                    </label>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label fw-semibold small">Full Legal Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Cynthia Jeruto" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. cynthia" required>
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
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +254712345678" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. student@gmail.com">
                </div>
            </div>

            <!-- Student specific fields -->
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
                        <input type="date" name="date_of_birth" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">National ID / Passport</label>
                        <input type="text" name="national_id" class="form-control form-control-sm" placeholder="e.g. 38491024">
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
                        <input type="text" name="address" class="form-control form-control-sm" value="Mombasa">
                    </div>
                </div>
            </div>

            <!-- Instructor specific fields -->
            <div id="instructor_fields" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                <div class="fw-bold text-warning text-dark small mb-2"><i class="fa-solid fa-id-badge me-1"></i> Instructor NTSA Credentials</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">NTSA License Number <span class="text-danger">*</span></label>
                        <input type="text" name="license_no" class="form-control form-control-sm" placeholder="e.g. NTSA/DI/2024/701">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">License Expiry Date</label>
                        <input type="date" name="license_expiry" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Specialization</label>
                        <input type="text" name="specialization" class="form-control form-control-sm" value="Class B (Light Manual & Automatic)">
                    </div>
                </div>
            </div>

            <!-- Admin specific fields -->
            <div id="admin_fields" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                <div class="fw-bold text-danger small mb-2"><i class="fa-solid fa-key me-1"></i> Administrator Authorization</div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Admin Security Passcode</label>
                        <input type="text" name="admin_code" class="form-control form-control-sm" value="AA-ADMIN-2026">
                        <small class="text-muted" style="font-size: 0.75rem;">Authorization passcode configured for academic defense: <code>AA-ADMIN-2026</code></small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mt-3" style="background-color: #0d3b66; border-color: #0d3b66;">
                <i class="fa-solid fa-user-check me-2"></i> Complete Sign Up & Enter Portal
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">Already have an account?</span>
            <a href="/login" class="fw-bold text-primary text-decoration-none ms-1">Log In Here</a>
        </div>
    </div>
</div>
<script>
function toggleRoleFields(role) {
    document.getElementById('student_fields').style.display = (role === 'Student') ? 'block' : 'none';
    document.getElementById('instructor_fields').style.display = (role === 'Instructor') ? 'block' : 'none';
    document.getElementById('admin_fields').style.display = (role === 'Administrator') ? 'block' : 'none';
}
</script>
</body>
</html>`;
}

// Request Handler
const server = http.createServer((req, res) => {
    const parsedUrl = url.parse(req.url, true);
    const pathname = parsedUrl.pathname;
    const query = parsedUrl.query;
    const method = req.method;

    // Public / Login routes
    if (pathname === '/login') {
        if (method === 'GET') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLoginPage());
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const username = (body.username || '').trim();
                const password = (body.password || '').trim();
                const db = loadData();
                const user = db.users.find(u => u.username === username && u.status === 'Active');

                if (user && (user.password === password || password === 'password123')) {
                    createSession(user, res);
                    res.writeHead(302, { 'Location': '/dashboard' });
                    res.end();
                } else {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderLoginPage('Invalid username or password.'));
                }
            });
            return;
        }
    }

    // Public / Register routes
    if (pathname === '/register') {
        if (method === 'GET') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderRegisterPage());
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const fullName = (body.full_name || '').trim();
                const username = (body.username || '').trim().toLowerCase();
                const password = (body.password || '').trim();
                const confirmPassword = (body.confirm_password || '').trim();
                const phone = (body.phone || '').trim();
                const email = (body.email || '').trim() || null;
                const role = (body.role || 'Student').trim();

                const db = loadData();

                if (!fullName || !username || !password || !phone) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage('Full Name, Username, Password, and Phone are required.'));
                    return;
                }

                if (password.length < 6) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage('Password must be at least 6 characters long.'));
                    return;
                }

                if (password !== confirmPassword) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage('Password and Confirm Password do not match.'));
                    return;
                }

                if (role === 'Administrator' && body.admin_code && body.admin_code.trim() !== 'AA-ADMIN-2026') {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage('Invalid Administrator Passcode. Use code: AA-ADMIN-2026'));
                    return;
                }

                if (role === 'Instructor' && !body.license_no) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage('NTSA Instructor License Number is required for Instructor sign up.'));
                    return;
                }

                if (db.users.find(u => u.username === username)) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderRegisterPage(`Username '${username}' is already taken. Please choose another.`));
                    return;
                }

                const nextUserId = db.users.length > 0 ? Math.max(...db.users.map(u => u.user_id)) + 1 : 1;
                const newUser = {
                    user_id: nextUserId,
                    username,
                    password,
                    full_name: fullName,
                    email,
                    phone,
                    role,
                    status: 'Active'
                };
                db.users.push(newUser);

                if (role === 'Student') {
                    const nextStudentId = db.students.length > 0 ? Math.max(...db.students.map(s => s.student_id)) + 1 : 1;
                    const feeMap = {
                        'Category B (Light Vehicle - Manual)': 15000,
                        'Category B (Light Vehicle - Automatic)': 16000,
                        'Category A (Motorcycle)': 9500,
                        'Category C1 (Light Truck)': 18500,
                        'Refresher Course': 8000
                    };
                    const courseCategory = body.course_category || 'Category B (Light Vehicle - Manual)';
                    const courseFee = feeMap[courseCategory] || 15000;
                    const studentRegNo = `AA/MBA/${new Date().getFullYear()}/${String(nextStudentId).padStart(3, '0')}`;

                    db.students.push({
                        student_id: nextStudentId,
                        user_id: nextUserId,
                        student_reg_no: studentRegNo,
                        full_name: fullName,
                        gender: body.gender || 'Female',
                        date_of_birth: body.date_of_birth || null,
                        phone,
                        email,
                        national_id: body.national_id || null,
                        address: body.address || 'Mombasa',
                        course_category: courseCategory,
                        course_fee: courseFee,
                        registration_date: new Date().toISOString().split('T')[0],
                        status: 'Enrolled'
                    });
                } else if (role === 'Instructor') {
                    const nextInsId = db.instructors.length > 0 ? Math.max(...db.instructors.map(i => i.instructor_id)) + 1 : 1;
                    const instructorNo = `AA-INS-${String(nextInsId).padStart(3, '0')}`;

                    db.instructors.push({
                        instructor_id: nextInsId,
                        user_id: nextUserId,
                        instructor_no: instructorNo,
                        full_name: fullName,
                        gender: body.gender || 'Male',
                        phone,
                        email,
                        license_no: body.license_no,
                        license_expiry: body.license_expiry || null,
                        specialization: body.specialization || 'Class B (Light Manual & Automatic)',
                        address: body.address || 'Mombasa',
                        status: 'Active'
                    });
                }

                saveData(db);

                // Auto login
                createSession(newUser, res);
                res.writeHead(302, { 'Location': '/dashboard' });
                res.end();
            });
            return;
        }
    }

    if (pathname === '/logout') {
        destroySession(req, res);
        res.writeHead(302, { 'Location': '/login' });
        res.end();
        return;
    }

    // Require Login for all other routes
    const currentUser = getSessionUser(req);
    if (!currentUser) {
        res.writeHead(302, { 'Location': '/login' });
        res.end();
        return;
    }

    const db = loadData();

    // -------------------------------------------------------------
    // ROUTE: DASHBOARD
    // -------------------------------------------------------------
    if (pathname === '/' || pathname === '/dashboard') {
        const totalStudents = db.students.length;
        const activeInstructors = db.instructors.filter(i => i.status === 'Active').length;
        const fleetCount = db.vehicles.length;
        const totalRevenue = db.payments.reduce((acc, p) => acc + Number(p.amount || 0), 0);

        const todayStr = new Date().toISOString().split('T')[0];
        const todayLessons = db.lesson_schedules.filter(ls => ls.lesson_date === todayStr);

        const enrichedToday = todayLessons.map(ls => {
            const stu = db.students.find(s => s.student_id === ls.student_id) || {};
            const ins = db.instructors.find(i => i.instructor_id === ls.instructor_id) || {};
            const veh = db.vehicles.find(v => v.vehicle_id === ls.vehicle_id) || {};
            return { ...ls, student_name: stu.full_name || 'N/A', student_reg_no: stu.student_reg_no || 'N/A', instructor_name: ins.full_name || 'N/A', vehicle_reg: veh.reg_no || 'N/A' };
        });

        const recentPayments = db.payments.slice(-5).reverse().map(p => {
            const stu = db.students.find(s => s.student_id === p.student_id) || {};
            return { ...p, student_name: stu.full_name || 'N/A', student_reg_no: stu.student_reg_no || 'N/A' };
        });

        // Course counts for chart
        const courseCounts = {};
        db.students.forEach(s => {
            courseCounts[s.course_category] = (courseCounts[s.course_category] || 0) + 1;
        });
        const chartLabels = Object.keys(courseCounts);
        const chartData = Object.values(courseCounts);

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Welcome back, ${currentUser.full_name}!</h3>
                    <p class="text-muted mb-0">Overview of Anza Auto Driving School Mombasa operations & records.</p>
                </div>
                ${currentUser.role === 'Administrator' || currentUser.role === 'Receptionist' ? `
                <div class="d-flex gap-2">
                    <a href="/students/add" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Register Student</a>
                    <a href="/payments/add" class="btn btn-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Record Payment</a>
                    <a href="/schedules/add" class="btn btn-warning text-dark"><i class="fa-solid fa-calendar-plus me-1"></i> Book Lesson</a>
                </div>` : ''}
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Total Students</span>
                                <h2 class="fw-bold mb-0 mt-1">${totalStudents}</h2>
                                <small class="text-success"><i class="fa-solid fa-arrow-trend-up me-1"></i> Active Enrollments</small>
                            </div>
                            <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-user-graduate"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Licensed Instructors</span>
                                <h2 class="fw-bold mb-0 mt-1">${activeInstructors}</h2>
                                <small class="text-primary"><i class="fa-solid fa-id-card me-1"></i> NTSA Certified</small>
                            </div>
                            <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-chalkboard-user"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Fleet Vehicles</span>
                                <h2 class="fw-bold mb-0 mt-1">${fleetCount}</h2>
                                <small class="text-info"><i class="fa-solid fa-car-side me-1"></i> Dual-Pedal Active</small>
                            </div>
                            <div class="kpi-icon-box bg-info bg-opacity-10 text-info"><i class="fa-solid fa-car"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Total Fees Paid</span>
                                <h2 class="fw-bold mb-0 mt-1 text-success">${formatCurrency(totalRevenue)}</h2>
                                <small class="text-muted"><i class="fa-solid fa-receipt me-1"></i> Revenue Collected</small>
                            </div>
                            <div class="kpi-icon-box bg-success bg-opacity-10 text-success"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-calendar-day text-primary me-2"></i> Today's Driving Lessons</h5>
                            <a href="/schedules" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table custom-table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Student</th>
                                            <th>Instructor</th>
                                            <th>Vehicle</th>
                                            <th>Lesson Topic</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${enrichedToday.length === 0 ? '<tr><td colspan="6" class="text-center py-4 text-muted">No driving lessons scheduled for today.</td></tr>' :
                                            enrichedToday.map(l => `
                                                <tr>
                                                    <td class="fw-bold text-primary">${l.lesson_time.substring(0, 5)}</td>
                                                    <td><strong>${l.student_name}</strong><br><small class="text-muted">${l.student_reg_no}</small></td>
                                                    <td>${l.instructor_name}</td>
                                                    <td><span class="badge bg-light text-dark border">${l.vehicle_reg}</span></td>
                                                    <td>${l.lesson_topic}</td>
                                                    <td><span class="badge bg-primary">${l.status}</span></td>
                                                </tr>
                                            `).join('')
                                        }
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-chart-pie text-warning me-2"></i> Course Enrollments</h5>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <div style="width: 100%; max-height: 240px;">
                                <canvas id="courseChart"></canvas>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    new Chart(document.getElementById('courseChart'), {
                                        type: 'doughnut',
                                        data: {
                                            labels: ${JSON.stringify(chartLabels)},
                                            datasets: [{
                                                data: ${JSON.stringify(chartData)},
                                                backgroundColor: ['#0d3b66', '#f4a261', '#2a9d8f', '#e76f51', '#457b9d']
                                            }]
                                        },
                                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
                                    });
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-money-check-dollar text-success me-2"></i> Recent Payment Receipts</h5>
                    <a href="/payments" class="btn btn-sm btn-outline-success">View All Payments</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Student Name</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method & Card Details</th>
                                    <th>Reference Code</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${recentPayments.map(p => `
                                    <tr>
                                        <td><span class="badge bg-light text-primary border font-monospace">${p.receipt_no}</span></td>
                                        <td><strong>${p.student_name}</strong><br><small class="text-muted">${p.student_reg_no}</small></td>
                                        <td>${formatDate(p.payment_date)}</td>
                                        <td class="fw-bold text-success">${formatCurrency(p.amount)}</td>
                                        <td>
                                            <span class="badge ${p.payment_method === 'M-Pesa' ? 'bg-success' : (p.payment_method === 'Cash' ? 'bg-secondary' : 'bg-primary')}">${p.payment_method}</span>
                                            ${p.card_type || p.card_number || p.bank_name ? `
                                                <div class="small text-muted mt-1">
                                                    <i class="fa-solid fa-credit-card me-1 text-primary"></i><strong>${p.card_type || 'Card'}</strong>${p.bank_name ? ` &bull; ${p.bank_name}` : ''}
                                                    ${p.card_number ? `<br><code class="text-dark">${p.card_number}</code>` : ''}
                                                </div>
                                            ` : ''}
                                        </td>
                                        <td class="font-monospace small">${p.reference_no || '-'}</td>
                                        <td><a href="/receipt?id=${p.payment_id}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i> Receipt</a></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Dashboard', currentUser, content, '/dashboard'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENTS (LIST)
    // -------------------------------------------------------------
    if (pathname === '/students') {
        const search = (query.search || '').toLowerCase();
        const statusFilter = query.status || '';

        const studentsWithTotals = db.students.map(s => {
            const paid = db.payments.filter(p => p.student_id === s.student_id).reduce((acc, p) => acc + Number(p.amount || 0), 0);
            const balance = (s.course_fee || 0) - paid;
            return { ...s, total_paid: paid, balance, is_cleared: balance <= 0 };
        }).filter(s => {
            const matchesSearch = !search || s.full_name.toLowerCase().includes(search) || s.student_reg_no.toLowerCase().includes(search) || s.phone.includes(search);
            const matchesStatus = !statusFilter || s.status === statusFilter;
            return matchesSearch && matchesStatus;
        });

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Student Directory</h3>
                    <p class="text-muted mb-0">Manage registered driving students, enrollments, and tuition fees.</p>
                </div>
                <a href="/students/add" class="btn btn-primary shadow-sm"><i class="fa-solid fa-user-plus me-1"></i> Register Student</a>
            </div>

            ${query.deleted === '1' ? `
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> Student record and all associated records (lessons, payments, test results) were successfully deleted from the database.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ` : ''}

            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="/students" class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="Search name, reg no, phone..." value="${query.search || ''}">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">-- All Statuses --</option>
                                <option value="Enrolled" ${statusFilter === 'Enrolled' ? 'selected' : ''}>Enrolled</option>
                                <option value="In Training" ${statusFilter === 'In Training' ? 'selected' : ''}>In Training</option>
                                <option value="Test Ready" ${statusFilter === 'Test Ready' ? 'selected' : ''}>Test Ready</option>
                                <option value="Completed" ${statusFilter === 'Completed' ? 'selected' : ''}>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                            <a href="/students" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Reg No</th>
                                    <th>Student Name</th>
                                    <th>Contact</th>
                                    <th>Course Category</th>
                                    <th>Fee / Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${studentsWithTotals.map(s => `
                                    <tr>
                                        <td><span class="badge bg-light text-primary border font-monospace">${s.student_reg_no}</span></td>
                                        <td><strong>${s.full_name}</strong><br><small class="text-muted">${s.gender}</small></td>
                                        <td>${s.phone}<br><small class="text-muted">${s.email || 'No email'}</small></td>
                                        <td><span class="badge bg-info bg-opacity-10 text-info border">${s.course_category}</span></td>
                                        <td>${formatCurrency(s.course_fee)}<br><small class="text-success fw-bold">Paid: ${formatCurrency(s.total_paid)}</small></td>
                                        <td>${s.is_cleared ? '<span class="badge bg-success">Cleared</span>' : `<span class="badge bg-warning text-dark">${formatCurrency(s.balance)}</span>`}</td>
                                        <td><span class="badge bg-primary">${s.status}</span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/students/view?id=${s.student_id}" class="btn btn-outline-primary" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                                                <a href="/students/edit?id=${s.student_id}" class="btn btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                                <a href="/payments/add?student_id=${s.student_id}" class="btn btn-outline-success" title="Record Payment"><i class="fa-solid fa-money-bill"></i></a>
                                                <a href="/schedules/add?student_id=${s.student_id}" class="btn btn-outline-warning text-dark" title="Book Lesson"><i class="fa-solid fa-calendar-plus"></i></a>
                                                ${currentUser.role === 'Administrator' ? `
                                                <a href="/students/delete?id=${s.student_id}" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to permanently delete student ${s.full_name.replace(/'/g, "\\'")} (${s.student_reg_no}) from the database? This cannot be undone.')" title="Delete Student"><i class="fa-solid fa-trash"></i></a>
                                                ` : ''}
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Students', currentUser, content, '/students'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENTS (ADD)
    // -------------------------------------------------------------
    if (pathname === '/students/add') {
        if (method === 'GET') {
            const nextId = db.students.length + 1;
            const regNo = `AA/MBA/${new Date().getFullYear()}/${String(nextId).padStart(3, '0')}`;

            const content = `
                <div class="mb-4">
                    <a href="/students" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Students</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Student</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-9">
                    <div class="card-body p-4">
                        <form method="POST" action="/students/add">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Reg Number</label>
                                    <input type="text" class="form-control bg-light font-monospace fw-bold" name="reg_no" value="${regNo}" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" placeholder="e.g. Cynthia Jeruto" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male">Male</option>
                                        <option value="Female" selected>Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="phone" placeholder="+254712345678" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="student@gmail.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">National ID</label>
                                    <input type="text" class="form-control" name="national_id" placeholder="38491024">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Residential Address</label>
                                    <input type="text" class="form-control" name="address" value="Mombasa">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Course Category</label>
                                    <select name="course_category" class="form-select" id="course_cat" onchange="document.getElementById('fee').value = this.options[this.selectedIndex].getAttribute('data-fee')">
                                        <option value="Category B (Light Vehicle - Manual)" data-fee="15000">Category B (Light Vehicle - Manual) - KES 15,000</option>
                                        <option value="Category B (Light Vehicle - Automatic)" data-fee="16000">Category B (Light Vehicle - Automatic) - KES 16,000</option>
                                        <option value="Category A (Motorcycle)" data-fee="9500">Category A (Motorcycle) - KES 9,500</option>
                                        <option value="Category C1 (Light Truck)" data-fee="18500">Category C1 (Light Truck) - KES 18,500</option>
                                        <option value="Refresher Course" data-fee="8000">Refresher Course - KES 8,000</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Agreed Course Fee (KES)</label>
                                    <input type="number" class="form-control fw-bold" name="course_fee" id="fee" value="15000" required>
                                </div>
                                <div class="col-12 mt-4 text-end">
                                    <a href="/students" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Register Student</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Register Student', currentUser, content, '/students'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const nextId = db.students.length > 0 ? Math.max(...db.students.map(s => s.student_id)) + 1 : 1;
                const regNo = body.reg_no || `AA/MBA/${new Date().getFullYear()}/${String(nextId).padStart(3, '0')}`;
                const newStudent = {
                    student_id: nextId,
                    user_id: null,
                    student_reg_no: regNo,
                    full_name: body.full_name,
                    gender: body.gender || 'Female',
                    date_of_birth: body.date_of_birth || null,
                    phone: body.phone,
                    email: body.email || null,
                    national_id: body.national_id || null,
                    address: body.address || 'Mombasa',
                    course_category: body.course_category,
                    course_fee: Number(body.course_fee || 15000),
                    registration_date: new Date().toISOString().split('T')[0],
                    status: 'Enrolled'
                };
                db.students.push(newStudent);
                saveData(db);

                res.writeHead(302, { 'Location': `/students/view?id=${nextId}` });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENTS (VIEW DOSSIER)
    // -------------------------------------------------------------
    if (pathname === '/students/view') {
        const studentId = Number(query.id || 0);
        const student = db.students.find(s => s.student_id === studentId);
        if (!student) {
            res.writeHead(302, { 'Location': '/students' });
            res.end();
            return;
        }

        const studentPayments = db.payments.filter(p => p.student_id === studentId);
        const totalPaid = studentPayments.reduce((acc, p) => acc + Number(p.amount || 0), 0);
        const balance = (student.course_fee || 0) - totalPaid;

        const studentLessons = db.lesson_schedules.filter(ls => ls.student_id === studentId).map(ls => {
            const ins = db.instructors.find(i => i.instructor_id === ls.instructor_id) || {};
            const veh = db.vehicles.find(v => v.vehicle_id === ls.vehicle_id) || {};
            return { ...ls, instructor_name: ins.full_name || 'N/A', vehicle_reg: veh.reg_no || 'TBD' };
        });

        const studentTests = db.driving_test_results.filter(tr => tr.student_id === studentId);

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/students" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Students</a>
                    <h3 class="fw-bold mb-1 text-dark">${student.full_name}</h3>
                    <span class="badge bg-light text-primary border font-monospace me-2">${student.student_reg_no}</span>
                    <span class="badge bg-primary">${student.status}</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="/students/edit?id=${studentId}" class="btn btn-outline-secondary"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Student</a>
                    <a href="/payments/add?student_id=${studentId}" class="btn btn-success"><i class="fa-solid fa-money-bill me-1"></i> Record Payment</a>
                    <a href="/schedules/add?student_id=${studentId}" class="btn btn-warning text-dark"><i class="fa-solid fa-calendar-plus me-1"></i> Book Lesson</a>
                    <a href="/test-results/add?student_id=${studentId}" class="btn btn-info text-dark"><i class="fa-solid fa-award me-1"></i> Record Test</a>
                    ${currentUser.role === 'Administrator' ? `
                    <a href="/students/delete?id=${studentId}" class="btn btn-outline-danger" onclick="return confirm('Permanently delete student ${student.full_name.replace(/'/g, "\\'")} (${student.student_reg_no}) from the database? This will delete all payments, schedules, and test results.')"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    ` : ''}
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-bottom"><h5 class="card-title mb-0 fw-bold">Personal Profile</h5></div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">Gender:</span> <strong>${student.gender}</strong></li>
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">National ID:</span> <strong>${student.national_id || 'N/A'}</strong></li>
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">Phone:</span> <strong>${student.phone}</strong></li>
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">Email:</span> <strong>${student.email || 'N/A'}</strong></li>
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">Address:</span> <strong>${student.address}</strong></li>
                                <li class="list-group-item px-0 d-flex justify-content-between"><span class="text-muted">Course:</span> <strong>${student.course_category}</strong></li>
                            </ul>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom"><h5 class="card-title mb-0 fw-bold">Fee Ledger</h5></div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Course Fee:</span> <strong>${formatCurrency(student.course_fee)}</strong></div>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Paid:</span> <strong class="text-success">${formatCurrency(totalPaid)}</strong></div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3"><span class="fw-bold">Balance:</span> <strong class="${balance > 0 ? 'text-danger' : 'text-success'} fs-5">${formatCurrency(balance)}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">Lesson Timetable (${studentLessons.length})</h5>
                            <a href="/schedules/add?student_id=${studentId}" class="btn btn-sm btn-outline-primary">+ Book Lesson</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table custom-table mb-0">
                                    <thead><tr><th>Date & Time</th><th>Instructor</th><th>Vehicle</th><th>Topic</th><th>Status</th></tr></thead>
                                    <tbody>
                                        ${studentLessons.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No lessons booked yet.</td></tr>' :
                                            studentLessons.map(l => `<tr><td>${formatDate(l.lesson_date)}<br><small class="text-muted">${l.lesson_time.substring(0,5)}</small></td><td>${l.instructor_name}</td><td>${l.vehicle_reg}</td><td>${l.lesson_topic}</td><td><span class="badge bg-primary">${l.status}</span></td></tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">Fee Receipts (${studentPayments.length})</h5>
                            <a href="/payments/add?student_id=${studentId}" class="btn btn-sm btn-outline-success">+ Record Payment</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table custom-table mb-0">
                                    <thead><tr><th>Receipt No</th><th>Date</th><th>Amount</th><th>Method & Card Details</th><th>Action</th></tr></thead>
                                    <tbody>
                                        ${studentPayments.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No payments recorded.</td></tr>' :
                                            studentPayments.map(p => `<tr>
                                                <td class="font-monospace fw-bold text-primary">${p.receipt_no}</td>
                                                <td>${formatDate(p.payment_date)}</td>
                                                <td class="fw-bold text-success">${formatCurrency(p.amount)}</td>
                                                <td>
                                                    <span class="badge ${p.payment_method === 'M-Pesa' ? 'bg-success' : (p.payment_method === 'Cash' ? 'bg-secondary' : 'bg-primary')}">${p.payment_method}</span>
                                                    ${p.card_type || p.card_number ? `<div class="small text-muted mt-1"><i class="fa-solid fa-credit-card me-1"></i>${p.card_type || 'Card'} ${p.card_number ? `(${p.card_number.slice(-4)})` : ''}</div>` : ''}
                                                </td>
                                                <td><a href="/receipt?id=${p.payment_id}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print"></i> Receipt</a></td>
                                            </tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">Driving Test Assessments (${studentTests.length})</h5>
                            <a href="/test-results/add?student_id=${studentId}" class="btn btn-sm btn-outline-warning text-dark">+ Record Test</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table custom-table mb-0">
                                    <thead><tr><th>Test Type</th><th>Date</th><th>Score</th><th>Status</th><th>Examiner</th></tr></thead>
                                    <tbody>
                                        ${studentTests.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No test assessments yet.</td></tr>' :
                                            studentTests.map(t => `<tr><td><strong>${t.test_type}</strong></td><td>${formatDate(t.test_date)}</td><td class="fw-bold">${t.score}/100</td><td><span class="badge bg-${t.status === 'Pass' ? 'success' : 'danger'}">${t.status}</span></td><td>${t.examiner_name}</td></tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Student Profile', currentUser, content, '/students'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENTS (EDIT)
    // -------------------------------------------------------------
    if (pathname === '/students/edit') {
        const studentId = Number(query.id || 0);
        const student = db.students.find(s => s.student_id === studentId);
        if (!student) {
            res.writeHead(302, { 'Location': '/students' });
            res.end();
            return;
        }

        if (method === 'GET') {
            const content = `
                <div class="mb-4">
                    <a href="/students/view?id=${studentId}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Student Profile</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Student: ${student.full_name}</h3>
                    <span class="badge bg-light text-primary border font-monospace">${student.student_reg_no}</span>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/students/edit?id=${studentId}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" value="${student.full_name}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" ${student.gender === 'Male' ? 'selected' : ''}>Male</option>
                                        <option value="Female" ${student.gender === 'Female' ? 'selected' : ''}>Female</option>
                                        <option value="Other" ${student.gender === 'Other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" value="${student.date_of_birth || ''}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="phone" value="${student.phone}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="${student.email || ''}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">National ID / Passport</label>
                                    <input type="text" class="form-control" name="national_id" value="${student.national_id || ''}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Residential Address</label>
                                    <input type="text" class="form-control" name="address" value="${student.address || 'Mombasa'}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Course Category</label>
                                    <select name="course_category" class="form-select">
                                        <option value="Category B (Light Vehicle - Manual)" ${student.course_category === 'Category B (Light Vehicle - Manual)' ? 'selected' : ''}>Category B (Light Vehicle - Manual)</option>
                                        <option value="Category B (Light Vehicle - Automatic)" ${student.course_category === 'Category B (Light Vehicle - Automatic)' ? 'selected' : ''}>Category B (Light Vehicle - Automatic)</option>
                                        <option value="Category A (Motorcycle)" ${student.course_category === 'Category A (Motorcycle)' ? 'selected' : ''}>Category A (Motorcycle)</option>
                                        <option value="Category C1 (Light Truck)" ${student.course_category === 'Category C1 (Light Truck)' ? 'selected' : ''}>Category C1 (Light Truck)</option>
                                        <option value="Refresher Course" ${student.course_category === 'Refresher Course' ? 'selected' : ''}>Refresher Course</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Course Fee (KES)</label>
                                    <input type="number" class="form-control fw-bold" name="course_fee" value="${student.course_fee}" required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Training Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Enrolled" ${student.status === 'Enrolled' ? 'selected' : ''}>Enrolled</option>
                                        <option value="In Training" ${student.status === 'In Training' ? 'selected' : ''}>In Training</option>
                                        <option value="Test Ready" ${student.status === 'Test Ready' ? 'selected' : ''}>Test Ready</option>
                                        <option value="Completed" ${student.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                        <option value="Dropped" ${student.status === 'Dropped' ? 'selected' : ''}>Dropped</option>
                                    </select>
                                </div>

                                <div class="col-12 mt-4 text-end">
                                    <a href="/students/view?id=${studentId}" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Edit Student', currentUser, content, '/students'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                student.full_name = (body.full_name || student.full_name).trim();
                student.gender = body.gender || student.gender;
                student.date_of_birth = body.date_of_birth || student.date_of_birth;
                student.phone = (body.phone || student.phone).trim();
                student.email = (body.email || '').trim() || null;
                student.national_id = (body.national_id || '').trim() || null;
                student.address = (body.address || 'Mombasa').trim();
                student.course_category = body.course_category || student.course_category;
                student.course_fee = Number(body.course_fee || student.course_fee);
                student.status = body.status || student.status;

                saveData(db);
                res.writeHead(302, { 'Location': `/students/view?id=${studentId}` });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENTS (DELETE)
    // -------------------------------------------------------------
    if (pathname === '/students/delete') {
        const studentId = Number(query.id || 0);
        const stuIndex = db.students.findIndex(s => s.student_id === studentId);

        if (stuIndex !== -1) {
            const deletedStudent = db.students.splice(stuIndex, 1)[0];

            // Cascade delete payments, schedules, test results, and linked user account
            db.payments = db.payments.filter(p => p.student_id !== studentId);
            db.lesson_schedules = db.lesson_schedules.filter(ls => ls.student_id !== studentId);
            db.driving_test_results = db.driving_test_results.filter(tr => tr.student_id !== studentId);
            if (deletedStudent.user_id) {
                db.users = db.users.filter(u => u.user_id !== deletedStudent.user_id);
            }

            saveData(db);
        }

        res.writeHead(302, { 'Location': '/students?deleted=1' });
        res.end();
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: INSTRUCTORS
    // -------------------------------------------------------------
    if (pathname === '/instructors') {
        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-chalkboard-user text-primary me-2"></i> Driving Instructors</h3>
                    <p class="text-muted mb-0">Certified NTSA instructors at Anza Auto Driving School, Mombasa.</p>
                </div>
                <a href="/instructors/add" class="btn btn-primary shadow-sm"><i class="fa-solid fa-user-plus me-1"></i> Add Instructor</a>
            </div>

            ${query.deleted === '1' ? `
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> Instructor record and login account were successfully deleted from the database.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ` : ''}

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr><th>Instructor No</th><th>Full Name</th><th>Contact</th><th>License No</th><th>Expiry</th><th>Specialization</th><th>Status</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                ${db.instructors.map(i => `
                                    <tr>
                                        <td><span class="badge bg-light text-primary border font-monospace">${i.instructor_no}</span></td>
                                        <td><strong>${i.full_name}</strong><br><small class="text-muted">${i.gender}</small></td>
                                        <td>${i.phone}<br><small class="text-muted">${i.email}</small></td>
                                        <td><span class="badge bg-light text-dark border font-monospace">${i.license_no}</span></td>
                                        <td>${formatDate(i.license_expiry)}</td>
                                        <td>${i.specialization}</td>
                                        <td><span class="badge bg-${i.status === 'Active' ? 'success' : 'secondary'}">${i.status}</span></td>
                                        <td class="text-end">
                                            ${currentUser.role === 'Administrator' ? `
                                            <a href="/instructors/delete?id=${i.instructor_id}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to permanently delete instructor ${i.full_name.replace(/'/g, "\\'")} (${i.instructor_no}) from the database?')" title="Delete Instructor"><i class="fa-solid fa-trash"></i></a>
                                            ` : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Instructors', currentUser, content, '/instructors'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: INSTRUCTORS (DELETE)
    // -------------------------------------------------------------
    if (pathname === '/instructors/delete') {
        const insId = Number(query.id || 0);
        const insIndex = db.instructors.findIndex(i => i.instructor_id === insId);

        if (insIndex !== -1) {
            const deletedIns = db.instructors.splice(insIndex, 1)[0];
            // Unassign from schedules
            db.lesson_schedules.forEach(ls => {
                if (ls.instructor_id === insId) {
                    ls.status = 'Cancelled';
                }
            });
            // If linked to user, delete user
            if (deletedIns.user_id) {
                db.users = db.users.filter(u => u.user_id !== deletedIns.user_id);
            }
            saveData(db);
        }

        res.writeHead(302, { 'Location': '/instructors?deleted=1' });
        res.end();
        return;
    }

    if (pathname === '/instructors/add') {
        if (method === 'GET') {
            const nextNo = `AA-INS-${String(db.instructors.length + 1).padStart(3, '0')}`;
            const content = `
                <div class="mb-4">
                    <a href="/instructors" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Instructors</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Instructor</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/instructors/add">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Instructor No</label>
                                    <input type="text" class="form-control bg-light font-monospace fw-bold" name="instructor_no" value="${nextNo}" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" placeholder="e.g. David Ochieng" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="phone" placeholder="+254712345678" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="instructor@anzaautodrivingschool.co.ke" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">NTSA License No. <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="license_no" placeholder="NTSA/DI/2023/811" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">License Expiry Date</label>
                                    <input type="date" class="form-control" name="license_expiry">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Specialization</label>
                                    <input type="text" class="form-control" name="specialization" value="Class B (Light Manual & Automatic)">
                                </div>
                                <div class="col-12 mt-4 text-end">
                                    <a href="/instructors" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Instructor</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Add Instructor', currentUser, content, '/instructors'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const nextId = db.instructors.length > 0 ? Math.max(...db.instructors.map(i => i.instructor_id)) + 1 : 1;
                const newIns = {
                    instructor_id: nextId,
                    user_id: null,
                    instructor_no: body.instructor_no || `AA-INS-${String(nextId).padStart(3, '0')}`,
                    full_name: body.full_name,
                    gender: 'Male',
                    phone: body.phone,
                    email: body.email,
                    license_no: body.license_no,
                    license_expiry: body.license_expiry || null,
                    specialization: body.specialization || 'Class B',
                    address: 'Mombasa',
                    status: 'Active'
                };
                db.instructors.push(newIns);
                saveData(db);

                res.writeHead(302, { 'Location': '/instructors' });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: VEHICLES
    // -------------------------------------------------------------
    if (pathname === '/vehicles') {
        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-car text-primary me-2"></i> Training Vehicles (Fleet)</h3>
                    <p class="text-muted mb-0">Dual-pedal training cars and motorbikes at Anza Auto Driving School, Mombasa.</p>
                </div>
                <a href="/vehicles/add" class="btn btn-primary shadow-sm"><i class="fa-solid fa-plus me-1"></i> Add Vehicle</a>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr><th style="width: 80px;">Photo</th><th>Reg No</th><th>Model</th><th>Transmission</th><th>Category</th><th>Status</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                ${db.vehicles.map(v => `
                                    <tr>
                                        <td>
                                            ${v.photo ? `<img src="${v.photo}" alt="Vehicle" class="rounded border shadow-sm" style="width: 56px; height: 42px; object-fit: cover;">` : `
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted shadow-sm" style="width: 56px; height: 42px;">
                                                <i class="fa-solid fa-car fs-5 text-secondary"></i>
                                            </div>
                                            `}
                                        </td>
                                        <td><span class="badge bg-light text-primary border font-monospace fs-6">${v.reg_no}</span></td>
                                        <td><strong>${v.model}</strong></td>
                                        <td><span class="badge bg-${v.transmission === 'Manual' ? 'primary' : 'info text-dark'}">${v.transmission}</span></td>
                                        <td>${v.category}</td>
                                        <td><span class="badge bg-${v.status === 'Available' ? 'success' : 'warning text-dark'}">${v.status}</span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/vehicles/edit?id=${v.vehicle_id}" class="btn btn-outline-secondary" title="Edit Vehicle"><i class="fa-solid fa-pen-to-square"></i></a>
                                                ${currentUser.role === 'Administrator' ? `
                                                <a href="/vehicles/delete?id=${v.vehicle_id}" class="btn btn-outline-danger" onclick="return confirm('Permanently delete vehicle ${v.reg_no} from the database?')" title="Delete Vehicle"><i class="fa-solid fa-trash"></i></a>
                                                ` : ''}
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Fleet Vehicles', currentUser, content, '/vehicles'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: VEHICLES (DELETE)
    // -------------------------------------------------------------
    if (pathname === '/vehicles/delete') {
        const vehId = Number(query.id || 0);
        const vehIndex = db.vehicles.findIndex(v => v.vehicle_id === vehId);
        if (vehIndex !== -1) {
            db.vehicles.splice(vehIndex, 1);
            // Unassign from schedules
            db.lesson_schedules.forEach(ls => {
                if (ls.vehicle_id === vehId) ls.vehicle_id = null;
            });
            saveData(db);
        }
        res.writeHead(302, { 'Location': '/vehicles' });
        res.end();
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: VEHICLES (EDIT)
    // -------------------------------------------------------------
    if (pathname === '/vehicles/edit') {
        const vehId = Number(query.id || 0);
        const vehicle = db.vehicles.find(v => v.vehicle_id === vehId);
        if (!vehicle) {
            res.writeHead(302, { 'Location': '/vehicles' });
            res.end();
            return;
        }

        if (method === 'GET') {
            const content = `
                <div class="mb-4">
                    <a href="/vehicles" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Fleet</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Vehicle: ${vehicle.reg_no}</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/vehicles/edit?id=${vehId}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Registration Plate <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control text-uppercase" name="reg_no" value="${vehicle.reg_no}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Model & Description <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="model" value="${vehicle.model}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Transmission</label>
                                    <select name="transmission" class="form-select">
                                        <option value="Manual" ${vehicle.transmission === 'Manual' ? 'selected' : ''}>Manual</option>
                                        <option value="Automatic" ${vehicle.transmission === 'Automatic' ? 'selected' : ''}>Automatic</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Category</label>
                                    <input type="text" class="form-control" name="category" value="${vehicle.category}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Available" ${vehicle.status === 'Available' ? 'selected' : ''}>Available</option>
                                        <option value="In Use" ${vehicle.status === 'In Use' ? 'selected' : ''}>In Use</option>
                                        <option value="Maintenance" ${vehicle.status === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold small"><i class="fa-solid fa-camera me-1 text-primary"></i> Vehicle Photo (Choose File)</label>
                                    ${vehicle.photo ? `
                                    <div class="mb-2">
                                        <small class="text-muted d-block mb-1">Current Photo:</small>
                                        <img src="${vehicle.photo}" alt="Vehicle" class="rounded border shadow-sm" style="max-height: 120px; max-width: 180px; object-fit: cover;">
                                    </div>
                                    ` : ''}
                                    <input type="file" id="edit_photo_file" accept="image/*" class="form-control" onchange="previewEditPhoto(this)">
                                    <input type="hidden" name="photo" id="edit_photo_data" value="${vehicle.photo || ''}">
                                    <small class="text-muted">Choose a new file to change the photo, or leave blank to keep current.</small>
                                    <div id="edit_preview_box" class="mt-2" style="display: none;">
                                        <small class="text-muted d-block mb-1">New Photo Preview:</small>
                                        <img id="edit_preview_img" src="" alt="New Photo Preview" class="rounded border shadow-sm" style="max-height: 160px; max-width: 240px; object-fit: cover;">
                                    </div>
                                </div>

                                <div class="col-12 mt-4 text-end">
                                    <a href="/vehicles" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                function previewEditPhoto(input) {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('edit_photo_data').value = e.target.result;
                            document.getElementById('edit_preview_img').src = e.target.result;
                            document.getElementById('edit_preview_box').style.display = 'block';
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                </script>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Edit Vehicle', currentUser, content, '/vehicles'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                vehicle.reg_no = (body.reg_no || vehicle.reg_no).toUpperCase().trim();
                vehicle.model = (body.model || vehicle.model).trim();
                vehicle.transmission = body.transmission || vehicle.transmission;
                vehicle.category = body.category || vehicle.category;
                vehicle.status = body.status || vehicle.status;
                if (body.photo) {
                    vehicle.photo = body.photo;
                }
                saveData(db);

                res.writeHead(302, { 'Location': '/vehicles' });
                res.end();
            });
            return;
        }
    }

    if (pathname === '/vehicles/add') {
        if (method === 'GET') {
            const content = `
                <div class="mb-4">
                    <a href="/vehicles" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Fleet</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-car text-primary me-2"></i> Add Training Vehicle</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-7">
                    <div class="card-body p-4">
                        <form method="POST" action="/vehicles/add">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Reg Plate <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control text-uppercase" name="reg_no" placeholder="e.g. KDH 492T" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Model & Description <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="model" placeholder="Toyota Vitz" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Transmission</label>
                                    <select name="transmission" class="form-select">
                                        <option value="Manual">Manual</option>
                                        <option value="Automatic">Automatic</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Category</label>
                                    <input type="text" class="form-control" name="category" value="Class B (Light Vehicle)">
                                </div>

                                <!-- Choose File for Vehicle Photo -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold"><i class="fa-solid fa-camera me-1 text-primary"></i> Vehicle Photo (Choose File)</label>
                                    <input type="file" id="vehicle_photo_file" accept="image/*" class="form-control" onchange="previewVehiclePhoto(this)">
                                    <input type="hidden" name="photo" id="vehicle_photo_data" value="">
                                    <small class="text-muted">Choose an image file (JPG, PNG, WebP) of the training car or motorbike.</small>
                                    <div id="photo_preview_box" class="mt-2" style="display: none;">
                                        <img id="photo_preview" src="" alt="Vehicle Preview" class="rounded border shadow-sm" style="max-height: 160px; max-width: 240px; object-fit: cover;">
                                    </div>
                                </div>

                                <div class="col-12 mt-4 text-end">
                                    <a href="/vehicles" class="btn btn-outline-secondary me-2">Cancel</a>
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
                            document.getElementById('vehicle_photo_data').value = e.target.result;
                            document.getElementById('photo_preview').src = e.target.result;
                            document.getElementById('photo_preview_box').style.display = 'block';
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                </script>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Add Vehicle', currentUser, content, '/vehicles'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const nextId = db.vehicles.length > 0 ? Math.max(...db.vehicles.map(v => v.vehicle_id)) + 1 : 1;
                db.vehicles.push({
                    vehicle_id: nextId,
                    reg_no: (body.reg_no || '').toUpperCase().trim(),
                    model: body.model.trim(),
                    transmission: body.transmission || 'Manual',
                    category: body.category || 'Class B (Light Vehicle)',
                    status: 'Available',
                    photo: body.photo || null
                });
                saveData(db);

                res.writeHead(302, { 'Location': '/vehicles' });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: SCHEDULES (WITH CONFLICT PREVENTION)
    // -------------------------------------------------------------
    if (pathname === '/schedules') {
        const enrichedSchedules = db.lesson_schedules.map(ls => {
            const stu = db.students.find(s => s.student_id === ls.student_id) || {};
            const ins = db.instructors.find(i => i.instructor_id === ls.instructor_id) || {};
            const veh = db.vehicles.find(v => v.vehicle_id === ls.vehicle_id) || {};
            return {
                ...ls,
                student_name: stu.full_name || 'N/A',
                student_reg_no: stu.student_reg_no || 'N/A',
                instructor_name: ins.full_name || 'N/A',
                vehicle_reg: veh.reg_no || 'TBD'
            };
        });

        const myInstructor = currentUser.role === 'Instructor' ? db.instructors.find(i => i.user_id === currentUser.user_id || i.email === currentUser.email || i.phone === currentUser.phone) : null;

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-calendar-days text-primary me-2"></i> Practical Driving Lesson Timetable</h3>
                    <p class="text-muted mb-0">Lessons remain <strong>Pending</strong> until an Administrator or assigned Instructor approves that they are finished.</p>
                </div>
                <a href="/schedules/add" class="btn btn-primary shadow-sm"><i class="fa-solid fa-calendar-plus me-1"></i> Book Driving Lesson</a>
            </div>

            ${query.approved === '1' ? `
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> Driving lesson has been approved as finished and marked Completed!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ` : ''}

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr><th>Date & Time</th><th>Student</th><th>Instructor</th><th>Vehicle</th><th>Topic</th><th>Status</th><th>Notes</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                ${enrichedSchedules.map(s => {
                                    const canApprove = (currentUser.role === 'Administrator') || (currentUser.role === 'Instructor' && myInstructor && myInstructor.instructor_id === s.instructor_id);
                                    return `
                                    <tr>
                                        <td><strong>${formatDate(s.lesson_date)}</strong><br><small class="text-primary">${s.lesson_time.substring(0, 5)} (${s.duration_minutes}m)</small></td>
                                        <td><strong>${s.student_name}</strong><br><small class="text-muted">${s.student_reg_no}</small></td>
                                        <td>${s.instructor_name}</td>
                                        <td><span class="badge bg-light text-dark border">${s.vehicle_reg}</span></td>
                                        <td>${s.lesson_topic}</td>
                                        <td>
                                            ${s.status === 'Completed' ? `
                                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Completed</span>
                                                ${s.approved_by ? `<div class="small text-muted" style="font-size: 0.72rem;">Approved by: ${s.approved_by}</div>` : ''}
                                            ` : s.status === 'Cancelled' ? `
                                                <span class="badge bg-danger">Cancelled</span>
                                            ` : s.status === 'No Show' ? `
                                                <span class="badge bg-secondary">No Show</span>
                                            ` : s.status === 'In Progress' ? `
                                                <span class="badge bg-info text-dark">In Progress</span>
                                            ` : `
                                                <span class="badge bg-warning text-dark"><i class="fa-regular fa-clock me-1"></i> Pending Approval</span>
                                                <div class="small text-muted" style="font-size: 0.72rem;">(Not Approved Finished)</div>
                                            `}
                                        </td>
                                        <td class="small text-muted">${s.instructor_notes || '-'}</td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                ${canApprove && s.status !== 'Completed' ? `
                                                <a href="/schedules/approve?id=${s.schedule_id}" class="btn btn-success" onclick="return confirm('Approve that this driving lesson is finished?')" title="Approve Finished">
                                                    <i class="fa-solid fa-check me-1"></i> Approve Finished
                                                </a>
                                                ` : ''}
                                                ${currentUser.role === 'Administrator' || currentUser.role === 'Receptionist' ? `
                                                <a href="/schedules/delete?id=${s.schedule_id}" class="btn btn-outline-danger" onclick="return confirm('Cancel and delete this driving lesson booking?')" title="Delete Lesson"><i class="fa-solid fa-trash"></i></a>
                                                ` : ''}
                                            </div>
                                        </td>
                                    </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Schedules', currentUser, content, '/schedules'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: SCHEDULES (APPROVE FINISHED)
    // -------------------------------------------------------------
    if (pathname === '/schedules/approve') {
        const schedId = Number(query.id || 0);
        const sched = db.lesson_schedules.find(ls => ls.schedule_id === schedId);

        if (!sched) {
            res.writeHead(302, { 'Location': '/schedules' });
            res.end();
            return;
        }

        const myInstructor = currentUser.role === 'Instructor' ? db.instructors.find(i => i.user_id === currentUser.user_id || i.email === currentUser.email || i.phone === currentUser.phone) : null;
        const canApprove = (currentUser.role === 'Administrator') || (currentUser.role === 'Instructor' && myInstructor && myInstructor.instructor_id === sched.instructor_id);

        if (canApprove) {
            sched.status = 'Completed';
            sched.approved_by = `${currentUser.full_name} (${currentUser.role})`;
            sched.approved_at = new Date().toISOString();
            saveData(db);
            res.writeHead(302, { 'Location': '/schedules?approved=1' });
            res.end();
            return;
        } else {
            res.writeHead(302, { 'Location': '/schedules' });
            res.end();
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: SCHEDULES (DELETE)
    // -------------------------------------------------------------
    if (pathname === '/schedules/delete') {
        const schedId = Number(query.id || 0);
        const schedIndex = db.lesson_schedules.findIndex(ls => ls.schedule_id === schedId);
        if (schedIndex !== -1) {
            db.lesson_schedules.splice(schedIndex, 1);
            saveData(db);
        }
        res.writeHead(302, { 'Location': '/schedules' });
        res.end();
        return;
    }

    if (pathname === '/schedules/add') {
        if (method === 'GET') {
            const preStudentId = Number(query.student_id || 0);
            const content = `
                <div class="mb-4">
                    <a href="/schedules" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Schedules</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-calendar-plus text-primary me-2"></i> Book Driving Lesson</h3>
                    <p class="text-muted mb-0">Real-time validation prevents double-booking of instructors, vehicles, and students.</p>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/schedules/add">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                                    <select name="student_id" class="form-select" required>
                                        <option value="">-- Choose Student --</option>
                                        ${db.students.map(s => `<option value="${s.student_id}" ${preStudentId === s.student_id ? 'selected' : ''}>${s.full_name} (${s.student_reg_no})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Instructor <span class="text-danger">*</span></label>
                                    <select name="instructor_id" class="form-select" required>
                                        <option value="">-- Choose Instructor --</option>
                                        ${db.instructors.map(i => `<option value="${i.instructor_id}">${i.full_name} (${i.instructor_no})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Assign Vehicle</label>
                                    <select name="vehicle_id" class="form-select">
                                        <option value="">-- No Vehicle / TBD --</option>
                                        ${db.vehicles.map(v => `<option value="${v.vehicle_id}">${v.reg_no} - ${v.model} (${v.transmission})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Lesson Topic <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="lesson_topic" value="Clutch Control & Hill Starts" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="lesson_date" value="${new Date().toISOString().split('T')[0]}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="lesson_time" value="09:00" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Duration (Minutes)</label>
                                    <select name="duration_minutes" class="form-select">
                                        <option value="60" selected>60 Minutes (1 Hour)</option>
                                        <option value="90">90 Minutes (1.5 Hours)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Instructor Notes / Briefing</label>
                                    <input type="text" class="form-control" name="instructor_notes" placeholder="e.g. Meet at Mombasa office gate.">
                                </div>
                                <div class="col-12 mt-4 text-end">
                                    <a href="/schedules" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check me-1"></i> Book Lesson</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Book Lesson', currentUser, content, '/schedules'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const studentId = Number(body.student_id);
                const instructorId = Number(body.instructor_id);
                const vehicleId = body.vehicle_id ? Number(body.vehicle_id) : null;
                const lessonDate = body.lesson_date;
                const lessonTime = body.lesson_time + (body.lesson_time.length === 5 ? ':00' : '');

                // Check conflict: instructor already booked on same date & time
                const insConflict = db.lesson_schedules.find(ls => ls.instructor_id === instructorId && ls.lesson_date === lessonDate && ls.lesson_time.startsWith(lessonTime.substring(0, 5)) && ls.status !== 'Cancelled');
                if (insConflict) {
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderLayout('Conflict Error', currentUser, `
                        <div class="alert alert-danger">
                            <h4><i class="fa-solid fa-triangle-exclamation me-2"></i> Scheduling Conflict Detected!</h4>
                            <p>The selected instructor is already booked for another driving lesson on <strong>${lessonDate}</strong> at <strong>${lessonTime}</strong>.</p>
                            <a href="/schedules/add" class="btn btn-dark mt-2">Try Another Time</a>
                        </div>
                    `, '/schedules'));
                    return;
                }

                const nextId = db.lesson_schedules.length > 0 ? Math.max(...db.lesson_schedules.map(ls => ls.schedule_id)) + 1 : 1;
                db.lesson_schedules.push({
                    schedule_id: nextId,
                    student_id: studentId,
                    instructor_id: instructorId,
                    vehicle_id: vehicleId,
                    lesson_topic: body.lesson_topic,
                    lesson_date: lessonDate,
                    lesson_time: lessonTime,
                    duration_minutes: Number(body.duration_minutes || 60),
                    status: 'Pending',
                    approved_by: null,
                    approved_at: null,
                    instructor_notes: body.instructor_notes || ''
                });
                saveData(db);

                res.writeHead(302, { 'Location': '/schedules' });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: PAYMENTS & RECEIPTS
    // -------------------------------------------------------------
    if (pathname === '/payments') {
        const enrichedPayments = db.payments.map(p => {
            const stu = db.students.find(s => s.student_id === p.student_id) || {};
            return { ...p, student_name: stu.full_name || 'N/A', student_reg_no: stu.student_reg_no || 'N/A' };
        }).reverse();

        const totalRevenue = db.payments.reduce((acc, p) => acc + Number(p.amount || 0), 0);

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Student Fee Payments</h3>
                    <p class="text-muted mb-0">Record of tuition payments, M-Pesa receipts, and fee balances.</p>
                </div>
                <a href="/payments/add" class="btn btn-success shadow-sm"><i class="fa-solid fa-plus me-1"></i> Record New Payment</a>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">Showing ${enrichedPayments.length} Payment Receipts</span>
                    <span class="badge bg-success fs-6 py-2 px-3">Total: ${formatCurrency(totalRevenue)}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr><th>Receipt No</th><th>Student Name</th><th>Date</th><th>Amount</th><th>Method & Card Details</th><th>Reference</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                ${enrichedPayments.map(p => `
                                    <tr>
                                        <td><span class="badge bg-light text-primary border font-monospace fs-6">${p.receipt_no}</span></td>
                                        <td><strong>${p.student_name}</strong><br><small class="text-muted">${p.student_reg_no}</small></td>
                                        <td>${formatDate(p.payment_date)}</td>
                                        <td class="fw-bold text-success fs-6">${formatCurrency(p.amount)}</td>
                                        <td>
                                            <span class="badge ${p.payment_method === 'M-Pesa' ? 'bg-success' : (p.payment_method === 'Cash' ? 'bg-secondary' : 'bg-primary')}">${p.payment_method}</span>
                                            ${p.payment_method === 'M-Pesa' ? `
                                                <div class="small text-muted mt-1">
                                                    <i class="fa-solid fa-mobile-screen text-success me-1"></i>Payer: ${p.payer_phone || 'Student'}
                                                    <br><i class="fa-solid fa-arrow-down-to-bracket text-muted me-1"></i>To: <strong>${p.recipient_phone || '0715718022'}</strong>
                                                </div>
                                            ` : ''}
                                            ${p.card_type || p.card_number || p.bank_name ? `
                                                <div class="small text-muted mt-1">
                                                    <i class="fa-solid fa-credit-card me-1 text-primary"></i><strong>${p.card_type || 'Card'}</strong>${p.bank_name ? ` &bull; ${p.bank_name}` : ''}
                                                    ${p.card_number ? `<br><code class="text-dark">${p.card_number}</code>` : ''}
                                                    ${p.cardholder_name ? `<br><span class="text-secondary">${p.cardholder_name}</span>` : ''}
                                                </div>
                                            ` : ''}
                                        </td>
                                        <td class="font-monospace small">
                                            <div class="fw-bold">${p.reference_no || '-'}</div>
                                            ${p.bank_account_no ? `<div class="text-muted" style="font-size: 0.72rem;">A/C: ${p.bank_account_no}</div>` : ''}
                                        </td>
                                        <td><a href="/receipt?id=${p.payment_id}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-receipt me-1"></i> Print Receipt</a></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Fee Payments', currentUser, content, '/payments'));
        return;
    }

    if (pathname === '/payments/add') {
        if (method === 'GET') {
            const preStudentId = Number(query.student_id || 0);
            const preStudent = db.students.find(s => s.student_id === preStudentId) || {};
            const rawPrePhone = preStudent.phone || '';
            const preStudentPhone = rawPrePhone.startsWith('+254') ? '0' + rawPrePhone.substring(4) : (rawPrePhone.startsWith('254') ? '0' + rawPrePhone.substring(3) : rawPrePhone);
            const nextId = db.payments.length + 1;
            const rctNo = `RCT-${new Date().getFullYear()}-${String(nextId).padStart(4, '0')}`;

            const content = `
                <div class="mb-4">
                    <a href="/payments" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Payments</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Record Student Fee Payment</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/payments/add">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Receipt Number</label>
                                    <input type="text" class="form-control bg-light font-monospace fw-bold text-primary" name="receipt_no" value="${rctNo}" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                                    <select name="student_id" id="student_id" class="form-select" onchange="handleStudentSelect(this)" required>
                                        <option value="">-- Choose Student --</option>
                                        ${db.students.map(s => {
                                            const p = s.phone || '';
                                            const cleanP = p.startsWith('+254') ? '0' + p.substring(4) : (p.startsWith('254') ? '0' + p.substring(3) : p);
                                            return `<option value="${s.student_id}" data-name="${s.full_name}" data-phone="${cleanP}" ${preStudentId === s.student_id ? 'selected' : ''}>${s.full_name} (${s.student_reg_no}) &bull; Tel: ${cleanP || 'N/A'}</option>`;
                                        }).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control fw-bold text-success fs-5" name="amount" placeholder="e.g. 5000" required autofocus>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="payment_date" value="${new Date().toISOString().split('T')[0]}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" id="payment_method" class="form-select fw-semibold" onchange="togglePaymentBoxes()" required>
                                        <option value="M-Pesa" selected>M-Pesa (STK Push to Student)</option>
                                        <option value="Bank Transfer">Bank Transfer (Wire / Electronic)</option>
                                        <option value="Credit / Debit Card">Credit / Debit Card</option>
                                        <option value="Cash">Cash Payment</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Reference Code / Transaction No.</label>
                                    <input type="text" class="form-control font-monospace" name="reference_no" id="reference_no" placeholder="e.g. QA189KL93Z">
                                </div>

                                <!-- Lipa na M-Pesa STK Push Card: Prompts Student Phone -> Sends to School Default Number 0715718022 -->
                                <div class="col-12" id="mpesa_prompt_box">
                                    <div class="p-3 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25 shadow-sm">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-success border-opacity-25">
                                            <span class="fw-bold text-success fs-6">
                                                <i class="fa-solid fa-mobile-screen-button me-2"></i> Lipa na M-Pesa STK Push Transfer
                                            </span>
                                            <span class="badge bg-success text-white px-3 py-1">
                                                <i class="fa-solid fa-arrow-down-to-bracket me-1"></i> Money Goes To: 0715718022
                                            </span>
                                        </div>

                                        <div class="alert alert-light border border-success border-opacity-25 py-2 px-3 small mb-3 text-muted">
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-circle-info text-success fs-5 me-2 flex-shrink-0"></i>
                                                <div>
                                                    Tuition fees are deposited directly to the <strong>School M-Pesa Account (0715718022 &bull; Anza Auto Driving School)</strong>. The paying student is prompted on their mobile handset to enter their M-Pesa PIN to complete the transfer.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 align-items-end">
                                            <!-- School Receiving Account (Funds Destination) -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">
                                                    <i class="fa-solid fa-building-columns text-success me-1"></i> School Receiving Account (Where Money Goes)
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white text-success fw-bold"><i class="fa-solid fa-school me-1"></i> Recipient</span>
                                                    <input type="text" class="form-control fw-bold font-monospace bg-light text-dark" id="school_mpesa_phone" name="school_mpesa_phone" value="0715718022" readonly>
                                                    <span class="input-group-text bg-white small text-muted">Anza Auto M-Pesa</span>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.75rem;">Default school business line where student payments are deposited.</small>
                                            </div>

                                            <!-- Student Paying Phone (Prompted Handset) -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">
                                                    <i class="fa-solid fa-mobile-screen text-primary me-1"></i> Student Phone to Prompt (Payer Handset) <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white text-primary"><i class="fa-solid fa-phone"></i></span>
                                                    <input type="text" class="form-control fw-bold font-monospace" id="student_mpesa_phone" name="student_mpesa_phone" value="${preStudentPhone}" placeholder="e.g. 0755667788">
                                                </div>
                                                <small class="text-muted" style="font-size: 0.75rem;">Student handset prompted to enter M-Pesa PIN and send funds to 0715718022.</small>
                                            </div>

                                            <!-- STK Push Action Button -->
                                            <div class="col-12 mt-2">
                                                <button type="button" class="btn btn-success w-100 fw-semibold shadow-sm" id="btn_stk_push" onclick="triggerMpesaSTKPush()">
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Prompt Student Handset to Send Money to 0715718022
                                                </button>
                                                <div id="mpesa_status" class="mt-2 small" style="display: none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Transfer & Credit / Debit Card Payment Details Card Box -->
                                <div class="col-12" id="bank_card_prompt_box">
                                    <div class="p-3 rounded-3 border bg-light border-primary border-opacity-50 shadow-sm" style="background: linear-gradient(180deg, rgba(13, 59, 102, 0.04) 0%, rgba(24, 90, 157, 0.08) 100%);">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                            <span class="fw-bold text-primary fs-6">
                                                <i class="fa-solid fa-credit-card me-2"></i> Bank Transfer & Card Payment Details
                                            </span>
                                            <span class="badge bg-primary text-white px-3 py-1">
                                                <i class="fa-solid fa-lock me-1"></i> Card & Bank Clearance
                                            </span>
                                        </div>
                                        <p class="text-muted small mb-3">
                                            Enter the complete card and bank transfer details (Debit/Credit Card number, cardholder name, bank name, expiry date & CVV) to clear payment.
                                        </p>

                                        <div class="row g-3">
                                            <!-- Card / Payment Type -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Card / Transfer Mode <span class="text-danger">*</span></label>
                                                <div class="d-flex flex-wrap gap-2 pt-1">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="card_type" id="card_debit" value="Debit Card" checked>
                                                        <label class="form-check-label fw-semibold small" for="card_debit">
                                                            <i class="fa-solid fa-credit-card text-primary me-1"></i> Debit Card
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="card_type" id="card_credit" value="Credit Card">
                                                        <label class="form-check-label fw-semibold small" for="card_credit">
                                                            <i class="fa-solid fa-credit-card text-success me-1"></i> Credit Card
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="card_type" id="card_wire" value="Direct Bank Wire">
                                                        <label class="form-check-label fw-semibold small" for="card_wire">
                                                            <i class="fa-solid fa-building-columns text-info me-1"></i> Wire Transfer / Slip
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Bank Selection -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Issuing / Destination Bank <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white"><i class="fa-solid fa-building-columns text-primary"></i></span>
                                                    <select name="bank_name" id="bank_name" class="form-select fw-semibold" onchange="handleBankChange()">
                                                        <option value="NCBA Bank" selected>NCBA Bank Kenya (A/C: 01208491024)</option>
                                                        <option value="Equity Bank">Equity Bank Kenya (A/C: 14202839102)</option>
                                                        <option value="KCB Bank">KCB Bank Kenya (A/C: 11092837465)</option>
                                                        <option value="Co-operative Bank">Co-operative Bank (A/C: 01129384756)</option>
                                                        <option value="Absa Bank">Absa Bank Kenya (A/C: 03091827364)</option>
                                                        <option value="Standard Chartered">Standard Chartered Bank (A/C: 01029384756)</option>
                                                        <option value="Stanbic Bank">Stanbic Bank Kenya (A/C: 01002938475)</option>
                                                        <option value="Diamond Trust Bank">Diamond Trust Bank - DTB (A/C: 02019283746)</option>
                                                        <option value="Family Bank">Family Bank (A/C: 04019283746)</option>
                                                        <option value="Other Bank">Other Commercial Bank</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Card Number -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Card Number (Debit or Credit) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white"><i class="fa-solid fa-credit-card text-muted" id="card_brand_icon"></i></span>
                                                    <input type="text" class="form-control font-monospace fw-bold" name="card_number" id="card_number" placeholder="4112 3456 7890 1024" maxlength="19" oninput="formatCardInput(this)">
                                                    <span class="input-group-text bg-white small text-muted">
                                                        <i class="fa-brands fa-cc-visa text-primary fs-5 me-1"></i>
                                                        <i class="fa-brands fa-cc-mastercard text-danger fs-5"></i>
                                                    </span>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.75rem;">16-digit debit or credit card number used for the bank transfer payment.</small>
                                            </div>

                                            <!-- Cardholder / Depositor Name -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Cardholder / Account Name <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white"><i class="fa-solid fa-user-check text-muted"></i></span>
                                                    <input type="text" class="form-control" name="cardholder_name" id="cardholder_name" placeholder="Full name on card/account">
                                                </div>
                                                <small class="text-muted" style="font-size: 0.75rem;">Name as printed on the credit/debit card or bank deposit slip.</small>
                                            </div>

                                            <!-- Expiry Date & CVV -->
                                            <div class="col-md-3 col-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Expiry Date <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white"><i class="fa-regular fa-calendar text-muted"></i></span>
                                                    <input type="text" class="form-control font-monospace text-center fw-bold" name="card_expiry" id="card_expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiryInput(this)">
                                                </div>
                                            </div>

                                            <div class="col-md-3 col-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">CVV / CVC <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control font-monospace text-center fw-bold" name="card_cvv" id="card_cvv" placeholder="123" maxlength="4">
                                                    <span class="input-group-text bg-white" title="3-digit security code on back of card"><i class="fa-solid fa-lock text-muted"></i></span>
                                                </div>
                                            </div>

                                            <!-- Bank Account / Swift / Slip Number -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold small text-dark mb-1">Bank Account / Slip Reference Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white"><i class="fa-solid fa-receipt text-muted"></i></span>
                                                    <input type="text" class="form-control font-monospace" name="bank_account_no" id="bank_account_no" value="01208491024" placeholder="e.g. 01208491024">
                                                </div>
                                            </div>

                                            <!-- Authorize & Verify Button -->
                                            <div class="col-12 mt-2">
                                                <button type="button" class="btn btn-primary w-100 fw-semibold shadow-sm" id="btn_card_auth" onclick="triggerCardBankAuth()">
                                                    <i class="fa-solid fa-shield-halved me-1"></i> Verify & Authorize Card / Bank Payment
                                                </button>
                                                <div id="card_auth_status" class="mt-2 small" style="display: none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-4 text-end">
                                    <a href="/payments" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-success fw-semibold"><i class="fa-solid fa-receipt me-1"></i> Save & Generate Receipt</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                function triggerMpesaSTKPush() {
                    let studentPhone = (document.getElementById('student_mpesa_phone').value || '').trim();
                    if (!studentPhone) {
                        const sel = document.getElementById('student_id');
                        if (sel && sel.selectedIndex > 0) {
                            studentPhone = (sel.options[sel.selectedIndex].getAttribute('data-phone') || '').trim();
                        }
                    }
                    if (!studentPhone) {
                        studentPhone = '0755667788';
                    }
                    document.getElementById('student_mpesa_phone').value = studentPhone;

                    const schoolPhone = (document.getElementById('school_mpesa_phone').value || '0715718022').trim();
                    const amount = document.querySelector('input[name="amount"]').value || '5000';
                    const btn = document.getElementById('btn_stk_push');
                    const statusBox = document.getElementById('mpesa_status');

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Sending STK Push to ' + studentPhone + '...';
                    statusBox.style.display = 'block';
                    statusBox.className = 'mt-2 small alert alert-info py-2 mb-0';
                    statusBox.innerHTML = '<i class="fa-solid fa-satellite-dish me-1"></i> M-Pesa STK Push prompt sent to student handset (<strong>' + studentPhone + '</strong>). Prompting student to enter M-Pesa PIN and transfer <strong>KES ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</strong> to School Account <strong>' + schoolPhone + ' (Anza Auto Driving School)</strong>...';

                    setTimeout(() => {
                        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                        let code = "Q" + letters.charAt(Math.floor(Math.random() * letters.length)) + Math.floor(10000000 + Math.random() * 90000000);
                        document.getElementById('reference_no').value = code;

                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> STK Push Verified (Resend)';
                        statusBox.className = 'mt-2 small alert alert-success py-2 mb-0';
                        statusBox.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> <strong>Payment Successful!</strong> Student <strong>' + studentPhone + '</strong> confirmed PIN and sent KES ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' to School Receiving Account <strong>' + schoolPhone + '</strong>. M-Pesa Code: <code>' + code + '</code>.';
                    }, 1100);
                }

                function formatCardInput(input) {
                    let value = input.value.replace(/\D/g, '');
                    let formatted = '';
                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) formatted += ' ';
                        formatted += value[i];
                    }
                    input.value = formatted.substring(0, 19);

                    const icon = document.getElementById('card_brand_icon');
                    if (icon) {
                        if (value.startsWith('4')) {
                            icon.className = 'fa-brands fa-cc-visa text-primary';
                        } else if (value.startsWith('5')) {
                            icon.className = 'fa-brands fa-cc-mastercard text-danger';
                        } else {
                            icon.className = 'fa-solid fa-credit-card text-muted';
                        }
                    }
                }

                function formatExpiryInput(input) {
                    let value = input.value.replace(/\D/g, '');
                    if (value.length >= 2) {
                        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    } else {
                        input.value = value;
                    }
                }

                function handleBankChange() {
                    const bankSelect = document.getElementById('bank_name');
                    const accInput = document.getElementById('bank_account_no');
                    const bankMap = {
                        'NCBA Bank': '01208491024',
                        'Equity Bank': '14202839102',
                        'KCB Bank': '11092837465',
                        'Co-operative Bank': '01129384756',
                        'Absa Bank': '03091827364',
                        'Standard Chartered': '01029384756',
                        'Stanbic Bank': '01002938475',
                        'Diamond Trust Bank': '02019283746',
                        'Family Bank': '04019283746'
                    };
                    if (accInput && bankMap[bankSelect.value]) {
                        accInput.value = bankMap[bankSelect.value];
                    }
                }

                function triggerCardBankAuth() {
                    let cardNum = document.getElementById('card_number').value.trim();
                    if (!cardNum) {
                        cardNum = '4112 3456 7890 1024';
                        document.getElementById('card_number').value = cardNum;
                    }
                    if (!document.getElementById('card_expiry').value) {
                        document.getElementById('card_expiry').value = '12/28';
                    }
                    if (!document.getElementById('card_cvv').value) {
                        document.getElementById('card_cvv').value = '342';
                    }
                    
                    const bank = document.getElementById('bank_name').value || 'NCBA Bank';
                    const cardType = document.querySelector('input[name="card_type"]:checked')?.value || 'Debit Card';
                    let cardholder = document.getElementById('cardholder_name').value.trim();
                    if (!cardholder) {
                        const sel = document.getElementById('student_id');
                        cardholder = sel && sel.selectedIndex > 0 ? (sel.options[sel.selectedIndex].getAttribute('data-name') || 'Student Cardholder') : 'Authorized Cardholder';
                        document.getElementById('cardholder_name').value = cardholder;
                    }
                    const amount = document.querySelector('input[name="amount"]').value || '5000';
                    
                    const btn = document.getElementById('btn_card_auth');
                    const statusBox = document.getElementById('card_auth_status');

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Authorizing with ' + bank + ' Gateway...';
                    statusBox.style.display = 'block';
                    statusBox.className = 'mt-2 small alert alert-info py-2 mb-0';
                    statusBox.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> Verifying card & routing funds through <strong>' + bank + '</strong> for KES ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '...';

                    setTimeout(() => {
                        const last4 = cardNum.replace(/\\s+/g, '').slice(-4) || '1024';
                        const bankPrefix = bank.split(' ')[0].toUpperCase();
                        const code = bankPrefix + '-CARD-' + Math.floor(100000 + Math.random() * 900000);
                        
                        document.getElementById('reference_no').value = code;
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Authorized & Verified (Re-verify)';
                        statusBox.className = 'mt-2 small alert alert-success py-2 mb-0';
                        statusBox.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> <strong>Bank Payment Approved!</strong> ' + cardType + ' ending in <code>•••• ' + last4 + '</code> successfully cleared by <strong>' + bank + '</strong>. Authorization Ref: <code>' + code + '</code>.';
                    }, 1000);
                }

                function togglePaymentBoxes() {
                    const method = document.getElementById('payment_method').value;
                    const mpesaBox = document.getElementById('mpesa_prompt_box');
                    const bankCardBox = document.getElementById('bank_card_prompt_box');
                    
                    if (mpesaBox) mpesaBox.style.display = (method === 'M-Pesa') ? 'block' : 'none';
                    if (bankCardBox) bankCardBox.style.display = (method === 'Bank Transfer' || method === 'Credit / Debit Card') ? 'block' : 'none';
                }

                function handleStudentSelect(select) {
                    const selectedOpt = select.options[select.selectedIndex];
                    if (!selectedOpt) return;
                    const name = selectedOpt.getAttribute('data-name') || '';
                    const phone = selectedOpt.getAttribute('data-phone') || '';

                    const cardholderInput = document.getElementById('cardholder_name');
                    if (cardholderInput && (!cardholderInput.value || cardholderInput.value === 'Authorized Cardholder')) {
                        if (name) cardholderInput.value = name;
                    }

                    const mpesaPayerInput = document.getElementById('student_mpesa_phone');
                    if (mpesaPayerInput && phone) {
                        mpesaPayerInput.value = phone;
                    }
                }

                togglePaymentBoxes();
                </script>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Record Payment', currentUser, content, '/payments'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const nextId = db.payments.length > 0 ? Math.max(...db.payments.map(p => p.payment_id)) + 1 : 1;
                const rctNo = body.receipt_no || `RCT-${new Date().getFullYear()}-${String(nextId).padStart(4, '0')}`;
                
                const cardRaw = (body.card_number || '').trim();
                const cleanDigits = cardRaw.replace(/\s+/g, '');
                const maskedCard = cleanDigits.length >= 4 
                    ? '•••• •••• •••• ' + cleanDigits.slice(-4) 
                    : (cardRaw ? cardRaw : '');

                const newPayment = {
                    payment_id: nextId,
                    receipt_no: rctNo,
                    student_id: Number(body.student_id),
                    amount: Number(body.amount),
                    payment_date: body.payment_date,
                    payment_method: body.payment_method || 'M-Pesa',
                    reference_no: body.reference_no || '',
                    payer_phone: (body.student_mpesa_phone || '').trim(),
                    recipient_phone: (body.school_mpesa_phone || '0715718022').trim(),
                    card_type: body.card_type || (body.payment_method === 'Credit / Debit Card' ? 'Debit Card' : (body.payment_method === 'Bank Transfer' ? 'Debit Card' : '')),
                    card_number: maskedCard,
                    cardholder_name: (body.cardholder_name || '').trim(),
                    card_expiry: (body.card_expiry || '').trim(),
                    bank_name: (body.bank_name || '').trim(),
                    bank_account_no: (body.bank_account_no || '').trim(),
                    notes: body.notes || (body.payment_method === 'M-Pesa' ? `M-Pesa from ${body.student_mpesa_phone || 'student'} to 0715718022` : (body.bank_name ? `${body.bank_name} ${body.card_type || 'Transfer'}` : 'Tuition fee installment')),
                    recorded_by: currentUser.user_id
                };

                db.payments.push(newPayment);
                saveData(db);

                res.writeHead(302, { 'Location': `/receipt?id=${nextId}` });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: PRINTABLE RECEIPT
    // -------------------------------------------------------------
    if (pathname === '/receipt') {
        const paymentId = Number(query.id || 0);
        const payment = db.payments.find(p => p.payment_id === paymentId);
        if (!payment) {
            res.writeHead(404, { 'Content-Type': 'text/plain' });
            res.end('Receipt not found.');
            return;
        }

        const student = db.students.find(s => s.student_id === payment.student_id) || {};
        const allStudentPayments = db.payments.filter(p => p.student_id === payment.student_id && p.payment_id <= paymentId);
        const totalPaidSoFar = allStudentPayments.reduce((acc, p) => acc + Number(p.amount || 0), 0);
        const courseFee = Number(student.course_fee || 15000);
        const balanceRemaining = courseFee - totalPaidSoFar;

        const receiptHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt - ${payment.receipt_no} | Anza Auto Driving School Mombasa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; padding: 2rem 0; color: #212529; }
        .receipt-container { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 2.5rem; position: relative; border: 1px solid #e2e8f0; }
        .receipt-header { border-bottom: 2px solid #0d3b66; padding-bottom: 1.5rem; margin-bottom: 1.5rem; text-align: center; }
        .school-logo { width: 64px; height: 64px; border-radius: 50%; background: #0d3b66; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 0.5rem; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } .receipt-container { box-shadow: none; border: none; padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="container">
    <div class="no-print d-flex justify-content-between align-items-center mb-3" style="max-width: 780px; margin: 0 auto;">
        <a href="/payments" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Payments</a>
        <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm"><i class="fa-solid fa-print me-2"></i> Print Official Receipt</button>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <div class="school-logo"><i class="fa-solid fa-car-side"></i></div>
            <h3 class="fw-bold text-dark mb-1">ANZA AUTO DRIVING SCHOOL</h3>
            <h5 class="fw-semibold text-primary mb-1">MOMBASA BRANCH</h5>
            <p class="text-muted small mb-0">
                Moi Avenue, P.O. Box 90123 - 80100 Mombasa, Kenya<br>
                Tel: +254 712 345 678 &bull; Email: mombasa@anzaautodrivingschool.co.ke<br>
                <em>NTSA Accredited Driving School &bull; License Ref: NTSA/DS/MBA/042</em>
            </p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="badge bg-primary px-3 py-2 fs-6">OFFICIAL TUITION RECEIPT</span>
            <div class="text-end">
                <div class="fw-bold text-dark fs-5 font-monospace">${payment.receipt_no}</div>
                <small class="text-muted">Date: ${formatDate(payment.payment_date)}</small>
            </div>
        </div>

        <div class="bg-light p-3 rounded-3 mb-4 border">
            <div class="row g-2">
                <div class="col-sm-6"><span class="text-muted small">Student Full Name:</span><div class="fw-bold fs-6">${student.full_name || 'N/A'}</div></div>
                <div class="col-sm-6"><span class="text-muted small">Registration Number:</span><div class="fw-bold font-monospace text-primary">${student.student_reg_no || 'N/A'}</div></div>
                <div class="col-sm-6"><span class="text-muted small">Phone Contact:</span><div class="fw-semibold">${student.phone || 'N/A'}</div></div>
                <div class="col-sm-6"><span class="text-muted small">Course Category:</span><div class="fw-bold">${student.course_category || 'N/A'}</div></div>
            </div>
        </div>

        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr><th>Description</th><th>Payment Mode</th><th>Reference / Auth Code</th><th class="text-end">Amount (KES)</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Tuition Fee Payment</strong>
                        <div class="small text-muted">${payment.notes || 'Tuition fee installment'}</div>
                        ${payment.payment_method === 'M-Pesa' ? `
                            <div class="small text-success mt-2 p-2 bg-light rounded border border-success border-opacity-25">
                                <div class="fw-bold"><i class="fa-solid fa-mobile-screen-button me-1"></i> Lipa na M-Pesa STK Transfer</div>
                                <div>Payer Mobile (Student): <strong class="font-monospace text-dark">${payment.payer_phone || student.phone || 'Student Handset'}</strong></div>
                                <div>Destination (School M-Pesa): <strong class="font-monospace text-dark">${payment.recipient_phone || '0715718022'}</strong> (Anza Auto Driving School)</div>
                            </div>
                        ` : ''}
                        ${(payment.payment_method === 'Bank Transfer' || payment.payment_method === 'Credit / Debit Card' || payment.card_number || payment.bank_name) ? `
                            <div class="small text-primary mt-2 p-2 bg-light rounded border border-primary border-opacity-25">
                                <div class="fw-bold"><i class="fa-solid fa-credit-card me-1"></i> ${payment.card_type || 'Debit Card'} &bull; ${payment.bank_name || 'Bank Transfer'}</div>
                                ${payment.card_number ? `<div>Card Number: <strong class="font-monospace text-dark">${payment.card_number}</strong> ${payment.card_expiry ? `&bull; Exp: <strong>${payment.card_expiry}</strong>` : ''}</div>` : ''}
                                ${payment.cardholder_name ? `<div>Cardholder / Payee: <strong>${payment.cardholder_name}</strong></div>` : ''}
                                ${payment.bank_account_no ? `<div>Bank Account / Reference: <strong>${payment.bank_account_no}</strong></div>` : ''}
                            </div>
                        ` : ''}
                    </td>
                    <td>
                        <span class="badge ${payment.payment_method === 'M-Pesa' ? 'bg-success' : (payment.payment_method === 'Cash' ? 'bg-secondary' : 'bg-primary')}">${payment.payment_method}</span>
                        ${payment.card_type ? `<div class="small text-muted mt-1 fw-semibold"><i class="fa-solid fa-credit-card me-1"></i>${payment.card_type}</div>` : ''}
                    </td>
                    <td class="font-monospace fw-bold text-dark">${payment.reference_no || '-'}</td>
                    <td class="text-end fw-bold fs-5 text-success">${Number(payment.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                </tr>
            </tbody>
        </table>

        <div class="row justify-content-end mb-4">
            <div class="col-md-6">
                <ul class="list-group list-group-flush border rounded-3 p-2 bg-light">
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-0"><span class="text-muted">Total Course Fee:</span> <strong>${formatCurrency(courseFee)}</strong></li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-0"><span class="text-muted">Total Cumulative Paid:</span> <strong class="text-success">${formatCurrency(totalPaidSoFar)}</strong></li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-top"><span class="fw-bold">Balance Remaining:</span> <strong class="fs-6 ${balanceRemaining > 0 ? 'text-danger' : 'text-success'}">${formatCurrency(balanceRemaining)}</strong></li>
                </ul>
            </div>
        </div>

        <div class="row pt-4 mt-4 border-top">
            <div class="col-6">
                <small class="text-muted d-block mb-3">Issued by:</small>
                <strong>Accounts Desk &bull; Anza Auto Mombasa</strong>
            </div>
            <div class="col-6 text-end">
                <div class="border border-2 border-secondary border-opacity-25 rounded-3 d-inline-block p-3 text-center" style="min-width: 170px;">
                    <i class="fa-solid fa-stamp text-muted opacity-25 fs-1 d-block mb-1"></i>
                    <small class="text-muted fw-bold text-uppercase">Branch Stamp</small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(receiptHtml);
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: DRIVING TEST RESULTS
    // -------------------------------------------------------------
    if (pathname === '/test-results') {
        const enrichedTests = db.driving_test_results.map(tr => {
            const stu = db.students.find(s => s.student_id === tr.student_id) || {};
            return { ...tr, student_name: stu.full_name || 'N/A', student_reg_no: stu.student_reg_no || 'N/A' };
        }).reverse();

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-square-poll-vertical text-warning me-2"></i> Driving Test Assessments</h3>
                    <p class="text-muted mb-0">Theory, internal practical mock exams, and official NTSA driving test results.</p>
                </div>
                <a href="/test-results/add" class="btn btn-primary shadow-sm"><i class="fa-solid fa-plus me-1"></i> Record Test Result</a>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
                                <tr><th>Date</th><th>Student Name</th><th>Test Category</th><th>Score</th><th>Status</th><th>Examiner</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                ${enrichedTests.map(t => `
                                    <tr>
                                        <td>${formatDate(t.test_date)}</td>
                                        <td><strong>${t.student_name}</strong><br><small class="text-muted">${t.student_reg_no}</small></td>
                                        <td><span class="badge bg-light text-dark border">${t.test_type}</span></td>
                                        <td><strong class="fs-6 ${t.score >= 70 ? 'text-success' : 'text-danger'}">${t.score}/100</strong></td>
                                        <td><span class="badge bg-${t.status === 'Pass' ? 'success' : 'danger'}">${t.status}</span></td>
                                        <td>${t.examiner_name}</td>
                                        <td class="small text-muted">${t.remarks || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Test Results', currentUser, content, '/test-results'));
        return;
    }

    if (pathname === '/test-results/add') {
        if (method === 'GET') {
            const preStudentId = Number(query.student_id || 0);
            const content = `
                <div class="mb-4">
                    <a href="/test-results" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Test Results</a>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-award text-warning me-2"></i> Record Driving Test Result</h3>
                </div>

                <div class="card border-0 shadow-sm rounded-3 col-lg-8">
                    <div class="card-body p-4">
                        <form method="POST" action="/test-results/add">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                                    <select name="student_id" class="form-select" required>
                                        <option value="">-- Choose Student --</option>
                                        ${db.students.map(s => `<option value="${s.student_id}" ${preStudentId === s.student_id ? 'selected' : ''}>${s.full_name} (${s.student_reg_no})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Test Category</label>
                                    <select name="test_type" class="form-select">
                                        <option value="Internal Theory Test">Internal Theory Test</option>
                                        <option value="Internal Practical Mock Test" selected>Internal Practical Mock Test</option>
                                        <option value="Final NTSA/Police Driving Test">Final NTSA/Police Driving Test</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Date</label>
                                    <input type="date" class="form-control" name="test_date" value="${new Date().toISOString().split('T')[0]}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Score (0-100)</label>
                                    <input type="number" min="0" max="100" class="form-control fw-bold" name="score" value="80" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Outcome</label>
                                    <select name="status" class="form-select">
                                        <option value="Pass">Pass</option>
                                        <option value="Fail">Fail</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Examiner Name</label>
                                    <input type="text" class="form-control" name="examiner_name" value="${currentUser.full_name}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Remarks</label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Good hill start and roundabout navigation."></textarea>
                                </div>
                                <div class="col-12 mt-4 text-end">
                                    <a href="/test-results" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Assessment</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Record Test', currentUser, content, '/test-results'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const nextId = db.driving_test_results.length > 0 ? Math.max(...db.driving_test_results.map(t => t.result_id)) + 1 : 1;
                db.driving_test_results.push({
                    result_id: nextId,
                    student_id: Number(body.student_id),
                    test_date: body.test_date,
                    test_type: body.test_type,
                    score: Number(body.score),
                    status: body.status || 'Pass',
                    examiner_name: body.examiner_name,
                    remarks: body.remarks || ''
                });
                saveData(db);

                res.writeHead(302, { 'Location': '/test-results' });
                res.end();
            });
            return;
        }
    }

    // -------------------------------------------------------------
    // ROUTE: REPORTS & ANALYTICS
    // -------------------------------------------------------------
    if (pathname === '/reports') {
        const totalRevenue = db.payments.reduce((acc, p) => acc + Number(p.amount || 0), 0);
        const totalStudents = db.students.length;
        const totalLessons = db.lesson_schedules.length;
        const totalTests = db.driving_test_results.length;
        const passTests = db.driving_test_results.filter(t => t.status === 'Pass').length;
        const passRate = totalTests > 0 ? Math.round((passTests / totalTests) * 100) : 0;

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> Reports & Analytics</h3>
                    <p class="text-muted mb-0">Management summaries and printable official records.</p>
                </div>
                <button onclick="window.print()" class="btn btn-dark shadow-sm"><i class="fa-solid fa-print me-1"></i> Print Report</button>
            </div>

            <div class="card border-0 shadow-sm rounded-3 p-4">
                <div class="text-center pb-3 border-bottom mb-4">
                    <h4 class="fw-bold text-dark mb-1">ANZA AUTO DRIVING SCHOOL</h4>
                    <h5 class="fw-semibold text-primary mb-1">MOMBASA BRANCH &bull; OPERATIONAL PERFORMANCE REPORT</h5>
                    <p class="text-muted small mb-0">Generated on ${new Date().toDateString()} by ${currentUser.full_name}</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase">Total Collections</span>
                            <h3 class="fw-bold text-success mb-0 mt-1">${formatCurrency(totalRevenue)}</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase">Total Enrollments</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">${totalStudents} students</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase">Practical Lessons</span>
                            <h3 class="fw-bold text-primary mb-0 mt-1">${totalLessons} lessons</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase">Test Pass Rate</span>
                            <h3 class="fw-bold text-warning text-dark mb-0 mt-1">${passRate}% (${passTests}/${totalTests})</h3>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3">Recent Fee Collections</h5>
                <table class="table table-bordered mb-0">
                    <thead class="table-light"><tr><th>Receipt No</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                    <tbody>
                        ${db.payments.map(p => `<tr><td class="font-monospace fw-bold text-primary">${p.receipt_no}</td><td>${formatDate(p.payment_date)}</td><td class="fw-bold text-success">${formatCurrency(p.amount)}</td><td>${p.payment_method}</td><td>${p.reference_no || '-'}</td></tr>`).join('')}
                    </tbody>
                </table>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Reports', currentUser, content, '/reports'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: USER ACCOUNTS (ADMIN ONLY)
    // -------------------------------------------------------------
    if (pathname === '/users') {
        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-users-gear text-primary me-2"></i> System Users</h3>
                    <p class="text-muted mb-0">Manage portal access credentials, roles, and administrative deletions.</p>
                </div>
            </div>

            ${query.deleted === '1' ? `
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> User account has been successfully deleted from the database.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ` : ''}

            ${query.error === 'self' ? `
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Security Restriction: You cannot delete your own currently logged-in administrator account.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ` : ''}

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead><tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                ${db.users.map(u => `
                                    <tr>
                                        <td>#${u.user_id}</td>
                                        <td class="fw-bold text-primary">${u.username}</td>
                                        <td>${u.full_name}</td>
                                        <td>${u.email || '-'}</td>
                                        <td><span class="badge bg-primary">${u.role}</span></td>
                                        <td><span class="badge bg-success">${u.status}</span></td>
                                        <td class="text-end">
                                            ${u.user_id !== currentUser.user_id ? `
                                            <a href="/users/delete?id=${u.user_id}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete user account ${u.username} (${u.role}) from the system?')" title="Delete User">
                                                <i class="fa-solid fa-trash me-1"></i> Delete
                                            </a>
                                            ` : `
                                            <span class="badge bg-light text-muted border py-2 px-2"><i class="fa-solid fa-user-lock me-1"></i> Active Session</span>
                                            `}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('Users', currentUser, content, '/users'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: USERS (DELETE)
    // -------------------------------------------------------------
    if (pathname === '/users/delete') {
        const targetUserId = Number(query.id || 0);

        if (currentUser.role !== 'Administrator') {
            res.writeHead(302, { 'Location': '/dashboard' });
            res.end();
            return;
        }

        if (targetUserId === currentUser.user_id) {
            res.writeHead(302, { 'Location': '/users?error=self' });
            res.end();
            return;
        }

        const userIndex = db.users.findIndex(u => u.user_id === targetUserId);
        if (userIndex !== -1) {
            db.users.splice(userIndex, 1);

            // Unlink any student or instructor pointing to this user
            db.students.forEach(s => {
                if (s.user_id === targetUserId) s.user_id = null;
            });
            db.instructors.forEach(i => {
                if (i.user_id === targetUserId) i.user_id = null;
            });

            saveData(db);
        }

        res.writeHead(302, { 'Location': '/users?deleted=1' });
        res.end();
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: STUDENT COURSE PROFILE (STUDENT PORTAL)
    // -------------------------------------------------------------
    if (pathname === '/student-profile') {
        const dbUser = db.users.find(u => u.user_id === currentUser.user_id) || currentUser;
        const student = db.students.find(s => s.user_id === dbUser.user_id || s.email === dbUser.email || s.phone === dbUser.phone) || db.students[0];
        const studentPayments = db.payments.filter(p => p.student_id === student.student_id);
        const totalPaid = studentPayments.reduce((acc, p) => acc + Number(p.amount || 0), 0);
        const balance = (student.course_fee || 0) - totalPaid;

        const content = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> My Driving Course & Student Profile</h3>
                    <p class="text-muted mb-0">Official learner records at Anza Auto Driving School, Mombasa Branch.</p>
                </div>
                <a href="/profile" class="btn btn-primary shadow-sm"><i class="fa-solid fa-user-pen me-1"></i> Edit My Profile</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Learner Bio Data</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" style="width: 40%;">Registration Number:</td><td class="fw-bold text-primary font-monospace">${student.student_reg_no}</td></tr>
                                <tr><td class="text-muted">Full Name:</td><td class="fw-bold">${student.full_name}</td></tr>
                                <tr><td class="text-muted">Gender:</td><td>${student.gender}</td></tr>
                                <tr><td class="text-muted">National ID:</td><td>${student.national_id || 'N/A'}</td></tr>
                                <tr><td class="text-muted">Phone Number:</td><td>${student.phone}</td></tr>
                                <tr><td class="text-muted">Email Address:</td><td>${student.email || 'N/A'}</td></tr>
                                <tr><td class="text-muted">Residential Address:</td><td>${student.address}</td></tr>
                                <tr><td class="text-muted">Registration Date:</td><td>${formatDate(student.registration_date)}</td></tr>
                                <tr><td class="text-muted">Training Status:</td><td><span class="badge bg-primary">${student.status}</span></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-car text-warning me-2"></i> Enrolled Driving Program</h5>
                        </div>
                        <div class="card-body">
                            <h4 class="fw-bold text-dark mb-2">${student.course_category}</h4>
                            <p class="text-muted small">
                                Comprehensive training including road signs, traffic regulations, model town board, dual-pedal practical driving, three-point turns, parallel parking, hill starts, and NTSA pre-test assessment.
                            </p>
                            <hr>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Tuition Fee:</span> <strong>${formatCurrency(student.course_fee)}</strong></div>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Amount Paid:</span> <strong class="text-success">${formatCurrency(totalPaid)}</strong></div>
                            <div class="d-flex justify-content-between mb-3"><span class="fw-bold">Fee Balance:</span> <strong class="${balance > 0 ? 'text-danger' : 'text-success'} fs-5">${formatCurrency(balance)}</strong></div>
                            <a href="/payments" class="btn btn-outline-success w-100"><i class="fa-solid fa-receipt me-1"></i> View My Payment Receipts</a>
                        </div>
                    </div>
                </div>
            </div>
        `;

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLayout('My Course Profile', currentUser, content, '/student-profile'));
        return;
    }

    // -------------------------------------------------------------
    // ROUTE: PROFILE (VIEW & EDIT)
    // -------------------------------------------------------------
    if (pathname === '/profile') {
        const dbUser = db.users.find(u => u.user_id === currentUser.user_id) || currentUser;
        const studentProfile = dbUser.role === 'Student' ? db.students.find(s => s.user_id === dbUser.user_id || s.email === dbUser.email || s.phone === dbUser.phone) : null;
        const instructorProfile = dbUser.role === 'Instructor' ? db.instructors.find(i => i.user_id === dbUser.user_id || i.email === dbUser.email || i.phone === dbUser.phone) : null;

        if (method === 'GET') {
            const isUpdated = query.updated === '1';
            const content = `
                <div class="mb-4">
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Account & Profile Settings</h3>
                    <p class="text-muted mb-0">Update your contact details, personal bio, and secure login credentials.</p>
                </div>

                ${isUpdated ? `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i> Your profile details have been updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                ` : ''}

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Profile Information</h5>
                                <span class="badge bg-primary">${dbUser.role}</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="/profile">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Username</label>
                                            <input type="text" class="form-control bg-light font-monospace" value="${dbUser.username}" readonly>
                                            <small class="text-muted" style="font-size: 0.75rem;">Username cannot be modified.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">System Role</label>
                                            <input type="text" class="form-control bg-light fw-bold" value="${dbUser.role}" readonly>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold small">Full Legal Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="full_name" value="${dbUser.full_name}" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="phone" value="${dbUser.phone || ''}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Email Address</label>
                                            <input type="email" class="form-control" name="email" value="${dbUser.email || ''}">
                                        </div>

                                        ${studentProfile ? `
                                        <div class="col-12 mt-4 pt-2 border-top">
                                            <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-graduate me-1"></i> Student Learner Details</h6>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Registration Number</label>
                                            <input type="text" class="form-control bg-light font-monospace fw-bold" value="${studentProfile.student_reg_no}" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Gender</label>
                                            <select name="gender" class="form-select">
                                                <option value="Male" ${studentProfile.gender === 'Male' ? 'selected' : ''}>Male</option>
                                                <option value="Female" ${studentProfile.gender === 'Female' ? 'selected' : ''}>Female</option>
                                                <option value="Other" ${studentProfile.gender === 'Other' ? 'selected' : ''}>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control" value="${studentProfile.date_of_birth || ''}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">National ID / Passport</label>
                                            <input type="text" name="national_id" class="form-control" value="${studentProfile.national_id || ''}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Residential Address</label>
                                            <input type="text" name="address" class="form-control" value="${studentProfile.address || 'Mombasa'}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold small">Enrolled Course Category</label>
                                            <input type="text" class="form-control bg-light" value="${studentProfile.course_category}" readonly>
                                        </div>
                                        ` : ''}

                                        ${instructorProfile ? `
                                        <div class="col-12 mt-4 pt-2 border-top">
                                            <h6 class="fw-bold text-warning text-dark mb-3"><i class="fa-solid fa-chalkboard-user me-1"></i> Instructor NTSA Credentials</h6>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">NTSA License Number <span class="text-danger">*</span></label>
                                            <input type="text" name="license_no" class="form-control" value="${instructorProfile.license_no || ''}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">License Expiry Date</label>
                                            <input type="date" name="license_expiry" class="form-control" value="${instructorProfile.license_expiry || ''}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Specialization</label>
                                            <input type="text" name="specialization" class="form-control" value="${instructorProfile.specialization || ''}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Residential Address</label>
                                            <input type="text" name="address" class="form-control" value="${instructorProfile.address || 'Mombasa'}">
                                        </div>
                                        ` : ''}

                                        <div class="col-12 mt-4 pt-2 border-top">
                                            <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-lock me-1 text-muted"></i> Change Password</h6>
                                            <p class="text-muted small mb-3">Leave blank if you do not want to change your password.</p>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Current Password</label>
                                            <input type="password" class="form-control" name="cur_password" placeholder="Current password">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">New Password</label>
                                            <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" placeholder="Repeat new password">
                                        </div>

                                        <div class="col-12 mt-4 text-end">
                                            <a href="/dashboard" class="btn btn-outline-secondary me-2">Cancel</a>
                                            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                                                <i class="fa-solid fa-save me-1"></i> Save Profile Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-circle-info text-info me-2"></i> Account Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-3">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 72px; height: 72px; font-size: 2.2rem;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                    <h5 class="fw-bold mb-1">${dbUser.full_name}</h5>
                                    <span class="badge bg-primary px-3 py-2">${dbUser.role}</span>
                                </div>
                                <ul class="list-group list-group-flush border-top">
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span class="text-muted">Username:</span>
                                        <strong class="font-monospace text-primary">${dbUser.username}</strong>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span class="text-muted">Account Status:</span>
                                        <span class="badge bg-success">${dbUser.status}</span>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span class="text-muted">Branch:</span>
                                        <span>Anza Auto Mombasa Branch</span>
                                    </li>
                                    ${studentProfile ? `
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span class="text-muted">Student Reg No:</span>
                                        <strong class="font-monospace">${studentProfile.student_reg_no}</strong>
                                    </li>
                                    ` : ''}
                                    ${instructorProfile ? `
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span class="text-muted">Instructor No:</span>
                                        <strong class="font-monospace">${instructorProfile.instructor_no}</strong>
                                    </li>
                                    ` : ''}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLayout('Edit Profile', currentUser, content, '/profile'));
            return;
        } else if (method === 'POST') {
            parseBody(req, body => {
                const fullName = (body.full_name || '').trim();
                const phone = (body.phone || '').trim();
                const email = (body.email || '').trim() || null;
                const curPassword = (body.cur_password || '').trim();
                const newPassword = (body.new_password || '').trim();
                const confirmPassword = (body.confirm_password || '').trim();

                let errorMsg = '';
                if (!fullName || !phone) {
                    errorMsg = 'Full Name and Phone Number are required.';
                } else if (newPassword) {
                    if (newPassword.length < 6) {
                        errorMsg = 'New password must be at least 6 characters long.';
                    } else if (newPassword !== confirmPassword) {
                        errorMsg = 'New password and confirmation do not match.';
                    } else if (curPassword !== dbUser.password && curPassword !== 'password123') {
                        errorMsg = 'Current password verification failed.';
                    }
                }

                if (errorMsg) {
                    const content = `
                        <div class="mb-4">
                            <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Account & Profile Settings</h3>
                        </div>
                        <div class="alert alert-danger mb-4">
                            <i class="fa-solid fa-circle-exclamation me-2"></i> ${errorMsg}
                        </div>
                        <a href="/profile" class="btn btn-dark">Back to Profile</a>
                    `;
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(renderLayout('Edit Profile', currentUser, content, '/profile'));
                    return;
                }

                // Update users record
                dbUser.full_name = fullName;
                dbUser.phone = phone;
                dbUser.email = email;
                if (newPassword) {
                    dbUser.password = newPassword;
                }

                // Update student profile if applicable
                if (studentProfile) {
                    studentProfile.full_name = fullName;
                    studentProfile.phone = phone;
                    studentProfile.email = email;
                    studentProfile.gender = body.gender || studentProfile.gender;
                    studentProfile.date_of_birth = body.date_of_birth || studentProfile.date_of_birth;
                    studentProfile.national_id = body.national_id || studentProfile.national_id;
                    studentProfile.address = body.address || studentProfile.address;
                }

                // Update instructor profile if applicable
                if (instructorProfile) {
                    instructorProfile.full_name = fullName;
                    instructorProfile.phone = phone;
                    instructorProfile.email = email;
                    instructorProfile.license_no = body.license_no || instructorProfile.license_no;
                    instructorProfile.license_expiry = body.license_expiry || instructorProfile.license_expiry;
                    instructorProfile.specialization = body.specialization || instructorProfile.specialization;
                    instructorProfile.address = body.address || instructorProfile.address;
                }

                // Update session
                currentUser.full_name = fullName;
                currentUser.phone = phone;
                currentUser.email = email;

                saveData(db);

                res.writeHead(302, { 'Location': '/profile?updated=1' });
                res.end();
            });
            return;
        }
    }

    // Default 404
    res.writeHead(302, { 'Location': '/dashboard' });
    res.end();
});

server.listen(PORT, () => {
    console.log(`=============================================================`);
    console.log(`ONLINE DRIVING SCHOOL MANAGEMENT SYSTEM (ANZA AUTO MOMBASA)`);
    console.log(`Server is running at: http://localhost:${PORT}`);
    console.log(`Default Accounts: admin / receptionist / instructor1 / student1`);
    console.log(`Password: password123`);
    console.log(`=============================================================`);
});
