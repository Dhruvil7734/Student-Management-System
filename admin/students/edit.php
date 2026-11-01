<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id') ?: post_int('id');
if ($id <= 0) {
    flash('danger', 'Invalid student record.');
    redirect('admin/students/index.php');
}

$stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch();

if (!$existing) {
    flash('danger', 'Student not found.');
    redirect('admin/students/index.php');
}

$student = $existing;
$errors  = [];

if (is_post()) {
    verify_csrf();

    $student = array_merge($existing, [
        'student_id'     => post('student_id'),
        'full_name'      => post('full_name'),
        'email'          => post('email'),
        'phone'          => post('phone'),
        'gender'         => post('gender'),
        'dob'            => post('dob'),
        'address'        => post('address'),
        'course_id'      => post_int('course_id'),
        'semester'       => post_int('semester', 1),
        'admission_date' => post('admission_date'),
        'status'         => post('status', 'Active'),
    ]);

    if ($student['student_id'] === '') {
        $errors[] = 'Student ID is required.';
    }
    if ($student['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($student['email'] === '' || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($student['course_id'] <= 0) {
        $errors[] = 'Please select a course.';
    }
    if ($student['semester'] < 1 || $student['semester'] > 8) {
        $errors[] = 'Semester must be between 1 and 8.';
    }
    if ($student['admission_date'] === '' || !strtotime($student['admission_date'])) {
        $errors[] = 'A valid admission date is required.';
    }
    if ($student['dob'] !== '' && !strtotime($student['dob'])) {
        $errors[] = 'The date of birth is not valid.';
    }
    if (!in_array($student['gender'], ['', 'Male', 'Female', 'Other'], true)) {
        $errors[] = 'Invalid gender selection.';
    }
    if (!in_array($student['status'], ['Active', 'Inactive', 'Graduated'], true)) {
        $errors[] = 'Invalid status selection.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE student_id = ? AND id <> ?');
        $stmt->execute([$student['student_id'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That student ID is already in use.';
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE email = ? AND id <> ?');
        $stmt->execute([$student['email'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That email address is already registered.';
        }
    }

    $newPhoto = null;
    $removePhoto = !empty($_POST['remove_photo']);

    if (!$errors && isset($_FILES['photo'])) {
        [$newPhoto, $uploadError] = upload_photo($_FILES['photo'], 'students');
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
            'UPDATE students SET
                student_id = ?, full_name = ?, email = ?, phone = ?, gender = ?, dob = ?, address = ?,
                course_id = ?, semester = ?, admission_date = ?, photo = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $student['student_id'],
            $student['full_name'],
            $student['email'],
            nullable($student['phone']),
            nullable($student['gender']),
            nullable($student['dob']),
            nullable($student['address']),
            $student['course_id'],
            $student['semester'],
            $student['admission_date'],
            $photo,
            $student['status'],
            $id,
        ]);

        if ($existing['photo'] && $existing['photo'] !== $photo) {
            delete_photo($existing['photo']);
        }

        flash('success', 'Student record updated successfully.');
        redirect('admin/students/view.php?id=' . $id);
    }
}

$formAction = url('admin/students/edit.php');
$isEdit     = true;
require __DIR__ . '/_form.php';
