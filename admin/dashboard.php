<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = db();

// ---------------------------------------------------------------- headline stats
$totalStudents = table_count('students');
$activeStudents = table_count('students', "status = 'Active'");
$totalTeachers = table_count('teachers');
$totalCourses  = table_count('courses');
$totalSubjects = table_count('subjects');

$collected = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM fees')->fetchColumn();
$expected  = (float) $pdo->query(
    "SELECT COALESCE(SUM(c.total_fee),0)
     FROM students s JOIN courses c ON c.id = s.course_id
     WHERE s.status = 'Active'"
)->fetchColumn();
$pending = max(0, $expected - $collected);

// ---------------------------------------------------------------- today's attendance
$stmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "Present") AS present,
        SUM(status = "Absent")  AS absent,
        SUM(status = "Late")    AS late
     FROM attendance WHERE attendance_date = CURDATE()'
);
$stmt->execute();
$today = $stmt->fetch() ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
$todayPct = (int) $today['total'] > 0
    ? round(((int) $today['present'] + (int) $today['late']) / (int) $today['total'] * 100, 1)
    : 0;

// ---------------------------------------------------------------- recent students
$recentStudents = $pdo->query(
    "SELECT s.*, c.name AS course_name
     FROM students s
     JOIN courses c ON c.id = s.course_id
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 5"
)->fetchAll();

// ---------------------------------------------------------------- notices
$notices = $pdo->query('SELECT * FROM notices ORDER BY created_at DESC LIMIT 3')->fetchAll();

// ---------------------------------------------------------------- chart data: attendance last 7 days
$attendanceRows = $pdo->query(
    "SELECT attendance_date,
            SUM(status = 'Present') AS present,
            SUM(status = 'Absent')  AS absent,
            SUM(status = 'Late')    AS late
     FROM attendance
     WHERE attendance_date >= CURDATE() - INTERVAL 6 DAY
     GROUP BY attendance_date
     ORDER BY attendance_date"
)->fetchAll();

$attendanceByDate = [];
foreach ($attendanceRows as $row) {
    $attendanceByDate[$row['attendance_date']] = $row;
}

$chartLabels = [];
$chartPresent = [];
$chartAbsent = [];
$chartLate = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('D', strtotime($date));
    $chartPresent[] = (int) ($attendanceByDate[$date]['present'] ?? 0);
    $chartAbsent[]  = (int) ($attendanceByDate[$date]['absent'] ?? 0);
    $chartLate[]    = (int) ($attendanceByDate[$date]['late'] ?? 0);
}

// ---------------------------------------------------------------- chart data: students per course
$courseRows = $pdo->query(
    'SELECT c.name, COUNT(s.id) AS total
     FROM courses c LEFT JOIN students s ON s.course_id = c.id
     GROUP BY c.id, c.name ORDER BY c.name'
)->fetchAll();

$page_title    = 'Dashboard';
$page_subtitle = 'Overview of your institution at a glance';
$active        = 'dashboard';
$use_charts    = true;
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4 mb-1">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-indigo"><i class="bi bi-people-fill"></i></div>
            <div class="stat-label">Total Students</div>
            <p class="stat-value"><?= number_format($totalStudents) ?></p>
            <div class="stat-meta"><i class="bi bi-dot"></i><?= number_format($activeStudents) ?> active</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-violet"><i class="bi bi-person-video3"></i></div>
            <div class="stat-label">Teachers</div>
            <p class="stat-value"><?= number_format($totalTeachers) ?></p>
            <div class="stat-meta"><i class="bi bi-dot"></i><?= number_format($totalSubjects) ?> subjects assigned</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-teal"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div class="stat-label">Courses</div>
            <p class="stat-value"><?= number_format($totalCourses) ?></p>
            <div class="stat-meta"><i class="bi bi-dot"></i><?= number_format($totalSubjects) ?> subjects</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-green"><i class="bi bi-calendar2-check-fill"></i></div>
            <div class="stat-label">Today's Attendance</div>
            <p class="stat-value"><?= number_format($todayPct) ?><small class="fs-6 fw-normal text-muted">%</small></p>
            <div class="stat-meta">
                <i class="bi bi-dot"></i><?= (int) $today['total'] ?> marked &middot;
                <?= (int) $today['present'] ?> present
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-1">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-indigo"><i class="bi bi-wallet2"></i></div>
            <div class="stat-label">Fees Collected</div>
            <p class="stat-value"><?= short_money($collected) ?></p>
            <div class="stat-meta"><?= short_money($expected) ?> expected</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-rose"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-label">Pending Fees</div>
            <p class="stat-value"><?= short_money($pending) ?></p>
            <div class="stat-meta"><?= $expected > 0 ? round($collected / $expected * 100, 1) : 0 ?>% collected</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-amber"><i class="bi bi-journal-check"></i></div>
            <div class="stat-label">Examinations</div>
            <p class="stat-value"><?= number_format(table_count('examinations')) ?></p>
            <div class="stat-meta"><?= number_format(table_count('results')) ?> results recorded</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-glow"></span>
            <div class="stat-icon tone-teal"><i class="bi bi-lightning-charge-fill"></i></div>
            <div class="stat-label">Quick Actions</div>
            <div class="d-grid gap-2 mt-2">
                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/students/create.php')) ?>">
                    <i class="bi bi-plus-lg me-1"></i>Add student
                </a>
                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/attendance/index.php')) ?>">
                    <i class="bi bi-check2-square me-1"></i>Mark attendance
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Attendance — last 7 days</span>
                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/reports/index.php')) ?>">Reports</a>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Students by course</div>
            <div class="card-body">
                <canvas id="courseChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-plus-fill me-2 text-primary"></i>Recent registrations</span>
                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/index.php')) ?>">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Student</th><th>Course</th><th>Semester</th><th>Admitted</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentStudents): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No students registered yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentStudents as $student): ?>
                            <tr>
                                <td>
                                    <div class="person-cell">
                                        <span class="avatar avatar-sm"><?= e(initials($student['full_name'])) ?></span>
                                        <div class="meta">
                                            <strong><?= e($student['full_name']) ?></strong>
                                            <small><?= e($student['student_id']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($student['course_name']) ?></td>
                                <td>Sem <?= (int) $student['semester'] ?></td>
                                <td class="text-muted"><?= format_date($student['admission_date']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-soft" href="<?= e(url('admin/students/view.php?id=' . $student['id'])) ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone-fill me-2 text-primary"></i>Notices</span>
                <a class="btn btn-sm btn-soft" href="<?= e(url('admin/notices.php')) ?>">Manage</a>
            </div>
            <div class="card-body">
                <?php if (!$notices): ?>
                    <p class="text-muted small mb-0">No notices published yet.</p>
                <?php endif; ?>
                <?php foreach ($notices as $notice): ?>
                    <div class="notice-item">
                        <span class="dot"><i class="bi bi-megaphone"></i></span>
                        <div>
                            <strong class="d-block"><?= e($notice['title']) ?></strong>
                            <p class="mb-1"><?= e(mb_strimwidth($notice['message'], 0, 110, '…')) ?></p>
                            <small class="text-muted"><?= format_date($notice['created_at'], 'd M Y') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
    window.smsChart('attendanceChart', {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                { label: 'Present', data: <?= json_encode($chartPresent) ?>, backgroundColor: '#4f46e5', borderRadius: 6, maxBarThickness: 26 },
                { label: 'Late',    data: <?= json_encode($chartLate) ?>,    backgroundColor: '#f59e0b', borderRadius: 6, maxBarThickness: 26 },
                { label: 'Absent',  data: <?= json_encode($chartAbsent) ?>,  backgroundColor: '#f43f5e', borderRadius: 6, maxBarThickness: 26 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2f7' } }
            }
        }
    });

    window.smsChart('courseChart', {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($courseRows, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($courseRows, 'total'))) ?>,
                backgroundColor: ['#4f46e5', '#8b5cf6', '#06b6d4', '#f59e0b', '#10b981', '#f43f5e'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } }
        }
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
