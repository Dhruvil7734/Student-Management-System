<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!is_post()) {
    redirect('admin/fees/index.php');
}

verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT * FROM fees WHERE id = ?');
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    flash('danger', 'Payment record not found.');
    redirect('admin/fees/index.php');
}

db()->prepare('DELETE FROM fees WHERE id = ?')->execute([$id]);
flash('success', 'Payment ' . $payment['receipt_no'] . ' has been deleted.');
redirect('admin/fees/index.php');
