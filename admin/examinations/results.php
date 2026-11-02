<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();

$exams = $pdo->query(
    'SELECT e.*, c.name AS course_name, c.code AS course_code
     FROM examinations e JOIN courses c ON c.id = e.course_id
     ORDER BY e.exam_date DESC, e.id DESC'
)->fetchAll();

$examId = query_int('exam_id') ?: post_int('exam_id');
$exam = null;
if ($examId > 0) {
    $stmt = $pdo->prepare(
        'SELECT e.*, c.name AS course_name, c.code AS course_code
         FROM examinations e JOIN courses c ON c.id = e.course_id
         WHERE e.id = ?'
    );
    $stmt->execute([$examId]);
    $exam = $stmt->fetch() ?: null;
}

$subjects = [];
$students = [];
if ($exam) {
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE course_id = ? AND semester = ? ORDER BY name');
    $stmt->execute([$exam['course_id'], $exam['semester']]);
    $subjects = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT * FROM students WHERE course_id = ? AND semester = ? AND status = 'Active' ORDER BY full_name"
    );
    $stmt->execute([$exam['course_id'], $exam['semester']]);
    $students = $stmt->fetchAll();
}

// ---------------------------------------------------------------- save marks
if (is_post() && $exam) {
    verify_csrf();

    if (!$subjects || !$students) {
        flash('danger', 'Add subjects and students for this class before entering marks.');
        redirect('admin/examinations/results.php?exam_id=' . $examId);
    }

    $marks = $_POST['marks'] ?? [];
    $errors = [];
    $values = [];

    if (!is_array($marks)) {
        $marks = [];
    }

    foreach ($students as $student) {
        foreach ($subjects as $subject) {
            $raw = $marks[$student['id']][$subject['id']] ?? '';
            $raw = is_scalar($raw) ? trim((string) $raw) : '';

            if ($raw === '') {
                $values[] = [(int) $student['id'], (int) $subject['id'], null];
                continue;
            }
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                $errors[] = 'Marks must be between 0 and 100 for ' . $student['full_name'] . ' / ' . $subject['name'] . '.';
                continue;
            }
            $values[] = [(int) $student['id'], (int) $subject['id'], (float) $raw];
        }
    }

    if (!$errors) {
        $delete = $pdo->prepare('DELETE FROM results WHERE examination_id = ? AND student_id = ? AND subject_id = ?');
        $upsert = $pdo->prepare(
            'INSERT INTO results (examination_id, student_id, subject_id, marks_obtained, max_marks)
             VALUES (?, ?, ?, ?, 100)
             ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), max_marks = VALUES(max_marks)'
        );

        $pdo->beginTransaction();
        try {
            $saved = 0;
            foreach ($values as [$studentId, $subjectId, $value]) {
                if ($value === null) {
                    $delete->execute([$examId, $studentId, $subjectId]);
                } else {
                    $upsert->execute([$examId, $studentId, $subjectId, $value]);
                    $saved++;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', 'Marks could not be saved. Please try again.');
            redirect('admin/examinations/results.php?exam_id=' . $examId);
        }

        flash('success', 'Marks saved for ' . $saved . ' subject record(s).');
        redirect('admin/examinations/results.php?exam_id=' . $examId);
    }

    foreach ($errors as $error) {
        flash('danger', $error);
    }
    redirect('admin/examinations/results.php?exam_id=' . $examId);
}

// ---------------------------------------------------------------- existing marks
$existing = [];
if ($exam) {
    $stmt = $pdo->prepare('SELECT * FROM results WHERE examination_id = ?');
    $stmt->execute([$examId]);
    foreach ($stmt->fetchAll() as $row) {
        $existing[(int) $row['student_id']][(int) $row['subject_id']] = $row;
    }
}

// ---------------------------------------------------------------- result sheet
$sheetStudent = null;
$sheetRows    = [];
$sheetTotals  = ['obtained' => 0.0, 'max' => 0.0];
$sheetPct     = 0.0;

$studentId = query_int('student_id');
if ($exam && $studentId > 0) {
    foreach ($students as $student) {
        if ((int) $student['id'] === $studentId) {
            $sheetStudent = $student;
            break;
        }
    }

    if ($sheetStudent) {
        $stmt = $pdo->prepare(
            'SELECT r.*, su.name AS subject_name, su.code AS subject_code
             FROM results r
             JOIN subjects su ON su.id = r.subject_id
             WHERE r.examination_id = ? AND r.student_id = ?
             ORDER BY su.name'
        );
        $stmt->execute([$examId, $studentId]);
        $sheetRows = $stmt->fetchAll();

        foreach ($sheetRows as $row) {
            $sheetTotals['obtained'] += (float) $row['marks_obtained'];
            $sheetTotals['max']      += (float) $row['max_marks'];
        }
        $sheetPct = $sheetTotals['max'] > 0 ? round($sheetTotals['obtained'] / $sheetTotals['max'] * 100, 1) : 0.0;
    }
}

$page_title    = 'Results';
$page_subtitle = 'Enter marks and generate student result sheets';
$active        = 'results';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-md-7">
                <label class="form-label">Examination</label>
                <select class="form-select" name="exam_id" required>
                    <option value="">— Select examination —</option>
                    <?php foreach ($exams as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"<?= selected($examId, $option['id']) ?>>
                            <?= e($option['name']) ?> — <?= e($option['course_code']) ?> (Sem <?= (int) $option['semester'] ?>) — <?= format_date($option['exam_date']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Open examination</button>
            </div>
            <div class="col-md-2">
                <a class="btn btn-soft w-100" href="<?= e(url('admin/examinations/index.php')) ?>">Manage exams</a>
            </div>
        </form>
    </div>
</div>

<?php if (!$exam): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-clipboard2-check"></i>
            <h6 class="mb-1">Select an examination</h6>
            <p class="small mb-0">Choose an examination above to enter marks or view result sheets.</p>
        </div>
    </div>
<?php elseif ($sheetStudent): ?>
    <div class="toolbar no-print">
        <a class="btn btn-icon" href="<?= e(url('admin/examinations/results.php?exam_id=' . $examId)) ?>" title="Back to marks">
            <i class="bi bi-arrow-left"></i>
        </a>
        <span class="text-muted small">Result sheet</span>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/students/view.php?id=' . $sheetStudent['id'])) ?>">
                <i class="bi bi-person me-1"></i>Student profile
            </a>
            <button class="btn btn-primary" type="button" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print result
            </button>
        </div>
    </div>

    <div class="card mx-auto" style="max-width: 860px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center border-bottom pb-4 mb-4">
                <h2 class="h4 fw-bold mb-1"><?= e(APP_NAME) ?></h2>
                <p class="text-muted mb-0">Statement of Examination Results</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <ul class="info-list">
                        <li><span class="label">Student</span><span class="value"><?= e($sheetStudent['full_name']) ?></span></li>
                        <li><span class="label">Student ID</span><span class="value"><?= e($sheetStudent['student_id']) ?></span></li>
                        <li><span class="label">Course</span><span class="value"><?= e($exam['course_name']) ?></span></li>
                    </ul>
                </div>
                <div class="col-sm-6">
                    <ul class="info-list">
                        <li><span class="label">Examination</span><span class="value"><?= e($exam['name']) ?></span></li>
                        <li><span class="label">Semester</span><span class="value"><?= (int) $exam['semester'] ?></span></li>
                        <li><span class="label">Exam date</span><span class="value"><?= format_date($exam['exam_date']) ?></span></li>
                    </ul>
                </div>
            </div>

            <div class="result-sheet mb-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Code</th><th>Subject</th><th class="text-end">Max</th><th class="text-end">Obtained</th><th class="text-end">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$sheetRows): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No marks have been recorded for this student.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($sheetRows as $row): ?>
                                <?php
                                $max = (float) $row['max_marks'];
                                $obtained = (float) $row['marks_obtained'];
                                $subjectPass = $max > 0 && ($obtained / $max * 100) >= 40;
                                ?>
                                <tr>
                                    <td class="text-muted"><?= e($row['subject_code']) ?></td>
                                    <td class="fw-semibold"><?= e($row['subject_name']) ?></td>
                                    <td class="text-end"><?= $max ?></td>
                                    <td class="text-end fw-semibold"><?= $obtained ?></td>
                                    <td class="text-end"><?= status_badge($subjectPass ? 'Pass' : 'Fail') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="2" class="fw-bold text-end">Total</td>
                                <td class="text-end fw-bold"><?= $sheetTotals['max'] ?></td>
                                <td class="text-end fw-bold"><?= $sheetTotals['obtained'] ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="row g-3 mb-5">
                <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Percentage</div><div class="value"><?= $sheetPct ?>%</div></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Grade</div><div class="value"><?= calculate_grade($sheetPct) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Result</div><div class="value"><?= $sheetRows ? ($sheetPct >= 40 ? 'Pass' : 'Fail') : '—' ?></div></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><div class="label">Subjects</div><div class="value"><?= count($sheetRows) ?></div></div></div>
            </div>

            <div class="d-flex justify-content-between align-items-end border-top pt-4">
                <div class="text-muted small">Generated on <?= date('d M Y, H:i') ?> by <?= e($currentAdmin['full_name']) ?>.</div>
                <div class="text-center">
                    <div style="height:34px;"></div>
                    <div class="border-top pt-1 px-4 small">Controller of Examinations</div>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$subjects): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-book-half"></i>
            <h6 class="mb-1">No subjects for this class</h6>
            <p class="small mb-3">Add subjects to <?= e($exam['course_name']) ?> semester <?= (int) $exam['semester'] ?> before entering marks.</p>
            <a class="btn btn-primary btn-sm" href="<?= e(url('admin/courses/subjects.php?course_id=' . $exam['course_id'] . '&semester=' . $exam['semester'])) ?>">Add subjects</a>
        </div>
    </div>
<?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="exam_id" value="<?= (int) $exam['id'] ?>">

        <div class="card table-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="bi bi-pencil-square me-2 text-primary"></i>
                    <?= e($exam['name']) ?> &middot; <?= e($exam['course_code']) ?> &middot; Semester <?= (int) $exam['semester'] ?>
                </span>
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save marks</button>
            </div>

            <?php if (!$students): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h6 class="mb-1">No active students</h6>
                    <p class="small mb-0">There are no active students in this class.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <?php foreach ($subjects as $subject): ?>
                                    <th class="text-center" title="<?= e($subject['name']) ?>">
                                        <?= e($subject['code']) ?>
                                        <div class="fw-normal text-lowercase text-muted" style="font-size:.66rem;"><?= e(mb_strimwidth($subject['name'], 0, 18, '…')) ?></div>
                                    </th>
                                <?php endforeach; ?>
                                <th class="text-end">Sheet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="person-cell">
                                            <span class="avatar avatar-sm"><?= e(initials($student['full_name'])) ?></span>
                                            <div class="meta">
                                                <strong><?= e($student['full_name']) ?></strong>
                                                <small><?= e($student['student_id']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <?php foreach ($subjects as $subject): ?>
                                        <?php $mark = $existing[(int) $student['id']][(int) $subject['id']] ?? null; ?>
                                        <td class="text-center">
                                            <input class="form-control form-control-sm text-center mx-auto" style="max-width: 78px;"
                                                   type="number" min="0" max="100" step="0.01"
                                                   name="marks[<?= (int) $student['id'] ?>][<?= (int) $subject['id'] ?>]"
                                                   value="<?= $mark ? e((string) (float) $mark['marks_obtained']) : '' ?>"
                                                   placeholder="—">
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-soft" href="<?= e(url('admin/examinations/results.php?exam_id=' . $exam['id'] . '&student_id=' . $student['id'])) ?>" title="View result sheet">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">Leave a cell blank to remove that mark. Maximum marks per subject: 100.</small>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save marks</button>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
