<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$courses = db()->query(
    'SELECT c.*,
            COUNT(DISTINCT s.id)  AS student_count,
            COUNT(DISTINCT su.id) AS subject_count
     FROM courses c
     LEFT JOIN students s  ON s.course_id  = c.id
     LEFT JOIN subjects su ON su.course_id = c.id
     GROUP BY c.id
     ORDER BY c.name'
)->fetchAll();

$page_title    = 'Courses';
$page_subtitle = 'Academic programs, subjects and fees';
$active        = 'courses';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <span class="text-muted small"><?= count($courses) ?> course(s) configured</span>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-soft" href="<?= e(url('admin/courses/subjects.php')) ?>">
            <i class="bi bi-book-half me-1"></i>All subjects
        </a>
        <a class="btn btn-primary" href="<?= e(url('admin/courses/create.php')) ?>">
            <i class="bi bi-plus-lg me-1"></i>Add course
        </a>
    </div>
</div>

<div class="row g-4">
    <?php if (!$courses): ?>
        <div class="col-12">
            <div class="card"><div class="empty-state">
                <i class="bi bi-journal-bookmark"></i>
                <h6 class="mb-1">No courses yet</h6>
                <p class="small mb-3">Create your first academic program to start enrolling students.</p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('admin/courses/create.php')) ?>">Add course</a>
            </div></div>
        </div>
    <?php endif; ?>

    <?php foreach ($courses as $course): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="stat-icon tone-indigo mb-0"><i class="bi bi-journal-bookmark-fill"></i></span>
                        <div class="dropdown">
                            <button class="btn btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Course actions">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="<?= e(url('admin/courses/edit.php?id=' . $course['id'])) ?>">
                                    <i class="bi bi-pencil-square me-2"></i>Edit course</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('admin/courses/subjects.php?course_id=' . $course['id'])) ?>">
                                    <i class="bi bi-book-half me-2"></i>Manage subjects</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="<?= e(url('admin/courses/delete.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
                                        <button class="dropdown-item text-danger" type="submit"
                                                data-confirm="Delete <?= e($course['name']) ?>? Subjects and examinations for this course will also be removed.">
                                            <i class="bi bi-trash3 me-2"></i>Delete course
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <span class="badge text-bg-light border mb-2"><?= e($course['code']) ?></span>
                    <h3 class="h6 fw-bold mb-1"><?= e($course['name']) ?></h3>
                    <p class="text-muted small mb-3">
                        <?= $course['description'] ? e(mb_strimwidth($course['description'], 0, 92, '…')) : 'No description provided.' ?>
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="mini-stat text-center">
                                <div class="label">Students</div>
                                <div class="value"><?= (int) $course['student_count'] ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mini-stat text-center">
                                <div class="label">Subjects</div>
                                <div class="value"><?= (int) $course['subject_count'] ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mini-stat text-center">
                                <div class="label">Years</div>
                                <div class="value"><?= (int) $course['duration_years'] ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Total fee</div>
                            <div class="fw-bold"><?= money($course['total_fee']) ?></div>
                        </div>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/students/index.php?course_id=' . $course['id'])) ?>">
                            View students <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
