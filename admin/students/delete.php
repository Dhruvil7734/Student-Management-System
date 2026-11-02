<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!is_post()) {
    redirect('admin/students/index.php');
}

verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('admin/students/index.php');
}

db()->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
delete_photo($student['photo']);

flash('success', $student['full_name'] . ' and all related records were deleted.');
redirect('admin/students/index.php');
