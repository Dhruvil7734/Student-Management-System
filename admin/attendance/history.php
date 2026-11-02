<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$courses = get_courses();

$courseId = query_int('course_id');
$semester = query_int('semester');
$from     = query('from', date('Y-m-d', strtotime('-30 days')));
$to       = query('to', date('Y-m-d'));
$q        = query('q');

if (!strtotime($from)) {
    $from = date('Y-m-d', strtotime('-30 days'));
}
if (!strtotime($to)) {
    $to = date('Y-m-d');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

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

$whereSql = ' WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare('SELECT COUNT(*) FROM students s' . $whereSql);
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

// Join parameters: date range first, then filter parameters.
$sql = 'SELECT s.id, s.student_id, s.full_name, s.semester, c.name AS course_name, c.code AS course_code,
               COUNT(a.id) AS marked,
               SUM(a.status = "Present") AS present,
               SUM(a.status = "Late")    AS late,
               SUM(a.status = "Absent")  AS absent
        FROM students s
        JOIN courses c ON c.id = s.course_id
        LEFT JOIN attendance a
               ON a.student_id = s.id AND a.attendance_date BETWEEN ? AND ?'
    . $whereSql . '
        GROUP BY s.id, s.student_id, s.full_name, s.semester, c.name, c.code
        ORDER BY s.full_name
        LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];
$stmt = db()->prepare($sql);
$stmt->execute(array_merge([$from, $to], $params));
$rows = $stmt->fetchAll();

// Overall totals for the filtered range.
$totalsSql = 'SELECT COUNT(a.id) marked,
                     SUM(a.status = "Present") present,
                     SUM(a.status = "Late") late,
                     SUM(a.status = "Absent") absent
              FROM students s
              JOIN courses c ON c.id = s.course_id
              LEFT JOIN attendance a
                     ON a.student_id = s.id AND a.attendance_date BETWEEN ? AND ?'
    . $whereSql;
$totalsStmt = db()->prepare($totalsSql);
$totalsStmt->execute(array_merge([$from, $to], $params));
$totals = $totalsStmt->fetch() ?: ['marked' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
$overallPct = (int) $totals['marked'] > 0
    ? round(((int) $totals['present'] + (int) $totals['late']) / (int) $totals['marked'] * 100, 1)
    : 0;

$page_title    = 'Attendance History';
$page_subtitle = 'Attendance percentages and records by date range';
$active        = 'attendance';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Course</label>
                <select class="form-select" name="course_id">
                    <option value="">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>><?= e($course['code']) ?> — <?= e($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="">All</option>
                    <?php foreach (semesters() as $s): ?>
                        <option value="<?= $s ?>"<?= selected($semester, $s) ?>>Sem <?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Student</label>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Name or ID">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply filters</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/attendance/history.php')) ?>">Reset</a>
                <a class="btn btn-soft ms-auto" href="<?= e(url('admin/attendance/index.php?course_id=' . $courseId . '&semester=' . ($semester ?: 1))) ?>">
                    <i class="bi bi-calendar2-check me-1"></i>Mark attendance
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Records</div><div class="value"><?= (int) $totals['marked'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Present</div><div class="value text-success"><?= (int) $totals['present'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Absent</div><div class="value text-danger"><?= (int) $totals['absent'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Overall attendance</div><div class="value"><?= $overallPct ?>%</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2 text-primary"></i><?= format_date($from) ?> — <?= format_date($to) ?></span>
        <small class="text-muted"><?= number_format((int) $p['total']) ?> student(s)</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Absent</th>
                    <th style="min-width:170px;">Attendance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No students match these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $marked = (int) $row['marked'];
                    $attended = (int) $row['present'] + (int) $row['late'];
                    $pct = $marked > 0 ? round($attended / $marked * 100, 1) : 0;
                    $barClass = $pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <tr>
                        <td>
                            <div class="person-cell">
                                <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                <div class="meta">
                                    <strong><?= e($row['full_name']) ?></strong>
                                    <small><?= e($row['student_id']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="d-block"><?= e($row['course_code']) ?></span>
                            <small class="text-muted">Sem <?= (int) $row['semester'] ?></small>
                        </td>
                        <td class="text-center text-success fw-semibold"><?= (int) $row['present'] ?></td>
                        <td class="text-center text-warning fw-semibold"><?= (int) $row['late'] ?></td>
                        <td class="text-center text-danger fw-semibold"><?= (int) $row['absent'] ?></td>
                        <td>
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted"><?= $marked ?> marked</small>
                                <small class="fw-semibold"><?= $pct ?>%</small>
                            </div>
                            <div class="progress"><div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div></div>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/view.php?id=' . $row['id'])) ?>">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($p['pages'] > 1): ?>
        <div class="card-body d-flex justify-content-end"><?php render_pagination($p); ?></div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
