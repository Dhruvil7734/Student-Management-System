<?php
/**
 * Page head + top bar. Set $page_title / $page_subtitle / $active before including.
 */
$page_title    = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
$active        = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-area">
        <header class="topbar">
            <button class="btn btn-icon d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle menu">
                <i class="bi bi-list"></i>
            </button>

            <div class="topbar-heading">
                <h1 class="page-title"><?= e($page_title) ?></h1>
                <?php if ($page_subtitle !== ''): ?>
                    <p class="page-subtitle"><?= e($page_subtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="topbar-actions">
                <span class="date-chip d-none d-md-inline-flex">
                    <i class="bi bi-calendar3"></i><?= date('D, d M Y') ?>
                </span>

                <div class="dropdown">
                    <button class="btn admin-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar avatar-sm"><?= e(initials($currentAdmin['full_name'])) ?></span>
                        <span class="d-none d-sm-flex flex-column text-start">
                            <strong><?= e($currentAdmin['full_name']) ?></strong>
                            <small>@<?= e($currentAdmin['username']) ?></small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="<?= e(url('admin/change_password.php')) ?>">
                            <i class="bi bi-key me-2"></i>Change password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url('auth/logout.php')) ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-octagon' : 'info-circle') ?> me-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
