<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();
$courses = get_courses();

$type = query('type', 'students');
if (!in_array($type, ['students', 'attendance', 'fees', 'exams'], true)) {
    $type = 'students';
}

// Shared filter values
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
$periodLabel = ($from !== '' && $to !== '')
    ? format_date($from) . ' — ' . format_date($to)
    : ($from !== '' ? 'Since ' . format_date($from) : ($to !== '' ? 'Until ' . format_date($to) : 'All time'));

$exportParams = array_filter([
    'type'      => $type,
    'q'         => $q,
    'course_id' => $courseId ?: null,
    'semester'  => $semester ?: null,
    'status'    => $status,
    'method'    => $method,
    'from'      => $from,
    'to'        => $to,
    'exam_id'   => $examId ?: null,
], static fn ($value) => $value !== null && $value !== '');
$exportUrl = url('admin/reports/export.php?' . http_build_query($exportParams));

// ---------------------------------------------------------------- data per report
$rows = [];
$p = null;
$attendanceTotals = ['marked' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
$feeTotals = ['total' => 0.0, 'count' => 0];
$exam = null;
$examSummary = ['students' => 0, 'passed' => 0, 'average' => 0.0];

if ($type === 'students') {
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
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM students s' . $whereSql);
    $countStmt->execute($params);
    $p = paginate((int) $countStmt->fetchColumn());

    $stmt = $pdo->prepare(
        'SELECT s.*, c.name AS course_name, c.code AS course_code
         FROM students s JOIN courses c ON c.id = s.course_id' . $whereSql . '
         ORDER BY s.full_name
         LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset']
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($type === 'attendance') {
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

    $baseFrom = ' FROM students s
                  JOIN courses c ON c.id = s.course_id
                  LEFT JOIN attendance a ON a.student_id = s.id' . $joinDate;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM students s' . $whereSql);
    $countStmt->execute($params);
    $p = paginate((int) $countStmt->fetchColumn());

    $stmt = $pdo->prepare(
        'SELECT s.id, s.student_id, s.full_name, s.semester, c.code AS course_code, c.name AS course_name,
                COUNT(a.id) AS marked,
                SUM(a.status = "Present") AS present,
                SUM(a.status = "Late") AS late,
                SUM(a.status = "Absent") AS absent'
        . $baseFrom . $whereSql . '
         GROUP BY s.id, s.student_id, s.full_name, s.semester, c.code, c.name
         ORDER BY s.full_name
         LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset']
    );
    $stmt->execute(array_merge($dateParams, $params));
    $rows = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT COUNT(a.id) AS marked, SUM(a.status = "Present") AS present,
                SUM(a.status = "Late") AS late, SUM(a.status = "Absent") AS absent'
        . $baseFrom . $whereSql
    );
    $stmt->execute(array_merge($dateParams, $params));
    $attendanceTotals = $stmt->fetch() ?: $attendanceTotals;
}

if ($type === 'fees') {
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
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*), COALESCE(SUM(f.amount),0)
         FROM fees f JOIN students s ON s.id = f.student_id' . $whereSql
    );
    $countStmt->execute($params);
    [$totalRows, $feeTotal] = $countStmt->fetch(PDO::FETCH_NUM);
    $p = paginate((int) $totalRows);
    $feeTotals = ['total' => (float) $feeTotal, 'count' => (int) $totalRows];

    $stmt = $pdo->prepare(
        'SELECT f.*, s.full_name, s.student_id AS student_code, c.code AS course_code
         FROM fees f
         JOIN students s ON s.id = f.student_id
         JOIN courses c ON c.id = f.course_id' . $whereSql . '
         ORDER BY f.payment_date DESC, f.id DESC
         LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset']
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($type === 'exams') {
    $stmt = $pdo->query(
        'SELECT e.*, c.name AS course_name, c.code AS course_code
         FROM examinations e JOIN courses c ON c.id = e.course_id
         ORDER BY e.exam_date DESC'
    );
    $exams = $stmt->fetchAll();

    if ($examId > 0) {
        $stmt = $pdo->prepare(
            'SELECT e.*, c.name AS course_name, c.code AS course_code
             FROM examinations e JOIN courses c ON c.id = e.course_id WHERE e.id = ?'
        );
        $stmt->execute([$examId]);
        $exam = $stmt->fetch() ?: null;
    }

    if ($exam) {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.student_id, s.full_name,
                    COUNT(r.id) AS subject_count,
                    COALESCE(SUM(r.marks_obtained),0) AS obtained,
                    COALESCE(SUM(r.max_marks),0) AS max_marks
             FROM results r
             JOIN students s ON s.id = r.student_id
             WHERE r.examination_id = ?
             GROUP BY s.id
             ORDER BY s.full_name'
        );
        $stmt->execute([$examId]);
        $rows = $stmt->fetchAll();

        $examSummary['students'] = count($rows);
        $sumPct = 0;
        foreach ($rows as $row) {
            $rowPct = (float) $row['max_marks'] > 0 ? (float) $row['obtained'] / (float) $row['max_marks'] * 100 : 0;
            if ($rowPct >= 40) {
                $examSummary['passed']++;
            }
            $sumPct += $rowPct;
        }
        $examSummary['average'] = count($rows) > 0 ? round($sumPct / count($rows), 1) : 0.0;
    }
}

$page_title    = 'Reports';
$page_subtitle = 'Generate, review and export institutional reports';
$active        = 'reports';
$use_charts    = false;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<ul class="nav nav-pills gap-2 mb-4">
    <?php
    $tabs = [
        'students'   => ['label' => 'Students', 'icon' => 'people-fill'],
        'attendance' => ['label' => 'Attendance', 'icon' => 'calendar2-check-fill'],
        'fees'       => ['label' => 'Fees', 'icon' => 'wallet-fill'],
        'exams'      => ['label' => 'Examinations', 'icon' => 'bar-chart-fill'],
    ];
    foreach ($tabs as $key => $tab):
        $tabParams = array_merge($exportParams, ['type' => $key]);
        unset($tabParams['status'], $tabParams['method'], $tabParams['exam_id']);
        ?>
        <li class="nav-item">
            <a class="nav-link<?= $type === $key ? ' active' : '' ?>"
               href="?<?= e(http_build_query($tabParams)) ?>"
               <?= $type === $key ? 'style="background:var(--brand);color:#fff;"' : 'style="background:#fff;border:1px solid var(--line);color:var(--muted);"' ?>>
                <i class="bi bi-<?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <input type="hidden" name="type" value="<?= e($type) ?>">

            <?php if ($type === 'students' || $type === 'attendance' || $type === 'fees'): ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Search</label>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                           placeholder="<?= $type === 'fees' ? 'Student or receipt…' : 'Name or ID…' ?>">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>><?= e($course['code']) ?> — <?= e($course['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($type === 'students'): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Semester</label>
                    <select class="form-select" name="semester">
                        <option value="">All</option>
                        <?php foreach (semesters() as $s): ?>
                            <option value="<?= $s ?>"<?= selected($semester, $s) ?>>Sem <?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <?php foreach (['Active', 'Inactive', 'Graduated'] as $s): ?>
                            <option value="<?= $s ?>"<?= selected($status, $s) ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($type === 'attendance'): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Semester</label>
                    <select class="form-select" name="semester">
                        <option value="">All</option>
                        <?php foreach (semesters() as $s): ?>
                            <option value="<?= $s ?>"<?= selected($semester, $s) ?>>Sem <?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">From <span class="text-muted">(optional)</span></label>
                    <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">To <span class="text-muted">(optional)</span></label>
                    <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
                </div>
            <?php endif; ?>

            <?php if ($type === 'fees'): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">From <span class="text-muted">(optional)</span></label>
                    <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">To <span class="text-muted">(optional)</span></label>
                    <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Method</label>
                    <select class="form-select" name="method">
                        <option value="">All</option>
                        <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Online'] as $option): ?>
                            <option value="<?= $option ?>"<?= selected($method, $option) ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($type === 'exams'): ?>
                <div class="col-lg-6">
                    <label class="form-label">Examination</label>
                    <select class="form-select" name="exam_id">
                        <option value="">— Select examination —</option>
                        <?php foreach ($exams as $option): ?>
                            <option value="<?= (int) $option['id'] ?>"<?= selected($examId, $option['id']) ?>>
                                <?= e($option['name']) ?> — <?= e($option['course_code']) ?> (Sem <?= (int) $option['semester'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-lg-2 col-md-6">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Generate</button>
            </div>
            <div class="col-lg-2 col-md-6">
                <a class="btn btn-soft w-100" href="<?= e($exportUrl) ?>"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
            </div>
        </form>
    </div>
</div>

<?php if ($type === 'students'): ?>
    <div class="card table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people-fill me-2 text-primary"></i>Student report</span>
            <span class="text-muted small"><?= number_format((int) ($p['total'] ?? 0)) ?> record(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Student</th><th>Course</th><th>Semester</th><th>Phone</th><th>Admitted</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No students match these filters.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="person-cell">
                                    <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                    <div class="meta">
                                        <strong><?= e($row['full_name']) ?></strong>
                                        <small><?= e($row['student_id']) ?> &middot; <?= e($row['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($row['course_code']) ?></td>
                            <td>Sem <?= (int) $row['semester'] ?></td>
                            <td class="text-muted"><?= e($row['phone'] ?: '—') ?></td>
                            <td class="text-muted"><?= format_date($row['admission_date']) ?></td>
                            <td><?= status_badge($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($p && $p['pages'] > 1): ?><div class="card-body d-flex justify-content-end"><?php render_pagination($p); ?></div><?php endif; ?>
    </div>

<?php elseif ($type === 'attendance'): ?>
    <?php $overallPct = (int) $attendanceTotals['marked'] > 0 ? round(((int) $attendanceTotals['present'] + (int) $attendanceTotals['late']) / (int) $attendanceTotals['marked'] * 100, 1) : 0; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Records</div><div class="value"><?= (int) $attendanceTotals['marked'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Present</div><div class="value text-success"><?= (int) $attendanceTotals['present'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Absent</div><div class="value text-danger"><?= (int) $attendanceTotals['absent'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Overall</div><div class="value"><?= $overallPct ?>%</div></div></div>
    </div>
    <div class="card table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar2-check me-2 text-primary"></i>Attendance report</span>
            <span class="text-muted small"><?= e($periodLabel) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Student</th><th>Course</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Absent</th><th>Attendance</th></tr></thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No attendance data for these filters.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $marked = (int) $row['marked'];
                        $pct = $marked > 0 ? round(((int) $row['present'] + (int) $row['late']) / $marked * 100, 1) : 0;
                        $barClass = $pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
                        ?>
                        <tr>
                            <td>
                                <div class="person-cell">
                                    <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                    <div class="meta"><strong><?= e($row['full_name']) ?></strong><small><?= e($row['student_id']) ?></small></div>
                                </div>
                            </td>
                            <td><?= e($row['course_code']) ?> · Sem <?= (int) $row['semester'] ?></td>
                            <td class="text-center text-success"><?= (int) $row['present'] ?></td>
                            <td class="text-center text-warning"><?= (int) $row['late'] ?></td>
                            <td class="text-center text-danger"><?= (int) $row['absent'] ?></td>
                            <td style="min-width: 150px;">
                                <div class="d-flex justify-content-between mb-1"><small class="text-muted"><?= $marked ?> marked</small><small class="fw-semibold"><?= $pct ?>%</small></div>
                                <div class="progress"><div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($p && $p['pages'] > 1): ?><div class="card-body d-flex justify-content-end"><?php render_pagination($p); ?></div><?php endif; ?>
    </div>

<?php elseif ($type === 'fees'): ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-4"><div class="mini-stat"><div class="label">Payments</div><div class="value"><?= number_format($feeTotals['count']) ?></div></div></div>
        <div class="col-sm-4"><div class="mini-stat"><div class="label">Collected in range</div><div class="value text-success"><?= money($feeTotals['total']) ?></div></div></div>
        <div class="col-sm-4"><div class="mini-stat"><div class="label">Period</div><div class="value fs-6"><?= e($periodLabel) ?></div></div></div>
    </div>
    <div class="card table-card">
        <div class="card-header"><i class="bi bi-wallet-fill me-2 text-primary"></i>Fee collection report</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Receipt</th><th>Student</th><th>Course</th><th>Date</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No payments in this period.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($row['receipt_no']) ?></td>
                            <td>
                                <div class="meta"><strong><?= e($row['full_name']) ?></strong><small class="text-muted"><?= e($row['student_code']) ?></small></div>
                            </td>
                            <td><?= e($row['course_code']) ?></td>
                            <td class="text-muted"><?= format_date($row['payment_date']) ?></td>
                            <td><span class="badge text-bg-light border"><?= e($row['payment_method']) ?></span></td>
                            <td class="text-end fw-semibold"><?= money($row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($p && $p['pages'] > 1): ?><div class="card-body d-flex justify-content-end"><?php render_pagination($p); ?></div><?php endif; ?>
    </div>

<?php else: ?>
    <?php if (!$exam): ?>
        <div class="card"><div class="empty-state">
            <i class="bi bi-bar-chart"></i>
            <h6 class="mb-1">Choose an examination</h6>
            <p class="small mb-0">Select an examination above to generate its result report.</p>
        </div></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Students</div><div class="value"><?= $examSummary['students'] ?></div></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Passed</div><div class="value text-success"><?= $examSummary['passed'] ?></div></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Failed</div><div class="value text-danger"><?= max(0, $examSummary['students'] - $examSummary['passed']) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Class average</div><div class="value"><?= $examSummary['average'] ?>%</div></div></div>
        </div>
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-fill me-2 text-primary"></i><?= e($exam['name']) ?> — <?= e($exam['course_code']) ?> (Sem <?= (int) $exam['semester'] ?>)</span>
                <span class="text-muted small"><?= format_date($exam['exam_date']) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Student</th><th class="text-center">Subjects</th><th class="text-end">Marks</th><th>Percentage</th><th>Grade</th><th>Result</th><th></th></tr></thead>
                    <tbody>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No marks recorded for this examination.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $pct = (float) $row['max_marks'] > 0 ? round((float) $row['obtained'] / (float) $row['max_marks'] * 100, 1) : 0;
                            $pass = $pct >= 40;
                            ?>
                            <tr>
                                <td>
                                    <div class="person-cell">
                                        <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                        <div class="meta"><strong><?= e($row['full_name']) ?></strong><small><?= e($row['student_id']) ?></small></div>
                                    </div>
                                </td>
                                <td class="text-center"><?= (int) $row['subject_count'] ?></td>
                                <td class="text-end"><?= (float) $row['obtained'] ?> / <?= (float) $row['max_marks'] ?></td>
                                <td style="min-width:140px;">
                                    <div class="d-flex justify-content-between mb-1"><small class="text-muted"><?= $pct ?>%</small></div>
                                    <div class="progress"><div class="progress-bar <?= $pass ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(100, $pct) ?>%"></div></div>
                                </td>
                                <td><span class="badge text-bg-light border"><?= calculate_grade($pct) ?></span></td>
                                <td><?= status_badge($pass ? 'Pass' : 'Fail') ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-soft" href="<?= e(url('admin/examinations/results.php?exam_id=' . $examId . '&student_id=' . $row['id'])) ?>" title="Result sheet">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
