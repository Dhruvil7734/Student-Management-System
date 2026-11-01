<?php
/**
 * Shared student create/edit form.
 * Expects: $student, $errors, $formAction, $isEdit
 */
$page_title    = $isEdit ? 'Edit Student' : 'Add Student';
$page_subtitle = $isEdit ? 'Update the student record' : 'Register a new student';
$active        = 'students';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-icon" href="<?= e(url('admin/students/index.php')) ?>" title="Back"><i class="bi bi-arrow-left"></i></a>
    <div>
        <div class="fw-semibold"><?= $isEdit ? e($student['full_name']) : 'New student registration' ?></div>
        <div class="text-muted small">Fields marked with * are required</div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-person-vcard me-2 text-primary"></i>Personal information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Student ID</label>
                            <input class="form-control" name="student_id" value="<?= e($student['student_id']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Full name</label>
                            <input class="form-control" name="full_name" value="<?= e($student['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email address</label>
                            <input class="form-control" type="email" name="email" value="<?= e($student['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone number</label>
                            <input class="form-control" name="phone" value="<?= e($student['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">— Select —</option>
                                <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                                    <option value="<?= $gender ?>"<?= selected($student['gender'], $gender) ?>><?= $gender ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of birth</label>
                            <input class="form-control" type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($student['dob']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?= e($student['address']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-mortarboard me-2 text-primary"></i>Academic details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label required">Course</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">— Select course —</option>
                                <?php foreach (get_courses() as $course): ?>
                                    <option value="<?= (int) $course['id'] ?>"<?= selected($student['course_id'], $course['id']) ?>>
                                        <?= e($course['name']) ?> (<?= e($course['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Semester</label>
                            <select class="form-select" name="semester" required>
                                <?php foreach (semesters() as $semester): ?>
                                    <option value="<?= $semester ?>"<?= selected($student['semester'], $semester) ?>>Semester <?= $semester ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Admission date</label>
                            <input class="form-control" type="date" name="admission_date" value="<?= e($student['admission_date']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach (['Active', 'Inactive', 'Graduated'] as $status): ?>
                                    <option value="<?= $status ?>"<?= selected($student['status'], $status) ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-camera me-2 text-primary"></i>Profile photo</div>
                <div class="card-body text-center">
                    <?php if (!empty($student['photo'])): ?>
                        <img class="avatar avatar-xl mb-3" src="<?= e(url($student['photo'])) ?>" alt="Student photo" id="photoPreview">
                    <?php else: ?>
                        <div class="avatar avatar-xl mb-3 mx-auto" id="photoFallback"><?= e(initials($student['full_name'] ?: 'New Student')) ?></div>
                        <img class="avatar avatar-xl mb-3 d-none" src="" alt="Preview" id="photoPreview">
                    <?php endif; ?>

                    <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           data-preview-target="#photoPreview">
                    <div class="form-text">JPG, PNG or WEBP. Max 2 MB.</div>

                    <?php if (!empty($student['photo'])): ?>
                        <div class="form-check text-start mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_photo" id="remove_photo" value="1">
                            <label class="form-check-label small" for="remove_photo">Remove current photo</label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Save changes' : 'Register student' ?>
                </button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/students/index.php')) ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
