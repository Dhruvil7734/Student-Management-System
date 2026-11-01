<?php
/**
 * Global application configuration.
 * Adjust DB credentials in config/database.php if needed.
 */

declare(strict_types=1);

// ---------------------------------------------------------------- paths
define('APP_NAME', 'Student Management System');
define('APP_ROOT', dirname(__DIR__));

// Base URL is calculated from the document root so the app works in any
// sub-folder of htdocs (including folders with spaces).
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
$appRoot = realpath(APP_ROOT);
$baseUrl = '';
if ($docRoot && $appRoot && stripos(str_replace('\\', '/', $appRoot), str_replace('\\', '/', $docRoot)) === 0) {
    $baseUrl = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
}
define('BASE_URL', rtrim($baseUrl, '/'));

// ---------------------------------------------------------------- app
define('TIMEZONE', 'Asia/Kolkata');
define('CURRENCY', '₹');
define('ROWS_PER_PAGE', 10);
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

date_default_timezone_set(TIMEZONE);

// ---------------------------------------------------------------- errors
// Set to false in production.
define('APP_DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// ---------------------------------------------------------------- session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SMS_SESSION');
    session_start();
}
