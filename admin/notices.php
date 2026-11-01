<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$errors = [];

if (is_post()) {
    verify_csrf();
    $action = post('action', 'add');

    if ($action === 'delete') {
        $id = post_int('id');
        db()->prepare('DELETE FROM notices WHERE id = ?')->execute([$id]);
        flash('success', 'Notice deleted.');
        redirect('admin/notices.php');
    }

    $title   = post('title');
    $message = post('message');

    if ($title === '') {
        $errors[] = 'A notice title is required.';
    }
    if ($message === '') {
        $errors[] = 'The notice message cannot be empty.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO notices (title, message, posted_by) VALUES (?, ?, ?)');
        $stmt->execute([$title, $message, $currentAdmin['id']]);
        flash('success', 'Notice published successfully.');
        redirect('admin/notices.php');
    }
}

$notices = db()->query(
    'SELECT n.*, a.full_name AS author
     FROM notices n LEFT JOIN admins a ON a.id = n.posted_by
     ORDER BY n.created_at DESC'
)->fetchAll();

$page_title    = 'Notices';
$page_subtitle = 'Publish announcements for the institution';
$active        = 'notices';
require dirname(__DIR__) . '/includes/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-megaphone me-2 text-primary"></i>Publish a notice</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label required">Title</label>
                        <input class="form-control" name="title" value="<?= e(post('title')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Message</label>
                        <textarea class="form-control" name="message" rows="6" required><?= e(post('message')) ?></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-send me-1"></i>Publish notice
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone-fill me-2 text-primary"></i>Published notices</span>
                <span class="badge text-bg-light border"><?= count($notices) ?></span>
            </div>
            <div class="card-body">
                <?php if (!$notices): ?>
                    <div class="empty-state">
                        <i class="bi bi-megaphone"></i>
                        <h6 class="mb-1">No notices yet</h6>
                        <p class="small mb-0">Publish your first announcement using the form.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($notices as $notice): ?>
                    <div class="notice-item">
                        <span class="dot"><i class="bi bi-megaphone"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <strong><?= e($notice['title']) ?></strong>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $notice['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Delete this notice?">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                            <p class="mb-1"><?= nl2br(e($notice['message'])) ?></p>
                            <small class="text-muted">
                                <?= format_date($notice['created_at'], 'd M Y, H:i') ?>
                                <?= $notice['author'] ? ' · by ' . e($notice['author']) : '' ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
