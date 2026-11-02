<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!is_post()) {
    redirect('admin/teachers/index.php');
}

verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT * FROM teachers WHERE id = ?');
$stmt->execute([$id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    flash('danger', 'Teacher not found.');
    redirect('admin/teachers/index.php');
}

db()->prepare('DELETE FROM teachers WHERE id = ?')->execute([$id]);
delete_photo($teacher['photo']);

flash('success', $teacher['full_name'] . ' has been removed from the teaching staff.');
redirect('admin/teachers/index.php');
