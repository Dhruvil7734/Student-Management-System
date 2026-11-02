<?php
/**
 * Shared teacher create/edit form.
 * Expects: $teacher, $errors, $formAction, $isEdit
 */
$page_title    = $isEdit ? 'Edit Teacher' : 'Add Teacher';
$page_subtitle = $isEdit ? 'Update the teacher record' : 'Register a new teacher';
$active        = 'teachers';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-icon" href="<?= e(url('admin/teachers/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <div>
        <div class="fw-semibold"><?= $isEdit ? e($teacher['full_name']) : 'New teacher registration' ?></div>
        <div class="text-muted small">Fields marked with * are required</div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-person-vcard me-2 text-primary"></i>Teacher information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Employee ID</label>
                            <input class="form-control" name="employee_id" value="<?= e($teacher['employee_id']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Full name</label>
                            <input class="form-control" name="full_name" value="<?= e($teacher['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email address</label>
                            <input class="form-control" type="email" name="email" value="<?= e($teacher['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone number</label>
                            <input class="form-control" name="phone" value="<?= e($teacher['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">— Select —</option>
                                <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                                    <option value="<?= $gender ?>"<?= selected($teacher['gender'], $gender) ?>><?= $gender ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of birth</label>
                            <input class="form-control" type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($teacher['dob']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Qualification</label>
                            <input class="form-control" name="qualification" value="<?= e($teacher['qualification']) ?>" placeholder="e.g. M.Sc. Mathematics">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?= e($teacher['address']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-camera me-2 text-primary"></i>Profile photo</div>
                <div class="card-body text-center">
                    <?php if (!empty($teacher['photo'])): ?>
                        <img class="avatar avatar-xl mb-3" src="<?= e(url($teacher['photo'])) ?>" alt="Teacher photo" id="photoPreview">
                    <?php else: ?>
                        <div class="avatar avatar-xl mb-3 mx-auto" id="photoFallback"><?= e(initials($teacher['full_name'] ?: 'New Teacher')) ?></div>
                        <img class="avatar avatar-xl mb-3 d-none" src="" alt="Preview" id="photoPreview">
                    <?php endif; ?>

                    <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           data-preview-target="#photoPreview">
                    <div class="form-text">JPG, PNG or WEBP. Max 2 MB.</div>

                    <?php if (!empty($teacher['photo'])): ?>
                        <div class="form-check text-start mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_photo" id="remove_photo" value="1">
                            <label class="form-check-label small" for="remove_photo">Remove current photo</label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Save changes' : 'Add teacher' ?>
                </button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/teachers/index.php')) ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
