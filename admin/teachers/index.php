<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$q = query('q');

$whereSql = '';
$params   = [];
if ($q !== '') {
    $whereSql = ' WHERE (t.full_name LIKE ? OR t.employee_id LIKE ? OR t.email LIKE ? OR t.qualification LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM teachers t' . $whereSql);
$countStmt->execute($params);
$p = paginate((int) $countStmt->fetchColumn());

$sql = 'SELECT t.*, COUNT(su.id) AS subject_count
        FROM teachers t
        LEFT JOIN subjects su ON su.teacher_id = t.id' . $whereSql . '
        GROUP BY t.id
        ORDER BY t.full_name
        LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

$page_title    = 'Teachers';
$page_subtitle = 'Manage teaching staff and subject assignments';
$active        = 'teachers';
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card table-card">
    <div class="card-header">
        <form class="toolbar mb-0" method="get">
            <div class="search-box input-icon">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, ID, email or qualification…">
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($q !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/teachers/index.php')) ?>">Reset</a>
            <?php endif; ?>
            <a class="btn btn-soft ms-auto" href="<?= e(url('admin/teachers/create.php')) ?>">
                <i class="bi bi-plus-lg me-1"></i>Add teacher
            </a>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Qualification</th>
                    <th>Phone</th>
                    <th>Subjects</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$teachers): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-person-video3"></i>
                                <h6 class="mb-1">No teachers found</h6>
                                <p class="small mb-3">Add teaching staff to assign them to subjects.</p>
                                <a class="btn btn-primary btn-sm" href="<?= e(url('admin/teachers/create.php')) ?>">Add teacher</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td>
                            <div class="person-cell">
                                <?php if ($teacher['photo']): ?>
                                    <img class="avatar avatar-md" src="<?= e(url($teacher['photo'])) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar avatar-md"><?= e(initials($teacher['full_name'])) ?></span>
                                <?php endif; ?>
                                <div class="meta">
                                    <strong><?= e($teacher['full_name']) ?></strong>
                                    <small><?= e($teacher['employee_id']) ?> &middot; <?= e($teacher['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($teacher['qualification'] ?: '—') ?></td>
                        <td class="text-muted"><?= e($teacher['phone'] ?: '—') ?></td>
                        <td><span class="badge text-bg-light border"><?= (int) $teacher['subject_count'] ?> subject(s)</span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/teachers/view.php?id=' . $teacher['id'])) ?>" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a class="btn btn-sm btn-soft" href="<?= e(url('admin/teachers/edit.php?id=' . $teacher['id'])) ?>" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form class="d-inline" method="post" action="<?= e(url('admin/teachers/delete.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-confirm="Remove <?= e($teacher['full_name']) ?> from the teaching staff?">
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
            <small class="text-muted"><?= number_format((int) $p['total']) ?> teachers</small>
            <?php render_pagination($p); ?>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
