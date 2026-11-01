<?php
/**
 * PDO database connection (singleton).
 */

declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'student_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Return the shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        $message = APP_DEBUG ? $e->getMessage() : 'Unable to connect to the database.';
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database Error</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
            . '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger shadow-sm">'
            . '<h4 class="alert-heading">Database connection failed</h4>'
            . '<p class="mb-2">' . htmlspecialchars($message) . '</p>'
            . '<hr><p class="mb-0 small">Make sure MySQL is running in XAMPP and that '
            . '<code>database/student_management.sql</code> has been imported.</p>'
            . '</div></div></body></html>';
        exit;
    }

    return $pdo;
}
