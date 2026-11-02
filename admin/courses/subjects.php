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
        $stmt = db()->prepare('SELECT * FROM subjects WHERE id = ?');
        $stmt->execute([$deleteId]);
        $subject = $stmt->fetch();

        if ($subject) {
            db()->prepare('DELETE FROM subjects WHERE id = ?')->execute([$deleteId]);
            flash('success', $subject['name'] . ' has been deleted.');
            redirect('admin/courses/subjects.php?course_id=' . (int) $subject['course_id']);
        }
        flash('danger', 'Subject not found.');
        redirect('admin/courses/subjects.php');
    }

    $subjectId  = post_int('id');
    $courseId   = post_int('course_id');
    $semester   = post_int('semester', 1);
    $code       = strtoupper(post('code'));
    $name       = post('name');
    $teacherId  = post_int('teacher_id');

    if ($courseId <= 0) {
        $errors[] = 'Please select a course.';
    }
    if ($code === '') {
        $errors[] = 'Subject code is required.';
    }
    if ($name === '') {
        $errors[] = 'Subject name is required.';
    }
    if ($semester < 1 || $semester > 8) {
        $errors[] = 'Semester must be between 1 and 8.';
    }

    if (!$errors) {
        $sql = 'SELECT COUNT(*) FROM subjects WHERE course_id = ? AND code = ?';
        $params = [$courseId, $code];
        if ($subjectId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $subjectId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That subject code already exists for this course.';
        }
    }

    if (!$errors) {
        $teacher = $teacherId > 0 ? $teacherId : null;

        if ($subjectId > 0) {
            $stmt = db()->prepare(
                'UPDATE subjects SET course_id = ?, semester = ?, code = ?, name = ?, teacher_id = ? WHERE id = ?'
            );
            $stmt->execute([$courseId, $semester, $code, $name, $teacher, $subjectId]);
            flash('success', 'Subject updated successfully.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO subjects (course_id, semester, code, name, teacher_id) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$courseId, $semester, $code, $name, $teacher]);
            flash('success', $name . ' has been added.');
        }

        redirect('admin/courses/subjects.php?course_id=' . $courseId . '&semester=' . $semester);
    }
}

// ---------------------------------------------------------------- editing
$editSubject = null;
$editId = query_int('edit');
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM subjects WHERE id = ?');
    $stmt->execute([$editId]);
    $editSubject = $stmt->fetch() ?: null;
}

$formValues = [
    'id'         => $editSubject['id'] ?? 0,
    'course_id'  => $editSubject['course_id'] ?? query_int('course_id'),
    'semester'   => $editSubject['semester'] ?? (query_int('semester') ?: 1),
    'code'       => $editSubject['code'] ?? '',
    'name'       => $editSubject['name'] ?? '',
    'teacher_id' => $editSubject['teacher_id'] ?? 0,
];

if (is_post() && $errors) {
    $formValues = [
        'id'         => post_int('id'),
        'course_id'  => post_int('course_id'),
        'semester'   => post_int('semester', 1),
        'code'       => post('code'),
        'name'       => post('name'),
        'teacher_id' => post_int('teacher_id'),
    ];
}

// ---------------------------------------------------------------- listing
$filterCourse   = query_int('course_id');
$filterSemester = query_int('semester');
$q              = query('q');

$where  = [];
$params = [];
if ($filterCourse > 0) {
    $where[] = 'su.course_id = ?';
    $params[] = $filterCourse;
}
if ($filterSemester > 0) {
    $where[] = 'su.semester = ?';
    $params[] = $filterSemester;
}
if ($q !== '') {
    $where[] = '(su.name LIKE ? OR su.code LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare('SELECT COUNT(*) FROM subjects su' . $whereSql);
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

$sql = 'SELECT su.*, c.name AS course_name, c.code AS course_code, t.full_name AS teacher_name
        FROM subjects su
        JOIN courses c ON c.id = su.course_id
        LEFT JOIN teachers t ON t.id = su.teacher_id' . $whereSql . '
        ORDER BY c.name, su.semester, su.name
        LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

$page_title    = 'Subjects';
$page_subtitle = 'Manage subjects, semesters and teacher assignments';
$active        = 'subjects';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-<?= $formValues['id'] ? 'pencil-square' : 'plus-circle' ?> me-2 text-primary"></i>
                <?= $formValues['id'] ? 'Edit subject' : 'Add subject' ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $formValues['id'] ? 'update' : 'add' ?>">
                    <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">

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
                            <label class="form-label required">Subject code</label>
                            <input class="form-control" name="code" value="<?= e($formValues['code']) ?>" placeholder="e.g. BCA101" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label required">Subject name</label>
                        <input class="form-control" name="name" value="<?= e($formValues['name']) ?>" required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Assigned teacher</label>
                        <select class="form-select" name="teacher_id">
                            <option value="0">— Unassigned —</option>
                            <?php foreach (get_teachers() as $teacher): ?>
                                <option value="<?= (int) $teacher['id'] ?>"<?= selected($formValues['teacher_id'], $teacher['id']) ?>>
                                    <?= e($teacher['full_name']) ?><?= $teacher['qualification'] ? ' — ' . e($teacher['qualification']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2 me-1"></i><?= $formValues['id'] ? 'Save changes' : 'Add subject' ?>
                        </button>
                        <?php if ($formValues['id']): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(url('admin/courses/subjects.php')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card table-card">
            <div class="card-header">
                <form class="toolbar mb-0" method="get">
                    <div class="search-box input-icon">
                        <i class="bi bi-search"></i>
                        <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search subject…">
                    </div>
                    <select class="form-select w-auto" name="course_id">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"<?= selected($filterCourse, $course['id']) ?>><?= e($course['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select w-auto" name="semester">
                        <option value="">All semesters</option>
                        <?php foreach (semesters() as $semester): ?>
                            <option value="<?= $semester ?>"<?= selected($filterSemester, $semester) ?>>Sem <?= $semester ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i></button>
                    <?php if ($q !== '' || $filterCourse || $filterSemester): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/courses/subjects.php')) ?>">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Subject</th><th>Course</th><th>Sem</th><th>Teacher</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$subjects): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">No subjects match your filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($subject['name']) ?></div>
                                    <small class="text-muted"><?= e($subject['code']) ?></small>
                                </td>
                                <td><?= e($subject['course_code']) ?></td>
                                <td><?= (int) $subject['semester'] ?></td>
                                <td class="text-muted"><?= e($subject['teacher_name'] ?: 'Unassigned') ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-soft" href="<?= e(url('admin/courses/subjects.php?edit=' . $subject['id'])) ?>" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form class="d-inline" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $subject['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                data-confirm="Delete <?= e($subject['name']) ?>? Marks recorded for it will also be removed.">
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
                    <small class="text-muted"><?= number_format((int) $p['total']) ?> subjects</small>
                    <?php render_pagination($p); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
