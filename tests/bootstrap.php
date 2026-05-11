<?php
/**
 * tests/bootstrap.php
 *
 * Test harness bootstrap — sets up constants and loads project files.
 *
 * Set TESTS_NO_DB=1 to skip database-dependent tests.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define path constants (mirrors load.php)
define('URL_SEPARATOR', '/');
define('DS', DIRECTORY_SEPARATOR);
define('SITE_ROOT', realpath(__DIR__ . '/../includes'));
define('LIB_PATH_INC', SITE_ROOT . DS);

// Load config (this also loads .env if available)
require_once LIB_PATH_INC . 'config.php';

// Load database (creates $db) — only if not skipping
if (!getenv('TESTS_NO_DB')) {
    require_once LIB_PATH_INC . 'database.php';
}

// Load functions (CSRF, h(), etc.)
require_once LIB_PATH_INC . 'functions.php';

// Load session class
require_once LIB_PATH_INC . 'session.php';

// Load SQL helpers (requires $db from database.php)
if (!getenv('TESTS_NO_DB')) {
    require_once LIB_PATH_INC . 'sql.php';
}

// Session stub for CLI testing (no HTTP headers)
if (php_sapi_name() === 'cli') {
    $_SESSION = [];
}

echo "Test bootstrap loaded.\n";
if (!getenv('TESTS_NO_DB')) {
    echo "DB connection: OK\n";
} else {
    echo "DB connection: SKIPPED (TESTS_NO_DB=1)\n";
}
