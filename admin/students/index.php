<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$q        = query('q');
$courseId = query_int('course_id');
$semester = query_int('semester');
$status   = query('status');

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.full_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($courseId > 0) {
    $where[] = 's.course_id = ?';
    $params[] = $courseId;
}
if ($semester > 0) {
    $where[] = 's.semester = ?';
    $params[] = $semester;
}
if ($status !== '' && in_array($status, ['Active', 'Inactive', 'Graduated'], true)) {
    $where[] = 's.status = ?';
    $params[] = $status;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare('SELECT COUNT(*) FROM students s' . $whereSql);
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

$sql = 'SELECT s.*, c.name AS course_name, c.code AS course_code
        FROM students s
        JOIN courses c ON c.id = s.course_id' . $whereSql . '
        ORDER BY s.created_at DESC, s.id DESC
        LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title    = 'Students';
$page_subtitle = 'Manage student records, admissions and profiles';
$active        = 'students';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="mini-stat">
            <div class="label">All students</div>
            <div class="value"><?= number_format(table_count('students')) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mini-stat">
            <div class="label">Active</div>
            <div class="value text-success"><?= number_format(table_count('students', "status = 'Active'")) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mini-stat">
            <div class="label">Graduated</div>
            <div class="value text-info"><?= number_format(table_count('students', "status = 'Graduated'")) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mini-stat">
            <div class="label">Inactive</div>
            <div class="value text-secondary"><?= number_format(table_count('students', "status = 'Inactive'")) ?></div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="toolbar mb-0" method="get">
            <div class="search-box input-icon">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, ID, email or phone…">
            </div>
            <select class="form-select w-auto" name="course_id">
                <option value="">All courses</option>
                <?php foreach (get_courses() as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"<?= selected($courseId, $course['id']) ?>><?= e($course['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select w-auto" name="semester">
                <option value="">All semesters</option>
                <?php foreach (semesters() as $s): ?>
                    <option value="<?= $s ?>"<?= selected($semester, $s) ?>>Sem <?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select w-auto" name="status">
                <option value="">All statuses</option>
                <?php foreach (['Active', 'Inactive', 'Graduated'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($status, $s) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <?php if ($q !== '' || $courseId || $semester || $status !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/students/index.php')) ?>">Reset</a>
            <?php endif; ?>
            <a class="btn btn-soft ms-auto" href="<?= e(url('admin/students/create.php')) ?>">
                <i class="bi bi-plus-lg me-1"></i>Add student
            </a>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Semester</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$students): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h6 class="mb-1">No students found</h6>
                                <p class="small mb-3">Try adjusting your filters or add a new student.</p>
                                <a class="btn btn-primary btn-sm" href="<?= e(url('admin/students/create.php')) ?>">Register student</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <div class="person-cell">
                                <?php if ($student['photo']): ?>
                                    <img class="avatar avatar-md" src="<?= e(url($student['photo'])) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar avatar-md"><?= e(initials($student['full_name'])) ?></span>
                                <?php endif; ?>
                                <div class="meta">
                                    <strong><?= e($student['full_name']) ?></strong>
                                    <small><?= e($student['student_id']) ?> &middot; <?= e($student['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="d-block"><?= e($student['course_code']) ?></span>
                            <small class="text-muted"><?= e($student['course_name']) ?></small>
                        </td>
                        <td>Sem <?= (int) $student['semester'] ?></td>
                        <td class="text-muted"><?= e($student['phone'] ?: '—') ?></td>
                        <td><?= status_badge($student['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/view.php?id=' . $student['id'])) ?>" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/edit.php?id=' . $student['id'])) ?>" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form class="d-inline" method="post" action="<?= e(url('admin/students/delete.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-confirm="Delete <?= e($student['full_name']) ?>? This also removes their attendance, fees and results.">
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
            <small class="text-muted">
                Showing <?= (int) $p['offset'] + 1 ?>–<?= min((int) $p['total'], (int) $p['offset'] + (int) $p['perPage']) ?>
                of <?= number_format((int) $p['total']) ?> students
            </small>
            <?php render_pagination($p); ?>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
