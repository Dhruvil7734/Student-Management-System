<?php
/**
 * Register a new administrator.
 * Open to the public only while no admin accounts exist; afterwards a
 * signed-in administrator is required.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$isFirstSetup = table_count('admins') === 0;
if (!$isFirstSetup && empty($_SESSION['admin_id'])) {
    flash('warning', 'Please log in as an administrator to create another account.');
    redirect('auth/login.php');
}

$errors = [];
$values = ['full_name' => '', 'username' => '', 'email' => ''];

if (is_post()) {
    verify_csrf();

    $values['full_name'] = post('full_name');
    $values['username']  = post('username');
    $values['email']     = post('email');
    $password            = post('password');
    $confirm             = post('confirm_password');

    if ($values['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $values['username'])) {
        $errors[] = 'Username must be 3–30 characters (letters, numbers, dot or underscore).';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
        $stmt->execute([$values['username']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'That username is already taken.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $values['username'],
            password_hash($password, PASSWORD_DEFAULT),
            $values['full_name'],
            nullable($values['email']),
        ]);

        flash('success', 'Administrator account created. You can now sign in.');
        redirect('auth/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create administrator &middot; <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="avatar avatar-lg mb-3"><i class="bi bi-person-plus-fill"></i></span>
                <h1 class="h4 fw-bold mb-1">Create administrator</h1>
                <p class="text-muted small mb-0">
                    <?= $isFirstSetup ? 'Set up the first administrator account for this system.' : 'Add another administrator account.' ?>
                </p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label required">Full name</label>
                    <input class="form-control" name="full_name" value="<?= e($values['full_name']) ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Username</label>
                        <input class="form-control" name="username" value="<?= e($values['username']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= e($values['email']) ?>">
                    </div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label required">Password</label>
                        <input class="form-control" type="password" name="password" minlength="8" required>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Confirm password</label>
                        <input class="form-control" type="password" name="confirm_password" minlength="8" required>
                    </div>
                </div>

                <button class="btn btn-primary w-100 mt-4" type="submit">
                    <i class="bi bi-check2-circle me-1"></i>Create account
                </button>
            </form>

            <p class="text-center small mt-4 mb-0">
                <a href="<?= e(url('auth/login.php')) ?>" class="text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Back to sign in
                </a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
