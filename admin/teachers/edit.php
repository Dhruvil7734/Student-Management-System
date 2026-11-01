<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id') ?: post_int('id');
$stmt = db()->prepare('SELECT * FROM teachers WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch();

if (!$existing) {
    flash('danger', 'Teacher not found.');
    redirect('admin/teachers/index.php');
}

$teacher = $existing;
$errors  = [];

if (is_post()) {
    verify_csrf();

    $teacher = array_merge($existing, [
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
        $stmt = db()->prepare('SELECT COUNT(*) FROM teachers WHERE (employee_id = ? OR email = ?) AND id <> ?');
        $stmt->execute([$teacher['employee_id'], $teacher['email'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That employee ID or email address is already in use.';
        }
    }

    $newPhoto = null;
    $removePhoto = !empty($_POST['remove_photo']);

    if (!$errors && isset($_FILES['photo'])) {
        [$newPhoto, $uploadError] = upload_photo($_FILES['photo'], 'teachers');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $photo = $existing['photo'];
        if ($newPhoto !== null) {
            $photo = $newPhoto;
        } elseif ($removePhoto) {
            $photo = null;
        }

        $stmt = db()->prepare(
            'UPDATE teachers SET
                employee_id = ?, full_name = ?, email = ?, phone = ?, gender = ?, dob = ?,
                qualification = ?, address = ?, photo = ?
             WHERE id = ?'
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
            $photo,
            $id,
        ]);

        if ($existing['photo'] && $existing['photo'] !== $photo) {
            delete_photo($existing['photo']);
        }

        flash('success', 'Teacher record updated successfully.');
        redirect('admin/teachers/view.php?id=' . $id);
    }
}

$formAction = url('admin/teachers/edit.php');
$isEdit     = true;
require __DIR__ . '/_form.php';
