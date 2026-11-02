<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id');
$stmt = db()->prepare(
    'SELECT f.*, s.full_name, s.student_id AS student_code, s.email, s.phone, s.semester,
            c.name AS course_name, c.code AS course_code, c.total_fee
     FROM fees f
     JOIN students s ON s.id = f.student_id
     JOIN courses c ON c.id = f.course_id
     WHERE f.id = ?'
);
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    flash('danger', 'Receipt not found.');
    redirect('admin/fees/index.php');
}

$stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id = ?');
$stmt->execute([$payment['student_id']]);
$totalPaid = (float) $stmt->fetchColumn();
$balance = max(0, (float) $payment['total_fee'] - $totalPaid);

$page_title    = 'Payment Receipt';
$page_subtitle = $payment['receipt_no'];
$active        = 'fees';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar no-print">
    <a class="btn btn-icon" href="<?= e(url('admin/fees/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <span class="text-muted small">Fee receipt</span>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/students/view.php?id=' . $payment['student_id'])) ?>">
            <i class="bi bi-person me-1"></i>Student profile
        </a>
        <button class="btn btn-primary" type="button" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print receipt
        </button>
    </div>
</div>

<div class="card mx-auto" style="max-width: 820px;">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <div>
                    <h2 class="h5 fw-bold mb-0"><?= e(APP_NAME) ?></h2>
                    <div class="text-muted small">Official fee payment receipt</div>
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small text-uppercase">Receipt no.</div>
                <div class="fw-bold"><?= e($payment['receipt_no']) ?></div>
                <div class="text-muted small mt-1"><?= format_date($payment['payment_date']) ?></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-sm-6">
                <div class="text-muted small text-uppercase mb-1">Received from</div>
                <div class="fw-bold fs-6"><?= e($payment['full_name']) ?></div>
                <div class="text-muted small"><?= e($payment['student_code']) ?> &middot; Semester <?= (int) $payment['semester'] ?></div>
                <div class="text-muted small"><?= e($payment['email']) ?><?= $payment['phone'] ? ' &middot; ' . e($payment['phone']) : '' ?></div>
            </div>
            <div class="col-sm-6 text-sm-end">
                <div class="text-muted small text-uppercase mb-1">Course</div>
                <div class="fw-bold"><?= e($payment['course_name']) ?></div>
                <div class="text-muted small"><?= e($payment['course_code']) ?> &middot; Total fee <?= money($payment['total_fee']) ?></div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Description</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-semibold">Tuition fee payment</div>
                            <small class="text-muted">
                                <?= e($payment['course_name']) ?> — Semester <?= (int) $payment['semester'] ?>
                                <?= $payment['remarks'] ? ' · ' . e($payment['remarks']) : '' ?>
                            </small>
                        </td>
                        <td class="text-end fw-semibold"><?= money($payment['amount']) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="text-end text-muted">Payment method</td>
                        <td class="text-end"><?= e($payment['payment_method']) ?></td>
                    </tr>
                    <tr class="table-light">
                        <td class="text-end fw-bold">Amount paid</td>
                        <td class="text-end fw-bold"><?= money($payment['amount']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row g-3 mb-5">
            <div class="col-6 col-sm-4">
                <div class="mini-stat">
                    <div class="label">Total fee</div>
                    <div class="value fs-6"><?= money($payment['total_fee']) ?></div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="mini-stat">
                    <div class="label">Paid to date</div>
                    <div class="value fs-6 text-success"><?= money($totalPaid) ?></div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="mini-stat">
                    <div class="label">Balance</div>
                    <div class="value fs-6 <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= money($balance) ?></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-end pt-4 border-top">
            <div class="text-muted small">
                This is a computer-generated receipt.<br>
                Generated on <?= date('d M Y, H:i') ?> by <?= e($currentAdmin['full_name']) ?>.
            </div>
            <div class="text-center">
                <div style="height:34px;"></div>
                <div class="border-top pt-1 px-4 small">Authorised signature</div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
