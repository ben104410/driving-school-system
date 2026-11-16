<?php
$roleColors = [
    'Administrator' => 'danger',
    'Receptionist' => 'primary',
    'Instructor' => 'warning text-dark',
    'Student' => 'success'
];
$badgeColor = $roleColors[$_SESSION['role'] ?? 'Student'] ?? 'secondary';
?>
<header class="top-navbar">
    <div class="d-flex align-items-center">
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill me-3 d-none d-md-inline-block">
            <i class="fa-solid fa-location-dot me-1"></i> AA Mombasa Branch
        </span>
        <span class="text-muted small d-none d-lg-inline-block">
            <i class="fa-regular fa-clock me-1"></i> <?= date('l, d F Y') ?>
        </span>
    </div>

    <div class="d-flex align-items-center gap-3">
        <div class="text-end">
            <div class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
            <span class="badge bg-<?= $badgeColor ?>"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
        </div>
        <div class="dropdown">
            <button class="btn btn-light rounded-circle shadow-sm border p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-user-circle fs-5 text-primary"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li><h6 class="dropdown-header">Signed in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong></h6></li>
                <li><a class="dropdown-item" href="profile.php"><i class="fa-solid fa-id-badge me-2 text-muted"></i> Profile & Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out</a></li>
            </ul>
        </div>
    </div>
</header>
