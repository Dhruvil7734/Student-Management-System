<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$id = query_int('id');
$stmt = db()->prepare('SELECT * FROM teachers WHERE id = ?');
$stmt->execute([$id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    flash('danger', 'Teacher not found.');
    redirect('admin/teachers/index.php');
}

$stmt = db()->prepare(
    'SELECT su.*, c.name AS course_name, c.code AS course_code
     FROM subjects su
     JOIN courses c ON c.id = su.course_id
     WHERE su.teacher_id = ?
     ORDER BY c.name, su.semester, su.name'
);
$stmt->execute([$id]);
$subjects = $stmt->fetchAll();

$page_title    = 'Teacher Profile';
$page_subtitle = $teacher['full_name'] . ' · ' . $teacher['employee_id'];
$active        = 'teachers';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-icon" href="<?= e(url('admin/teachers/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <span class="text-muted small">Teacher profile</span>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('admin/teachers/edit.php?id=' . $teacher['id'])) ?>">
            <i class="bi bi-pencil-square me-1"></i>Edit
        </a>
        <form method="post" action="<?= e(url('admin/teachers/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>">
            <button class="btn btn-outline-danger" type="submit"
                    data-confirm="Remove <?= e($teacher['full_name']) ?> from the teaching staff?">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php if ($teacher['photo']): ?>
                    <img class="avatar avatar-xl mb-3" src="<?= e(url($teacher['photo'])) ?>" alt="">
                <?php else: ?>
                    <span class="avatar avatar-xl mb-3"><?= e(initials($teacher['full_name'])) ?></span>
                <?php endif; ?>
                <h2 class="h5 fw-bold mb-1"><?= e($teacher['full_name']) ?></h2>
                <p class="text-muted mb-3"><?= e($teacher['qualification'] ?: 'Teaching Staff') ?></p>
                <span class="badge text-bg-light border mb-4"><?= e($teacher['employee_id']) ?></span>

                <ul class="info-list text-start">
                    <li><span class="label">Email</span><span class="value"><?= e($teacher['email']) ?></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= e($teacher['phone'] ?: '—') ?></span></li>
                    <li><span class="label">Gender</span><span class="value"><?= e($teacher['gender'] ?: '—') ?></span></li>
                    <li><span class="label">Date of birth</span><span class="value"><?= format_date($teacher['dob']) ?></span></li>
                    <li><span class="label">Address</span><span class="value"><?= e($teacher['address'] ?: '—') ?></span></li>
                    <li><span class="label">Joined</span><span class="value"><?= format_date($teacher['created_at']) ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book-half me-2 text-primary"></i>Assigned subjects</span>
                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/courses/subjects.php')) ?>">Manage subjects</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Subject</th><th>Course</th><th>Semester</th></tr></thead>
                    <tbody>
                        <?php if (!$subjects): ?>
                            <tr><td colspan="3" class="text-center text-muted py-5">
                                No subjects assigned yet. Assign subjects from the course management page.
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($subject['name']) ?></div>
                                    <small class="text-muted"><?= e($subject['code']) ?></small>
                                </td>
                                <td><?= e($subject['course_code']) ?></td>
                                <td>Sem <?= (int) $subject['semester'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
