<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/dashboard.php');
}

redirect('auth/login.php');
