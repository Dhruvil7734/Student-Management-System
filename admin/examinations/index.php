<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$courses = get_courses();
$errors  = [];

// ---------------------------------------------------------------- mutations
if (is_post()) {
    verify_csrf();
    $action = post('action', 'add');

    if ($action === 'delete') {
        $deleteId = post_int('id');
        $stmt = db()->prepare('SELECT * FROM examinations WHERE id = ?');
        $stmt->execute([$deleteId]);
        $exam = $stmt->fetch();

        if ($exam) {
            db()->prepare('DELETE FROM examinations WHERE id = ?')->execute([$deleteId]);
            flash('success', $exam['name'] . ' and its results have been deleted.');
        } else {
            flash('danger', 'Examination not found.');
        }
        redirect('admin/examinations/index.php');
    }

    $examId   = post_int('id');
    $name     = post('name');
    $courseId = post_int('course_id');
    $semester = post_int('semester', 1);
    $examDate = post('exam_date');

    if ($name === '') {
        $errors[] = 'Examination name is required.';
    }
    if ($courseId <= 0) {
        $errors[] = 'Please select a course.';
    }
    if ($semester < 1 || $semester > 8) {
        $errors[] = 'Semester must be between 1 and 8.';
    }
    if ($examDate === '' || !strtotime($examDate)) {
        $errors[] = 'A valid examination date is required.';
    }

    if (!$errors) {
        if ($examId > 0) {
            $stmt = db()->prepare(
                'UPDATE examinations SET name = ?, course_id = ?, semester = ?, exam_date = ? WHERE id = ?'
            );
            $stmt->execute([$name, $courseId, $semester, $examDate, $examId]);
            flash('success', 'Examination updated successfully.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO examinations (name, course_id, semester, exam_date) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$name, $courseId, $semester, $examDate]);
            flash('success', $name . ' has been created.');
        }
        redirect('admin/examinations/index.php');
    }
}

// ---------------------------------------------------------------- edit prefill
$editExam = null;
$editId = query_int('edit');
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM examinations WHERE id = ?');
    $stmt->execute([$editId]);
    $editExam = $stmt->fetch() ?: null;
}

$formValues = [
    'id'        => $editExam['id'] ?? 0,
    'name'      => $editExam['name'] ?? '',
    'course_id' => $editExam['course_id'] ?? '',
    'semester'  => $editExam['semester'] ?? 1,
    'exam_date' => $editExam['exam_date'] ?? date('Y-m-d'),
];

if (is_post() && $errors && post('action') !== 'delete') {
    $formValues = [
        'id'        => post_int('id'),
        'name'      => post('name'),
        'course_id' => post_int('course_id'),
        'semester'  => post_int('semester', 1),
        'exam_date' => post('exam_date'),
    ];
}

// ---------------------------------------------------------------- listing
$exams = db()->query(
    'SELECT e.*, c.name AS course_name, c.code AS course_code, COUNT(r.id) AS result_count
     FROM examinations e
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN results r ON r.examination_id = e.id
     GROUP BY e.id
     ORDER BY e.exam_date DESC, e.id DESC'
)->fetchAll();

$page_title    = 'Examinations';
$page_subtitle = 'Schedule examinations and record student marks';
$active        = 'examinations';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-<?= $formValues['id'] ? 'pencil-square' : 'plus-circle' ?> me-2 text-primary"></i>
                <?= $formValues['id'] ? 'Edit examination' : 'Create examination' ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $formValues['id'] ? 'update' : 'add' ?>">
                    <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label required">Examination name</label>
                        <input class="form-control" name="name" value="<?= e($formValues['name']) ?>"
                               placeholder="e.g. Mid-Term Examination 2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Course</label>
                        <select class="form-select" name="course_id" required>
                            <option value="">— Select course —</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['id'] ?>"<?= selected($formValues['course_id'], $course['id']) ?>>
                                    <?= e($course['name']) ?> (<?= e($course['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-5">
                            <label class="form-label required">Semester</label>
                            <select class="form-select" name="semester" required>
                                <?php foreach (semesters() as $semester): ?>
                                    <option value="<?= $semester ?>"<?= selected($formValues['semester'], $semester) ?>>Sem <?= $semester ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-7">
                            <label class="form-label required">Exam date</label>
                            <input class="form-control" type="date" name="exam_date" value="<?= e($formValues['exam_date']) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2 me-1"></i><?= $formValues['id'] ? 'Save changes' : 'Create exam' ?>
                        </button>
                        <?php if ($formValues['id']): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(url('admin/examinations/index.php')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard2-check me-2 text-primary"></i>Scheduled examinations</span>
                <span class="badge text-bg-light border"><?= count($exams) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Examination</th><th>Course</th><th>Date</th><th class="text-center">Results</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$exams): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">No examinations scheduled yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($exam['name']) ?></div>
                                    <small class="text-muted">Semester <?= (int) $exam['semester'] ?></small>
                                </td>
                                <td><?= e($exam['course_code']) ?></td>
                                <td class="text-muted"><?= format_date($exam['exam_date']) ?></td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border"><?= (int) $exam['result_count'] ?> marks</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-primary" href="<?= e(url('admin/examinations/results.php?exam_id=' . $exam['id'])) ?>" title="Enter marks">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a class="btn btn-sm btn-soft" href="<?= e(url('admin/examinations/index.php?edit=' . $exam['id'])) ?>" title="Edit">
                                        <i class="bi bi-gear"></i>
                                    </a>
                                    <form class="d-inline" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $exam['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                data-confirm="Delete <?= e($exam['name']) ?>? All marks recorded for it will be removed.">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
