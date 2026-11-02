<?php
/**
 * Shared course create/edit form.
 * Expects: $course, $errors, $formAction, $isEdit
 */
$page_title    = $isEdit ? 'Edit Course' : 'Add Course';
$page_subtitle = $isEdit ? 'Update course details' : 'Create a new academic program';
$active        = 'courses';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-icon" href="<?= e(url('admin/courses/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <div>
        <div class="fw-semibold"><?= $isEdit ? e($course['name']) : 'New course' ?></div>
        <div class="text-muted small">Fields marked with * are required</div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Course details</div>
            <div class="card-body">
                <form method="post" action="<?= e($formAction) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Course code</label>
                            <input class="form-control" name="code" value="<?= e($course['code']) ?>" placeholder="e.g. BCA" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Course name</label>
                            <input class="form-control" name="name" value="<?= e($course['name']) ?>" placeholder="e.g. Bachelor of Computer Applications" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Duration (years)</label>
                            <input class="form-control" type="number" name="duration_years" min="1" max="6"
                                   value="<?= (int) $course['duration_years'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Total course fee</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= e(CURRENCY) ?></span>
                                <input class="form-control" type="number" name="total_fee" min="0" step="0.01"
                                       value="<?= e(number_format((float) $course['total_fee'], 2, '.', '')) ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?= e($course['description']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Save changes' : 'Create course' ?>
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/courses/index.php')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Good to know</div>
            <div class="card-body text-muted small">
                <p>The <strong>total course fee</strong> is used to calculate each student's pending balance in the fees module.</p>
                <p class="mb-0">A course can hold multiple subjects across semesters. Add them from the
                    <a href="<?= e(url('admin/courses/subjects.php')) ?>">Subjects</a> page.</p>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
