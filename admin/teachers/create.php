<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

function next_employee_id(): string
{
    $stmt = db()->query('SELECT employee_id FROM teachers ORDER BY id DESC LIMIT 1');
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $next = (int) $m[1] + 1;
    }

    do {
        $candidate = 'TCH-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        $check = db()->prepare('SELECT COUNT(*) FROM teachers WHERE employee_id = ?');
        $check->execute([$candidate]);
        $next++;
    } while ((int) $check->fetchColumn() > 0);

    return $candidate;
}

$teacher = [
    'id'            => 0,
    'employee_id'   => next_employee_id(),
    'full_name'     => '',
    'email'         => '',
    'phone'         => '',
    'gender'        => '',
    'dob'           => '',
    'qualification' => '',
    'address'       => '',
    'photo'         => null,
];

$errors = [];

if (is_post()) {
    verify_csrf();

    $teacher = array_merge($teacher, [
        'employee_id'   => post('employee_id'),
        'full_name'     => post('full_name'),
        'email'         => post('email'),
        'phone'         => post('phone'),
        'gender'        => post('gender'),
        'dob'           => post('dob'),
        'qualification' => post('qualification'),
        'address'       => post('address'),
    ]);

    if ($teacher['employee_id'] === '') {
        $errors[] = 'Employee ID is required.';
    }
    if ($teacher['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($teacher['email'] === '' || !filter_var($teacher['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($teacher['dob'] !== '' && !strtotime($teacher['dob'])) {
        $errors[] = 'The date of birth is not valid.';
    }
    if (!in_array($teacher['gender'], ['', 'Male', 'Female', 'Other'], true)) {
        $errors[] = 'Invalid gender selection.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM teachers WHERE employee_id = ? OR email = ?');
        $stmt->execute([$teacher['employee_id'], $teacher['email']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That employee ID or email address is already in use.';
        }
    }

    $photoPath = null;
    if (!$errors && isset($_FILES['photo'])) {
        [$photoPath, $uploadError] = upload_photo($_FILES['photo'], 'teachers');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO teachers (employee_id, full_name, email, phone, gender, dob, qualification, address, photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $teacher['employee_id'],
            $teacher['full_name'],
            $teacher['email'],
            nullable($teacher['phone']),
            nullable($teacher['gender']),
            nullable($teacher['dob']),
            nullable($teacher['qualification']),
            nullable($teacher['address']),
            $photoPath,
        ]);

        flash('success', $teacher['full_name'] . ' has been added to the teaching staff.');
        redirect('admin/teachers/view.php?id=' . (int) db()->lastInsertId());
    }
}

$formAction = url('admin/teachers/create.php');
$isEdit     = false;
require __DIR__ . '/_form.php';
