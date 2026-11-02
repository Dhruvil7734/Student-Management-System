<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/dashboard.php');
}

$error = '';
$username = '';

if (is_post()) {
    verify_csrf();
    $username = post('username');
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name']     = $admin['full_name'];

            db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);

            flash('success', 'Welcome back, ' . $admin['full_name'] . '!');
            redirect('admin/dashboard.php');
        }

        $error = 'Invalid username or password.';
        usleep(300000);
    }
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <section class="auth-hero">
        <div class="d-flex align-items-center gap-3">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <div class="brand-text">
                <strong class="text-white">Campus</strong>
                <small class="text-white-50">Management Suite</small>
            </div>
        </div>

        <div>
            <h2>Everything your campus needs,<br>in one place.</h2>
            <p>Manage students, teachers, courses, attendance, fees and results from a single secure dashboard.</p>
            <ul class="hero-features">
                <li><i class="bi bi-check-circle-fill"></i><span>Complete student &amp; staff records</span></li>
                <li><i class="bi bi-check-circle-fill"></i><span>Daily attendance with instant reports</span></li>
                <li><i class="bi bi-check-circle-fill"></i><span>Fee tracking and printable receipts</span></li>
                <li><i class="bi bi-check-circle-fill"></i><span>AI-powered academic insights</span></li>
            </ul>
        </div>

        <small class="text-white-50">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></small>
    </section>

    <section class="auth-form-side">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="avatar auth-avatar"><?= e(initials('System Administrator')) ?></span>
                <h1>Welcome back</h1>
                <p class="lead mb-0">Sign in to your administrator account</p>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label required" for="username">Username</label>
                    <div class="input-icon">
                        <i class="bi bi-person"></i>
                        <input class="form-control" id="username" name="username" type="text"
                               value="<?= e($username) ?>" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="password">Password</label>
                    <div class="input-icon">
                        <i class="bi bi-lock"></i>
                        <input class="form-control" id="password" name="password" type="password"
                               autocomplete="current-password" required>
                    </div>
                </div>

                <button class="btn btn-primary w-100 py-2" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
                </button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                Demo credentials: <code>admin</code> / <code>admin123</code>
            </p>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
