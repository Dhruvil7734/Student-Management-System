<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$courses = get_courses();

// ---------------------------------------------------------------- save attendance
if (is_post()) {
    verify_csrf();

    $courseId = post_int('course_id');
    $semester = post_int('semester', 1);
    $date     = post('attendance_date');
    $statuses = $_POST['status'] ?? [];

    if ($courseId <= 0) {
        flash('danger', 'Please select a course.');
        redirect('admin/attendance/index.php');
    }
    if ($date === '' || !strtotime($date) || $date > date('Y-m-d')) {
        flash('danger', 'Please choose a valid date (today or earlier).');
        redirect('admin/attendance/index.php?course_id=' . $courseId . '&semester=' . $semester);
    }
    if (!is_array($statuses)) {
        $statuses = [];
    }

    // Only students that actually belong to the selected class are accepted.
    $stmt = db()->prepare(
        "SELECT id FROM students WHERE course_id = ? AND semester = ? AND status = 'Active'"
    );
    $stmt->execute([$courseId, $semester]);
    $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $allowed = ['Present', 'Absent', 'Late'];
    $saved = 0;

    $upsert = db()->prepare(
        'INSERT INTO attendance (student_id, course_id, semester, attendance_date, status, marked_by)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by),
                                 course_id = VALUES(course_id), semester = VALUES(semester)'
    );

    db()->beginTransaction();
    try {
        foreach ($studentIds as $studentId) {
            $status = $statuses[$studentId] ?? 'Present';
            if (!in_array($status, $allowed, true)) {
                $status = 'Present';
            }
            $upsert->execute([$studentId, $courseId, $semester, $date, $status, $currentAdmin['id']]);
            $saved++;
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash('danger', 'Attendance could not be saved. Please try again.');
        redirect('admin/attendance/index.php?course_id=' . $courseId . '&semester=' . $semester . '&date=' . urlencode($date));
    }

    flash('success', 'Attendance saved for ' . $saved . ' student(s) on ' . format_date($date) . '.');
    redirect('admin/attendance/index.php?course_id=' . $courseId . '&semester=' . $semester . '&date=' . urlencode($date));
}

// ---------------------------------------------------------------- filters
$courseId = query_int('course_id');
$semester = query_int('semester') ?: 1;
$date     = query('date', date('Y-m-d'));
if (!strtotime($date)) {
    $date = date('Y-m-d');
}

$students = [];
if ($courseId > 0) {
    $stmt = db()->prepare(
        'SELECT s.id, s.student_id, s.full_name, s.photo, a.status AS attendance_status
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
         WHERE s.course_id = ? AND s.semester = ? AND s.status = "Active"
         ORDER BY s.full_name'
    );
    $stmt->execute([$date, $courseId, $semester]);
    $students = $stmt->fetchAll();
}

$counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Unmarked' => 0];
foreach ($students as $student) {
    $status = $student['attendance_status'];
    if ($status === null) {
        $counts['Unmarked']++;
    } elseif (isset($counts[$status])) {
        $counts[$status]++;
    }
}

$page_title    = 'Attendance';
$page_subtitle = 'Mark and review daily class attendance';
$active        = 'attendance';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Course</label>
                <select class="form-select" name="course_id" required>
                    <option value="">— Select course —</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>>
                            <?= e($course['name']) ?> (<?= e($course['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <?php foreach (semesters() as $s): ?>
                        <option value="<?= $s ?>"<?= selected($semester, $s) ?>>Sem <?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input class="form-control" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= e($date) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-people me-1"></i>Load class</button>
                <a class="btn btn-icon" href="<?= e(url('admin/attendance/history.php?course_id=' . $courseId . '&semester=' . $semester)) ?>" title="History">
                    <i class="bi bi-clock-history"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($courseId <= 0): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-calendar2-check"></i>
            <h6 class="mb-1">Select a class to begin</h6>
            <p class="small mb-0">Choose a course, semester and date above, then load the student list.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Class size</div><div class="value"><?= count($students) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Present</div><div class="value text-success"><?= $counts['Present'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Absent</div><div class="value text-danger"><?= $counts['Absent'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Late / unmarked</div><div class="value text-warning"><?= $counts['Late'] ?> / <?= $counts['Unmarked'] ?></div></div></div>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
        <input type="hidden" name="semester" value="<?= (int) $semester ?>">
        <input type="hidden" name="attendance_date" value="<?= e($date) ?>">

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="bi bi-clipboard2-check me-2 text-primary"></i>
                    <?= e(course_name($courseId)) ?> &middot; Semester <?= (int) $semester ?> &middot; <?= format_date($date, 'D, d M Y') ?>
                </span>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-soft" type="button" id="markAllPresent">
                        <i class="bi bi-check2-all me-1"></i>Mark all present
                    </button>
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="bi bi-save me-1"></i>Save attendance
                    </button>
                </div>
            </div>

            <?php if (!$students): ?>
                <div class="empty-state">
                    <i class="bi bi-person-x"></i>
                    <h6 class="mb-1">No active students</h6>
                    <p class="small mb-0">There are no active students in this course and semester.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Student</th><th>Student ID</th><th>Current</th><th class="text-end">Mark attendance</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php $current = $student['attendance_status'] ?? 'Present'; ?>
                                <tr>
                                    <td>
                                        <div class="person-cell">
                                            <?php if ($student['photo']): ?>
                                                <img class="avatar avatar-sm" src="<?= e(url($student['photo'])) ?>" alt="">
                                            <?php else: ?>
                                                <span class="avatar avatar-sm"><?= e(initials($student['full_name'])) ?></span>
                                            <?php endif; ?>
                                            <div class="meta"><strong><?= e($student['full_name']) ?></strong></div>
                                        </div>
                                    </td>
                                    <td class="text-muted"><?= e($student['student_id']) ?></td>
                                    <td>
                                        <?= $student['attendance_status'] ? status_badge($student['attendance_status']) : '<span class="badge text-bg-light border">Not marked</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="att-status">
                                            <?php foreach (['Present', 'Absent', 'Late'] as $status): ?>
                                                <input type="radio" id="att-<?= (int) $student['id'] ?>-<?= $status ?>"
                                                       name="status[<?= (int) $student['id'] ?>]" value="<?= $status ?>"
                                                       <?= $current === $status ? 'checked' : '' ?>>
                                                <label for="att-<?= (int) $student['id'] ?>-<?= $status ?>"><?= $status ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
