<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();

$type = query('type', 'students');
if (!in_array($type, ['students', 'attendance', 'fees', 'exams'], true)) {
    $type = 'students';
}

$q        = query('q');
$courseId = query_int('course_id');
$semester = query_int('semester');
$status   = query('status');
$method   = query('method');
$from     = query('from');
$to       = query('to');
$examId   = query_int('exam_id');

// Date filters are optional: an empty value means "all time".
if ($from !== '' && !strtotime($from)) {
    $from = '';
}
if ($to !== '' && !strtotime($to)) {
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$filename = 'sms-' . $type . '-report-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly

if ($type === 'students') {
    fputcsv($out, ['Student ID', 'Full Name', 'Email', 'Phone', 'Gender', 'Date of Birth', 'Address', 'Course', 'Semester', 'Admission Date', 'Status']);

    $where  = ['1 = 1'];
    $params = [];
    if ($q !== '') {
        $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if ($courseId > 0) {
        $where[] = 's.course_id = ?';
        $params[] = $courseId;
    }
    if ($semester > 0) {
        $where[] = 's.semester = ?';
        $params[] = $semester;
    }
    if (in_array($status, ['Active', 'Inactive', 'Graduated'], true)) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, c.name AS course_name FROM students s JOIN courses c ON c.id = s.course_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY s.full_name'
    );
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['student_id'], $row['full_name'], $row['email'], $row['phone'], $row['gender'],
            $row['dob'], $row['address'], $row['course_name'], $row['semester'], $row['admission_date'], $row['status'],
        ]);
    }
}

if ($type === 'attendance') {
    fputcsv($out, ['Student ID', 'Full Name', 'Course', 'Semester', 'Present', 'Late', 'Absent', 'Records', 'Attendance %']);

    $where  = ['1 = 1'];
    $params = [];
    if ($courseId > 0) {
        $where[] = 's.course_id = ?';
        $params[] = $courseId;
    }
    if ($semester > 0) {
        $where[] = 's.semester = ?';
        $params[] = $semester;
    }
    if ($q !== '') {
        $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $joinDate   = '';
    $dateParams = [];
    if ($from !== '') {
        $joinDate .= ' AND a.attendance_date >= ?';
        $dateParams[] = $from;
    }
    if ($to !== '') {
        $joinDate .= ' AND a.attendance_date <= ?';
        $dateParams[] = $to;
    }

    $stmt = $pdo->prepare(
        'SELECT s.student_id, s.full_name, s.semester, c.code AS course_code,
                COUNT(a.id) AS marked,
                SUM(a.status = "Present") AS present,
                SUM(a.status = "Late") AS late,
                SUM(a.status = "Absent") AS absent
         FROM students s
         JOIN courses c ON c.id = s.course_id
         LEFT JOIN attendance a ON a.student_id = s.id' . $joinDate . '
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY s.id, s.student_id, s.full_name, s.semester, c.code
         ORDER BY s.full_name'
    );
    $stmt->execute(array_merge($dateParams, $params));

    foreach ($stmt->fetchAll() as $row) {
        $marked = (int) $row['marked'];
        $pct = $marked > 0 ? round(((int) $row['present'] + (int) $row['late']) / $marked * 100, 1) : 0;
        fputcsv($out, [
            $row['student_id'], $row['full_name'], $row['course_code'], $row['semester'],
            (int) $row['present'], (int) $row['late'], (int) $row['absent'], $marked, $pct,
        ]);
    }
}

if ($type === 'fees') {
    fputcsv($out, ['Receipt No', 'Student ID', 'Student Name', 'Course', 'Amount', 'Payment Date', 'Method', 'Remarks']);

    $where  = ['1 = 1'];
    $params = [];
    if ($from !== '') {
        $where[] = 'f.payment_date >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'f.payment_date <= ?';
        $params[] = $to;
    }
    if ($courseId > 0) {
        $where[] = 'f.course_id = ?';
        $params[] = $courseId;
    }
    if (in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Online'], true)) {
        $where[] = 'f.payment_method = ?';
        $params[] = $method;
    }
    if ($q !== '') {
        $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ? OR f.receipt_no LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }

    $stmt = $pdo->prepare(
        'SELECT f.*, s.full_name, s.student_id AS student_code, c.code AS course_code
         FROM fees f
         JOIN students s ON s.id = f.student_id
         JOIN courses c ON c.id = f.course_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY f.payment_date, f.id'
    );
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['receipt_no'], $row['student_code'], $row['full_name'], $row['course_code'],
            number_format((float) $row['amount'], 2, '.', ''), $row['payment_date'], $row['payment_method'], $row['remarks'],
        ]);
    }
}

if ($type === 'exams') {
    if ($examId <= 0) {
        fclose($out);
        flash('warning', 'Select an examination before exporting its report.');
        redirect('admin/reports/index.php?type=exams');
    }

    $stmt = $pdo->prepare(
        'SELECT e.name, e.exam_date, c.code AS course_code, c.name AS course_name, e.semester
         FROM examinations e JOIN courses c ON c.id = e.course_id WHERE e.id = ?'
    );
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();

    fputcsv($out, ['Examination', $exam['name'] ?? '', 'Course', $exam['course_code'] ?? '', 'Semester', $exam['semester'] ?? '', 'Exam Date', $exam['exam_date'] ?? '']);
    fputcsv($out, []);
    fputcsv($out, ['Student ID', 'Student Name', 'Subjects', 'Obtained', 'Maximum', 'Percentage', 'Grade', 'Result']);

    $stmt = $pdo->prepare(
        'SELECT s.student_id, s.full_name, COUNT(r.id) AS subject_count,
                COALESCE(SUM(r.marks_obtained),0) AS obtained,
                COALESCE(SUM(r.max_marks),0) AS max_marks
         FROM results r JOIN students s ON s.id = r.student_id
         WHERE r.examination_id = ?
         GROUP BY s.id ORDER BY s.full_name'
    );
    $stmt->execute([$examId]);

    foreach ($stmt->fetchAll() as $row) {
        $max = (float) $row['max_marks'];
        $pct = $max > 0 ? round((float) $row['obtained'] / $max * 100, 1) : 0;
        fputcsv($out, [
            $row['student_id'], $row['full_name'], (int) $row['subject_count'],
            (float) $row['obtained'], $max, $pct, calculate_grade($pct), $pct >= 40 ? 'Pass' : 'Fail',
        ]);
    }
}

fclose($out);
exit;
