<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id');
$stmt = db()->prepare(
    'SELECT s.*, c.name AS course_name, c.code AS course_code, c.duration_years
     FROM students s JOIN courses c ON c.id = s.course_id
     WHERE s.id = ?'
);
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('admin/students/index.php');
}

$attendance = attendance_summary((int) $student['id']);
$fees       = student_fee_summary((int) $student['id'], (int) $student['course_id']);

$stmt = db()->prepare(
    'SELECT f.* FROM fees f WHERE f.student_id = ? ORDER BY f.payment_date DESC, f.id DESC LIMIT 5'
);
$stmt->execute([$student['id']]);
$payments = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT * FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 7'
);
$stmt->execute([$student['id']]);
$attendanceRows = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT e.id AS exam_id, e.name AS exam_name, e.exam_date,
            SUM(r.marks_obtained) AS obtained, SUM(r.max_marks) AS max_marks
     FROM results r
     JOIN examinations e ON e.id = r.examination_id
     WHERE r.student_id = ?
     GROUP BY e.id, e.name, e.exam_date
     ORDER BY e.exam_date DESC"
);
$stmt->execute([$student['id']]);
$examResults = $stmt->fetchAll();

$page_title    = 'Student Profile';
$page_subtitle = $student['full_name'] . ' · ' . $student['student_id'];
$active        = 'students';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-icon" href="<?= e(url('admin/students/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <span class="text-muted small">Student profile</span>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-soft" href="<?= e(url('admin/fees/index.php?student_id=' . $student['id'])) ?>">
            <i class="bi bi-wallet2 me-1"></i>Collect fee
        </a>
        <a class="btn btn-primary" href="<?= e(url('admin/students/edit.php?id=' . $student['id'])) ?>">
            <i class="bi bi-pencil-square me-1"></i>Edit
        </a>
        <form method="post" action="<?= e(url('admin/students/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
            <button class="btn btn-outline-danger" type="submit"
                    data-confirm="Delete <?= e($student['full_name']) ?> and all related records?">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
            <?php if ($student['photo']): ?>
                <img class="avatar avatar-xl" src="<?= e(url($student['photo'])) ?>" alt="<?= e($student['full_name']) ?>">
            <?php else: ?>
                <span class="avatar avatar-xl"><?= e(initials($student['full_name'])) ?></span>
            <?php endif; ?>

            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="h4 mb-0 fw-bold"><?= e($student['full_name']) ?></h2>
                    <?= status_badge($student['status']) ?>
                </div>
                <p class="text-muted mb-2"><?= e($student['student_id']) ?> &middot; <?= e($student['email']) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border"><i class="bi bi-journal-bookmark me-1"></i><?= e($student['course_name']) ?></span>
                    <span class="badge text-bg-light border"><i class="bi bi-layers me-1"></i>Semester <?= (int) $student['semester'] ?></span>
                    <span class="badge text-bg-light border"><i class="bi bi-calendar3 me-1"></i>Admitted <?= format_date($student['admission_date']) ?></span>
                    <?php if ($student['gender']): ?>
                        <span class="badge text-bg-light border"><i class="bi bi-person me-1"></i><?= e($student['gender']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-md-end">
                <div class="text-muted small">Attendance</div>
                <div class="fs-3 fw-bold <?= $attendance['percentage'] >= 75 ? 'text-success' : 'text-danger' ?>">
                    <?= $attendance['percentage'] ?>%
                </div>
                <small class="text-muted"><?= $attendance['present'] + $attendance['late'] ?> of <?= $attendance['total'] ?> classes</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Total course fee</div>
            <div class="value"><?= money($fees['total']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Paid</div>
            <div class="value text-success"><?= money($fees['paid']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Pending</div>
            <div class="value text-danger"><?= money($fees['pending']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Attendance</div>
            <div class="value"><?= $attendance['present'] ?> P / <?= $attendance['late'] ?> L / <?= $attendance['absent'] ?> A</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-2 text-primary"></i>Personal details</div>
            <div class="card-body">
                <ul class="info-list">
                    <li><span class="label">Student ID</span><span class="value"><?= e($student['student_id']) ?></span></li>
                    <li><span class="label">Full name</span><span class="value"><?= e($student['full_name']) ?></span></li>
                    <li><span class="label">Email</span><span class="value"><?= e($student['email']) ?></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= e($student['phone'] ?: '—') ?></span></li>
                    <li><span class="label">Gender</span><span class="value"><?= e($student['gender'] ?: '—') ?></span></li>
                    <li><span class="label">Date of birth</span><span class="value"><?= format_date($student['dob']) ?></span></li>
                    <li><span class="label">Address</span><span class="value"><?= e($student['address'] ?: '—') ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cash-coin me-2 text-primary"></i>Recent payments</span>
                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/fees/index.php?student_id=' . $student['id'])) ?>">All payments</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php if (!$payments): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No payments recorded.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><a href="<?= e(url('admin/fees/receipt.php?id=' . $payment['id'])) ?>"><?= e($payment['receipt_no']) ?></a></td>
                                <td class="text-muted"><?= format_date($payment['payment_date']) ?></td>
                                <td><?= e($payment['payment_method']) ?></td>
                                <td class="text-end fw-semibold"><?= money($payment['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-calendar2-check me-2 text-primary"></i>Recent attendance</div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Day</th><th class="text-end">Status</th></tr></thead>
                    <tbody>
                        <?php if (!$attendanceRows): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No attendance marked yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($attendanceRows as $row): ?>
                            <tr>
                                <td><?= format_date($row['attendance_date']) ?></td>
                                <td class="text-muted"><?= date('l', strtotime($row['attendance_date'])) ?></td>
                                <td class="text-end"><?= status_badge($row['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart-line me-2 text-primary"></i>Examination results</span>
        <a class="btn btn-sm btn-soft" href="<?= e(url('admin/examinations/results.php?student_id=' . $student['id'])) ?>">Open result sheet</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Examination</th><th>Date</th><th>Marks</th><th>Percentage</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (!$examResults): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No results recorded for this student.</td></tr>
                <?php endif; ?>
                <?php foreach ($examResults as $result): ?>
                    <?php
                    $pct = (float) $result['max_marks'] > 0 ? round((float) $result['obtained'] / (float) $result['max_marks'] * 100, 1) : 0;
                    $pass = $pct >= 40;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($result['exam_name']) ?></td>
                        <td class="text-muted"><?= format_date($result['exam_date']) ?></td>
                        <td><?= (float) $result['obtained'] ?> / <?= (float) $result['max_marks'] ?></td>
                        <td><?= $pct ?>%</td>
                        <td><span class="badge text-bg-light border"><?= calculate_grade($pct) ?></span></td>
                        <td><?= status_badge($pass ? 'Pass' : 'Fail') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
