<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();

$q        = query('q');
$courseId = query_int('course_id');
$status   = query('status');

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($courseId > 0) {
    $where[] = 's.course_id = ?';
    $params[] = $courseId;
}

$having = '';
if ($status === 'paid') {
    $having = ' HAVING COALESCE(SUM(f.amount),0) >= c.total_fee';
} elseif ($status === 'pending') {
    $having = ' HAVING COALESCE(SUM(f.amount),0) < c.total_fee';
}

$inner = 'SELECT s.id, s.student_id, s.full_name, s.semester,
                 c.name AS course_name, c.code AS course_code, c.total_fee,
                 COALESCE(SUM(f.amount),0) AS paid
          FROM students s
          JOIN courses c ON c.id = s.course_id
          LEFT JOIN fees f ON f.student_id = s.id
          WHERE ' . implode(' AND ', $where) . '
          GROUP BY s.id, s.student_id, s.full_name, s.semester, c.name, c.code, c.total_fee' . $having;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM (' . $inner . ') AS t');
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

$dataStmt = $pdo->prepare(
    'SELECT * FROM (' . $inner . ') AS t
     ORDER BY (t.total_fee - t.paid) DESC, t.full_name
     LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset']
);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// Overall numbers
$totalFee = (float) $pdo->query(
    "SELECT COALESCE(SUM(c.total_fee),0) FROM students s JOIN courses c ON c.id = s.course_id WHERE s.status = 'Active'"
)->fetchColumn();
$totalPaid = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM fees')->fetchColumn();
$totalPending = max(0, $totalFee - $totalPaid);

$page_title    = 'Student Balances';
$page_subtitle = 'Outstanding fees for every student';
$active        = 'fees';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="mini-stat"><div class="label">Total expected</div><div class="value"><?= short_money($totalFee) ?></div></div></div>
    <div class="col-sm-4"><div class="mini-stat"><div class="label">Collected</div><div class="value text-success"><?= short_money($totalPaid) ?></div></div></div>
    <div class="col-sm-4"><div class="mini-stat"><div class="label">Outstanding</div><div class="value text-danger"><?= short_money($totalPending) ?></div></div></div>
</div>

<div class="toolbar">
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/fees/index.php')) ?>">
        <i class="bi bi-arrow-left me-1"></i>Payments &amp; receipts
    </a>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="toolbar mb-0" method="get">
            <div class="search-box input-icon">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search student…">
            </div>
            <select class="form-select w-auto" name="course_id">
                <option value="">All courses</option>
                <?php foreach (get_courses() as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>><?= e($course['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select w-auto" name="status">
                <option value="">All balances</option>
                <option value="pending"<?= selected($status, 'pending') ?>>Pending only</option>
                <option value="paid"<?= selected($status, 'paid') ?>>Fully paid</option>
            </select>
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <?php if ($q !== '' || $courseId || $status !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/fees/balances.php')) ?>">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th class="text-end">Total fee</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Pending</th>
                    <th style="min-width:150px;">Progress</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check2-circle"></i>
                        <h6 class="mb-1">Nothing to show</h6>
                        <p class="small mb-0">No students match these filters.</p>
                    </div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $fee = (float) $row['total_fee'];
                    $paid = (float) $row['paid'];
                    $pending = max(0, $fee - $paid);
                    $pct = $fee > 0 ? min(100, round($paid / $fee * 100, 1)) : 100;
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
                        <td class="text-end"><?= money($fee) ?></td>
                        <td class="text-end text-success"><?= money($paid) ?></td>
                        <td class="text-end <?= $pending > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= money($pending) ?></td>
                        <td>
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted"><?= $pct ?>%</small>
                                <small><?= $pending > 0 ? 'Due' : 'Paid' ?></small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-primary' : 'bg-warning') ?>"
                                     style="width: <?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($pending > 0): ?>
                                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/fees/index.php?collect=1&student_id=' . $row['id'])) ?>" title="Collect fee">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/view.php?id=' . $row['id'])) ?>" title="Student profile">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($p['pages'] > 1): ?>
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted"><?= number_format((int) $p['total']) ?> student(s)</small>
            <?php render_pagination($p); ?>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
