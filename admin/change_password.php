<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$errors = [];

if (is_post()) {
    verify_csrf();

    $current = post('current_password');
    $new     = post('new_password');
    $confirm = post('confirm_password');

    $stmt = db()->prepare('SELECT password FROM admins WHERE id = ?');
    $stmt->execute([$currentAdmin['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters long.';
    }
    if ($new === $current) {
        $errors[] = 'The new password must be different from the current one.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE admins SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $currentAdmin['id']]);
        flash('success', 'Your password has been updated.');
        redirect('admin/change_password.php');
    }
}

$page_title    = 'Change Password';
$page_subtitle = 'Keep your administrator account secure';
$active        = '';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>Update your password</div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" style="max-width: 460px;">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required">Current password</label>
                        <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">New password</label>
                        <input class="form-control" type="password" name="new_password" minlength="8" required autocomplete="new-password">
                        <div class="form-text">At least 8 characters. Use a mix of letters, numbers and symbols.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Confirm new password</label>
                        <input class="form-control" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Save password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Account details</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="avatar avatar-lg"><?= e(initials($currentAdmin['full_name'])) ?></span>
                    <div>
                        <div class="fw-bold"><?= e($currentAdmin['full_name']) ?></div>
                        <div class="text-muted small">@<?= e($currentAdmin['username']) ?></div>
                    </div>
                </div>
                <ul class="info-list">
                    <li><span class="label">Role</span><span class="value">Administrator</span></li>
                    <li><span class="label">Session</span><span class="value">Secure</span></li>
                    <li><span class="label">Signed in</span><span class="value"><?= date('d M Y, H:i') ?></span></li>
                </ul>
                <a class="btn btn-soft w-100 mt-3" href="<?= e(url('auth/register.php')) ?>">
                    <i class="bi bi-person-plus me-1"></i>Add another administrator
                </a>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
