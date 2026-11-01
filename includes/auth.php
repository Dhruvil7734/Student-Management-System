<?php
/**
 * Require an authenticated administrator.
 * Include this at the top of every protected page.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    flash('warning', 'Please log in to continue.');
    redirect('auth/login.php');
}

// Make the logged-in admin available to views.
$currentAdmin = [
    'id'        => (int) $_SESSION['admin_id'],
    'username'  => $_SESSION['admin_username'] ?? '',
    'full_name' => $_SESSION['admin_name'] ?? 'Administrator',
];
