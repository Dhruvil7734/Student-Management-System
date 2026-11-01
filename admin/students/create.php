<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

/**
 * Suggest the next student ID, e.g. STU-2026-013.
 */
function next_student_id(): string
{
    $prefix = 'STU-' . date('Y') . '-';
    $stmt = db()->prepare('SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $next = (int) $m[1] + 1;
    }

    do {
        $candidate = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        $check = db()->prepare('SELECT COUNT(*) FROM students WHERE student_id = ?');
        $check->execute([$candidate]);
        $next++;
    } while ((int) $check->fetchColumn() > 0);

    return $candidate;
}

$student = [
    'id'             => 0,
    'student_id'     => next_student_id(),
    'full_name'      => '',
    'email'          => '',
    'phone'          => '',
    'gender'         => '',
    'dob'            => '',
    'address'        => '',
    'course_id'      => '',
    'semester'       => 1,
    'admission_date' => date('Y-m-d'),
    'photo'          => null,
    'status'         => 'Active',
];

$errors = [];

if (is_post()) {
    verify_csrf();

    $student = array_merge($student, [
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
        $stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE student_id = ?');
        $stmt->execute([$student['student_id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That student ID is already in use.';
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE email = ?');
        $stmt->execute([$student['email']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That email address is already registered.';
        }
    }

    $photoPath = null;
    if (!$errors && isset($_FILES['photo'])) {
        [$photoPath, $uploadError] = upload_photo($_FILES['photo'], 'students');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO students
                (student_id, full_name, email, phone, gender, dob, address, course_id, semester, admission_date, photo, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $photoPath,
            $student['status'],
        ]);

        $newId = (int) db()->lastInsertId();
        flash('success', $student['full_name'] . ' has been registered successfully.');
        redirect('admin/students/view.php?id=' . $newId);
    }
}

$formAction = url('admin/students/create.php');
$isEdit     = false;
require __DIR__ . '/_form.php';
