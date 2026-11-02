<?php
/**
 * Shared helper functions.
 */

declare(strict_types=1);

// ---------------------------------------------------------------- output
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function current_url(): string
{
    return $_SERVER['REQUEST_URI'] ?? url('admin/dashboard.php');
}

// ---------------------------------------------------------------- flash
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------- request
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (int) $_POST[$key] : $default;
}

function post_float(string $key, float $default = 0): float
{
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (float) $_POST[$key] : $default;
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && $_GET[$key] !== '' ? (int) $_GET[$key] : $default;
}

function nullable(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('danger', 'Your security token expired. Please try again.');
        header('Location: ' . current_url());
        exit;
    }
}

// ---------------------------------------------------------------- uploads
/**
 * Validate and store an uploaded profile photo.
 *
 * @return array{0:?string,1:?string} [relative path, error message]
 */
function upload_photo(array $file, string $subdir = 'students'): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The photo could not be uploaded (error code ' . $file['error'] . ').'];
    }
    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        return [null, 'The photo must be smaller than 2 MB.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        return [null, 'Only JPG, PNG or WEBP images are allowed.'];
    }
    if (getimagesize($file['tmp_name']) === false) {
        return [null, 'The uploaded file is not a valid image.'];
    }

    $relativeDir = 'assets/images/' . $subdir;
    $absoluteDir = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        return [null, 'Could not create the upload directory.'];
    }

    $filename = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $target   = $absoluteDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [null, 'Could not save the uploaded photo.'];
    }

    return [$relativeDir . '/' . $filename, null];
}

function delete_photo(?string $relativePath): void
{
    if (!$relativePath || strpos($relativePath, 'assets/images/') !== 0) {
        return;
    }
    $absolute = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

// ---------------------------------------------------------------- pagination
/**
 * @return array{page:int,pages:int,offset:int,perPage:int,total:int}
 */
function paginate(int $total, ?int $perPage = null): array
{
    $perPage = $perPage ?? ROWS_PER_PAGE;
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($pages, query_int('page', 1)));

    return [
        'page'    => $page,
        'pages'   => $pages,
        'offset'  => ($page - 1) * $perPage,
        'perPage' => $perPage,
        'total'   => $total,
    ];
}

function page_url(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

function render_pagination(array $p): void
{
    if ($p['pages'] <= 1) {
        return;
    }

    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);

    echo '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    echo '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(page_url(max(1, $p['page'] - 1))) . '">&laquo;</a></li>';

    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url(1)) . '">1</a></li>';
        if ($start > 2) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e(page_url($i)) . '">' . $i . '</a></li>';
    }

    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url($p['pages'])) . '">'
            . $p['pages'] . '</a></li>';
    }

    echo '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(page_url(min($p['pages'], $p['page'] + 1))) . '">&raquo;</a></li>';
    echo '</ul></nav>';
}

// ---------------------------------------------------------------- formatting
function money($amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

function short_money($amount): string
{
    return CURRENCY . number_format((float) $amount, 0);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= strtoupper(substr($part, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($a, $b): string
{
    return (string) $a === (string) $b ? ' checked' : '';
}

function calculate_grade(float $percentage): string
{
    return match (true) {
        $percentage >= 90 => 'A+',
        $percentage >= 80 => 'A',
        $percentage >= 70 => 'B+',
        $percentage >= 60 => 'B',
        $percentage >= 50 => 'C',
        $percentage >= 40 => 'D',
        default           => 'F',
    };
}

function status_badge(string $status): string
{
    $map = [
        'Active'    => 'success',
        'Inactive'  => 'secondary',
        'Graduated' => 'info',
        'Present'   => 'success',
        'Absent'    => 'danger',
        'Late'      => 'warning',
        'Paid'      => 'success',
        'Pending'   => 'danger',
        'Pass'      => 'success',
        'Fail'      => 'danger',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $class . ' badge-soft">' . e($status) . '</span>';
}

// ---------------------------------------------------------------- domain helpers
function get_courses(): array
{
    static $courses = null;
    if ($courses === null) {
        $courses = db()->query('SELECT * FROM courses ORDER BY name')->fetchAll();
    }
    return $courses;
}

function get_teachers(): array
{
    static $teachers = null;
    if ($teachers === null) {
        $teachers = db()->query('SELECT * FROM teachers ORDER BY full_name')->fetchAll();
    }
    return $teachers;
}

function course_name(?int $id): string
{
    foreach (get_courses() as $course) {
        if ((int) $course['id'] === (int) $id) {
            return $course['name'];
        }
    }
    return '—';
}

function course_fee(?int $id): float
{
    foreach (get_courses() as $course) {
        if ((int) $course['id'] === (int) $id) {
            return (float) $course['total_fee'];
        }
    }
    return 0.0;
}

function teacher_name(?int $id): string
{
    foreach (get_teachers() as $teacher) {
        if ((int) $teacher['id'] === (int) $id) {
            return $teacher['full_name'];
        }
    }
    return '—';
}

function attendance_summary(int $studentId): array
{
    $stmt = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "Present") AS present,
            SUM(status = "Late")    AS late,
            SUM(status = "Absent")  AS absent
         FROM attendance WHERE student_id = ?'
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch() ?: ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];

    $total = (int) $row['total'];
    $attended = (int) $row['present'] + (int) $row['late'];

    return [
        'total'      => $total,
        'present'    => (int) $row['present'],
        'late'       => (int) $row['late'],
        'absent'     => (int) $row['absent'],
        'percentage' => $total > 0 ? round($attended / $total * 100, 1) : 0.0,
    ];
}

function student_fee_summary(int $studentId, int $courseId): array
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id = ?');
    $stmt->execute([$studentId]);
    $paid = (float) $stmt->fetchColumn();

    $total = course_fee($courseId);
    $pending = max(0, $total - $paid);

    return [
        'total'   => $total,
        'paid'    => $paid,
        'pending' => $pending,
        'percent' => $total > 0 ? min(100, round($paid / $total * 100, 1)) : 0.0,
    ];
}

function generate_receipt_no(): string
{
    do {
        $receipt = 'RCPT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = db()->prepare('SELECT COUNT(*) FROM fees WHERE receipt_no = ?');
        $stmt->execute([$receipt]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $receipt;
}

function table_count(string $table, string $where = '', array $params = []): int
{
    $allowed = ['admins', 'students', 'teachers', 'courses', 'subjects', 'attendance', 'fees', 'examinations', 'results', 'notices'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Return a list of [1..8] for semester selects. */
function semesters(): array
{
    return range(1, 8);
}
