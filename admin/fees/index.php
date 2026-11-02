<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();

// ---------------------------------------------------------------- collect a payment
$errors = [];
$collect = [
    'student_id'     => query_int('student_id'),
    'amount'         => '',
    'payment_date'   => date('Y-m-d'),
    'payment_method' => 'Cash',
    'remarks'        => '',
];

if (is_post()) {
    verify_csrf();

    $collect = [
        'student_id'     => post_int('student_id'),
        'amount'         => post('amount'),
        'payment_date'   => post('payment_date'),
        'payment_method' => post('payment_method', 'Cash'),
        'remarks'        => post('remarks'),
    ];

    $amount = (float) $collect['amount'];

    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$collect['student_id']]);
    $student = $stmt->fetch();

    if (!$student) {
        $errors[] = 'Please select a valid student.';
    }
    if ($amount <= 0) {
        $errors[] = 'The payment amount must be greater than zero.';
    }
    if ($collect['payment_date'] === '' || !strtotime($collect['payment_date'])) {
        $errors[] = 'Please choose a valid payment date.';
    } elseif ($collect['payment_date'] > date('Y-m-d')) {
        $errors[] = 'The payment date cannot be in the future.';
    }
    if (!in_array($collect['payment_method'], ['Cash', 'Card', 'Bank Transfer', 'Online'], true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if (!$errors && $student) {
        $summary = student_fee_summary((int) $student['id'], (int) $student['course_id']);
        if ($summary['pending'] <= 0) {
            $errors[] = 'This student has already paid the full course fee.';
        } elseif ($amount > $summary['pending'] + 0.001) {
            $errors[] = 'The amount exceeds the pending balance of ' . money($summary['pending']) . '.';
        }
    }

    if (!$errors) {
        $receiptNo = generate_receipt_no();
        $stmt = $pdo->prepare(
            'INSERT INTO fees (student_id, course_id, amount, payment_date, payment_method, receipt_no, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $student['id'],
            $student['course_id'],
            $amount,
            $collect['payment_date'],
            $collect['payment_method'],
            $receiptNo,
            nullable($collect['remarks']),
        ]);

        flash('success', 'Payment of ' . money($amount) . ' recorded. Receipt ' . $receiptNo . ' generated.');
        redirect('admin/fees/receipt.php?id=' . (int) $pdo->lastInsertId());
    }
}

// ---------------------------------------------------------------- summary
$totalCollected = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM fees')->fetchColumn();
$expected = (float) $pdo->query(
    "SELECT COALESCE(SUM(c.total_fee),0) FROM students s JOIN courses c ON c.id = s.course_id WHERE s.status = 'Active'"
)->fetchColumn();
$pendingTotal = max(0, $expected - $totalCollected);
$monthCollected = (float) $pdo->query(
    'SELECT COALESCE(SUM(amount),0) FROM fees WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())'
)->fetchColumn();

// ---------------------------------------------------------------- student options
$studentOptions = $pdo->query(
    "SELECT s.id, s.student_id, s.full_name, s.course_id, c.code AS course_code, c.total_fee,
            COALESCE(SUM(f.amount),0) AS paid
     FROM students s
     JOIN courses c ON c.id = s.course_id
     LEFT JOIN fees f ON f.student_id = s.id
     WHERE s.status = 'Active'
     GROUP BY s.id, s.student_id, s.full_name, s.course_id, c.code, c.total_fee
     ORDER BY s.full_name"
)->fetchAll();

// ---------------------------------------------------------------- payments list
$q        = query('q');
$courseId = query_int('course_id');
$method   = query('method');
$from     = query('from');
$to       = query('to');

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ? OR f.receipt_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($courseId > 0) {
    $where[] = 'f.course_id = ?';
    $params[] = $courseId;
}
if (in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Online'], true)) {
    $where[] = 'f.payment_method = ?';
    $params[] = $method;
}
if ($from !== '' && strtotime($from)) {
    $where[] = 'f.payment_date >= ?';
    $params[] = $from;
}
if ($to !== '' && strtotime($to)) {
    $where[] = 'f.payment_date <= ?';
    $params[] = $to;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM fees f JOIN students s ON s.id = f.student_id' . $whereSql);
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

$sql = 'SELECT f.*, s.full_name, s.student_id AS student_code, c.code AS course_code
        FROM fees f
        JOIN students s ON s.id = f.student_id
        JOIN courses c ON c.id = f.course_id' . $whereSql . '
        ORDER BY f.payment_date DESC, f.id DESC
        LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$showForm = $errors || query_int('student_id') > 0 || query('collect') === '1';

$page_title    = 'Fees';
$page_subtitle = 'Collect payments, track balances and issue receipts';
$active        = 'fees';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Collected</div>
            <div class="value text-success"><?= short_money($totalCollected) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Pending</div>
            <div class="value text-danger"><?= short_money($pendingTotal) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">Expected</div>
            <div class="value"><?= short_money($expected) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mini-stat">
            <div class="label">This month</div>
            <div class="value"><?= short_money($monthCollected) ?></div>
        </div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<div class="toolbar">
    <a class="btn btn-soft<?= $showForm ? ' active' : '' ?>" data-bs-toggle="collapse" href="#collectForm" role="button">
        <i class="bi bi-plus-lg me-1"></i>Collect fee
    </a>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/fees/balances.php')) ?>">
        <i class="bi bi-list-check me-1"></i>Student balances
    </a>
</div>

<div class="collapse<?= $showForm ? ' show' : '' ?>" id="collectForm">
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-cash-coin me-2 text-primary"></i>Record a fee payment</div>
        <div class="card-body">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label required">Student</label>
                        <select class="form-select" name="student_id" id="feeStudent" required>
                            <option value="">— Select student —</option>
                            <?php foreach ($studentOptions as $option): ?>
                                <?php $due = max(0, (float) $option['total_fee'] - (float) $option['paid']); ?>
                                <option value="<?= (int) $option['id'] ?>"
                                        data-due="<?= (float) $due ?>"
                                        data-fee="<?= (float) $option['total_fee'] ?>"
                                        data-paid="<?= (float) $option['paid'] ?>"
                                        <?= (int) $collect['student_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                                    <?= e($option['full_name']) ?> (<?= e($option['student_code']) ?>) — <?= e($option['course_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="dueHint">Select a student to see their outstanding balance.</div>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label required">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= e(CURRENCY) ?></span>
                            <input class="form-control" type="number" name="amount" min="0.01" step="0.01"
                                   value="<?= e($collect['amount']) ?>" required>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label required">Payment date</label>
                        <input class="form-control" type="date" name="payment_date" max="<?= date('Y-m-d') ?>"
                               value="<?= e($collect['payment_date']) ?>" required>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label required">Method</label>
                        <select class="form-select" name="payment_method" required>
                            <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Online'] as $option): ?>
                                <option value="<?= $option ?>"<?= selected($collect['payment_method'], $option) ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Remarks</label>
                        <input class="form-control" name="remarks" value="<?= e($collect['remarks']) ?>" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2-circle me-1"></i>Save payment &amp; generate receipt
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="toolbar mb-0" method="get">
            <div class="search-box input-icon">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Student or receipt no…">
            </div>
            <select class="form-select w-auto" name="course_id">
                <option value="">All courses</option>
                <?php foreach (get_courses() as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>><?= e($course['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select w-auto" name="method">
                <option value="">All methods</option>
                <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Online'] as $option): ?>
                    <option value="<?= $option ?>"<?= selected($method, $option) ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control w-auto" type="date" name="from" value="<?= e($from) ?>" title="From date">
            <input class="form-control w-auto" type="date" name="to" value="<?= e($to) ?>" title="To date">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i></button>
            <?php if ($q !== '' || $courseId || $method !== '' || $from !== '' || $to !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/fees/index.php')) ?>">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Student</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$payments): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-wallet2"></i>
                        <h6 class="mb-1">No payments found</h6>
                        <p class="small mb-0">Record a payment with the “Collect fee” button above.</p>
                    </div></td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><span class="fw-semibold"><?= e($payment['receipt_no']) ?></span></td>
                        <td>
                            <div class="person-cell">
                                <span class="avatar avatar-sm"><?= e(initials($payment['full_name'])) ?></span>
                                <div class="meta">
                                    <strong><?= e($payment['full_name']) ?></strong>
                                    <small><?= e($payment['student_code']) ?> &middot; <?= e($payment['course_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted"><?= format_date($payment['payment_date']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($payment['payment_method']) ?></span></td>
                        <td class="text-end fw-semibold"><?= money($payment['amount']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/fees/receipt.php?id=' . $payment['id'])) ?>" title="Receipt">
                                <i class="bi bi-receipt"></i>
                            </a>
                            <form class="d-inline" method="post" action="<?= e(url('admin/fees/delete.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-confirm="Delete receipt <?= e($payment['receipt_no']) ?>? This cannot be undone.">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($p['pages'] > 1): ?>
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted"><?= number_format((int) $p['total']) ?> payment(s)</small>
            <?php render_pagination($p); ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var select = document.getElementById('feeStudent');
    var hint = document.getElementById('dueHint');
    if (!select || !hint) return;
    var money = function (n) { return '<?= e(CURRENCY) ?>' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function update() {
        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            hint.textContent = 'Select a student to see their outstanding balance.';
            return;
        }
        var due = option.getAttribute('data-due');
        var fee = option.getAttribute('data-fee');
        var paid = option.getAttribute('data-paid');
        hint.textContent = 'Course fee ' + money(fee) + ' · paid ' + money(paid) + ' · outstanding ' + money(due);
    }
    select.addEventListener('change', update);
    update();
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
