<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'Student';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand-logo">
            <i class="fa-solid fa-car-side"></i>
        </div>
        <h5 class="mb-0 text-white fw-bold">AA Driving School</h5>
        <small class="text-white-50">Mombasa Branch</small>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-category">Main Menu</li>
        <li>
            <a href="dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if ($userRole === 'Administrator' || $userRole === 'Receptionist'): ?>
        <li class="nav-category">Management</li>
        <li>
            <a href="students.php" class="nav-link <?= in_array($currentPage, ['students.php', 'student_add.php', 'student_edit.php', 'student_view.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </li>
        <li>
            <a href="instructors.php" class="nav-link <?= in_array($currentPage, ['instructors.php', 'instructor_add.php', 'instructor_edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-chalkboard-user"></i>
                <span>Instructors</span>
            </a>
        </li>
        <li>
            <a href="vehicles.php" class="nav-link <?= in_array($currentPage, ['vehicles.php', 'vehicle_add.php', 'vehicle_edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-car"></i>
                <span>Vehicles (Fleet)</span>
            </a>
        </li>
        <li>
            <a href="schedules.php" class="nav-link <?= in_array($currentPage, ['schedules.php', 'schedule_add.php', 'schedule_edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Lesson Schedules</span>
            </a>
        </li>
        <li>
            <a href="payments.php" class="nav-link <?= in_array($currentPage, ['payments.php', 'payment_add.php', 'receipt.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-money-bill-wave"></i>
                <span>Fee Payments</span>
            </a>
        </li>
        <li>
            <a href="test_results.php" class="nav-link <?= in_array($currentPage, ['test_results.php', 'test_result_add.php', 'test_result_edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Driving Test Results</span>
            </a>
        </li>

        <li class="nav-category">Analytics & System</li>
        <li>
            <a href="reports.php" class="nav-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Reports & Analytics</span>
            </a>
        </li>
        <?php if ($userRole === 'Administrator'): ?>
        <li>
            <a href="users.php" class="nav-link <?= in_array($currentPage, ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>
                <span>System Users</span>
            </a>
        </li>
        <?php endif; ?>

        <?php elseif ($userRole === 'Instructor'): ?>
        <li class="nav-category">Instructor Portal</li>
        <li>
            <a href="schedules.php" class="nav-link <?= ($currentPage === 'schedules.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i>
                <span>My Lesson Schedules</span>
            </a>
        </li>
        <li>
            <a href="students.php" class="nav-link <?= ($currentPage === 'students.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Assigned Students</span>
            </a>
        </li>
        <li>
            <a href="test_results.php" class="nav-link <?= ($currentPage === 'test_results.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Test Assessments</span>
            </a>
        </li>

        <?php elseif ($userRole === 'Student'): ?>
        <li class="nav-category">Student Portal</li>
        <li>
            <a href="student_profile.php" class="nav-link <?= ($currentPage === 'student_profile.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card"></i>
                <span>My Course Profile</span>
            </a>
        </li>
        <li>
            <a href="schedules.php" class="nav-link <?= ($currentPage === 'schedules.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>My Driving Lessons</span>
            </a>
        </li>
        <li>
            <a href="payments.php" class="nav-link <?= ($currentPage === 'payments.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt"></i>
                <span>My Fees & Receipts</span>
            </a>
        </li>
        <li>
            <a href="test_results.php" class="nav-link <?= ($currentPage === 'test_results.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-award"></i>
                <span>My Driving Test Results</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-category">Account</li>
        <li>
            <a href="profile.php" class="nav-link <?= ($currentPage === 'profile.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-pen"></i>
                <span>My Profile</span>
            </a>
        </li>
        <li>
            <a href="logout.php" class="nav-link text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
