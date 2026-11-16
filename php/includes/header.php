<?php
require_once __DIR__ . '/functions.php';
require_login();
$currentUser = get_logged_user();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= SITE_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <div class="main-content">
        <!-- Top Navbar -->
        <?php include __DIR__ . '/navbar.php'; ?>
        
        <!-- Content Body -->
        <div class="content-body">
            <?php display_flash(); ?>
