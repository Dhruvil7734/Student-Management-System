<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!is_post()) {
    redirect('admin/courses/index.php');
}

verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT * FROM courses WHERE id = ?');
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    flash('danger', 'Course not found.');
    redirect('admin/courses/index.php');
}

$stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE course_id = ?');
$stmt->execute([$id]);
if ((int) $stmt->fetchColumn() > 0) {
    flash('danger', 'This course cannot be deleted while students are enrolled in it.');
    redirect('admin/courses/index.php');
}

db()->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
flash('success', $course['name'] . ' has been deleted.');
redirect('admin/courses/index.php');
