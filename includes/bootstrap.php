<?php
/**
 * Common bootstrap for every page:
 * configuration, database connection and helper functions.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
