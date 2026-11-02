<?php
/**
 * Sidebar navigation. Uses $active set by each page.
 */
$active = $active ?? '';

$nav = [
    'Main' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid-1x2-fill', 'href' => 'admin/dashboard.php'],
    ],
    'Academics' => [
        ['key' => 'students', 'label' => 'Students',    'icon' => 'people-fill',       'href' => 'admin/students/index.php'],
        ['key' => 'teachers', 'label' => 'Teachers',    'icon' => 'person-video3',     'href' => 'admin/teachers/index.php'],
        ['key' => 'courses',  'label' => 'Courses',     'icon' => 'journal-bookmark-fill', 'href' => 'admin/courses/index.php'],
        ['key' => 'subjects', 'label' => 'Subjects',    'icon' => 'book-half',         'href' => 'admin/courses/subjects.php'],
    ],
    'Operations' => [
        ['key' => 'attendance',   'label' => 'Attendance',   'icon' => 'calendar2-check-fill', 'href' => 'admin/attendance/index.php'],
        ['key' => 'fees',         'label' => 'Fees',         'icon' => 'wallet-fill',          'href' => 'admin/fees/index.php'],
        ['key' => 'examinations', 'label' => 'Examinations', 'icon' => 'clipboard2-check-fill','href' => 'admin/examinations/index.php'],
        ['key' => 'results',      'label' => 'Results',      'icon' => 'bar-chart-fill',       'href' => 'admin/examinations/results.php'],
    ],
    'Insights' => [
        ['key' => 'reports',   'label' => 'Reports',       'icon' => 'file-earmark-bar-graph-fill', 'href' => 'admin/reports/index.php'],
        ['key' => 'assistant', 'label' => 'AI Assistant',  'icon' => 'stars',                        'href' => 'ai/assistant.php'],
        ['key' => 'notices',   'label' => 'Notices',       'icon' => 'megaphone-fill',               'href' => 'admin/notices.php'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
        <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="brand-text">
            <strong>Campus</strong>
            <small>Management Suite</small>
        </span>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($nav as $group => $items): ?>
            <p class="nav-label"><?= e($group) ?></p>
            <?php foreach ($items as $item): ?>
                <a class="nav-link<?= $active === $item['key'] ? ' active' : '' ?>"
                   href="<?= e(url($item['href'])) ?>">
                    <i class="bi bi-<?= e($item['icon']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-tip">
            <i class="bi bi-shield-check"></i>
            <div>
                <strong>Secure session</strong>
                <small>Signed in as @<?= e($currentAdmin['username']) ?></small>
            </div>
        </div>
    </div>
</aside>
