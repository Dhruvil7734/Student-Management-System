<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id') ?: post_int('id');
$stmt = db()->prepare('SELECT * FROM courses WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch();

if (!$existing) {
    flash('danger', 'Course not found.');
    redirect('admin/courses/index.php');
}

$course = $existing;
$errors = [];

if (is_post()) {
    verify_csrf();

    $course = array_merge($existing, [
        'code'           => strtoupper(post('code')),
        'name'           => post('name'),
        'duration_years' => post_int('duration_years', 3),
        'total_fee'      => post_float('total_fee'),
        'description'    => post('description'),
    ]);

    if ($course['code'] === '') {
        $errors[] = 'Course code is required.';
    }
    if ($course['name'] === '') {
        $errors[] = 'Course name is required.';
    }
    if ($course['duration_years'] < 1 || $course['duration_years'] > 6) {
        $errors[] = 'Duration must be between 1 and 6 years.';
    }
    if ($course['total_fee'] < 0) {
        $errors[] = 'Total fee cannot be negative.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?');
        $stmt->execute([$course['code'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That course code is already in use.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE courses SET code = ?, name = ?, duration_years = ?, total_fee = ?, description = ? WHERE id = ?'
        );
        $stmt->execute([
            $course['code'],
            $course['name'],
            $course['duration_years'],
            $course['total_fee'],
            nullable($course['description']),
            $id,
        ]);

        flash('success', 'Course updated successfully.');
        redirect('admin/courses/index.php');
    }
}

$formAction = url('admin/courses/edit.php');
$isEdit     = true;
require __DIR__ . '/_form.php';
