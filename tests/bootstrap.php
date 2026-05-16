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

// Buffer all output for the whole test run so session_regenerate_id()
// (which checks headers_sent()) still works after PASS/FAIL prints.
// We flush manually after each test() result inside the runner.
if (!ob_get_level()) {
    ob_start();
}

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

// Load Settings (lazy — only queries the DB when get() is called)
require_once LIB_PATH_INC . 'settings.php';
require_once LIB_PATH_INC . 'formatcurrency.php';

// Session stub for CLI testing (no HTTP headers)
if (php_sapi_name() === 'cli') {
    $_SESSION = [];
}

// Seed org_members if empty (for tests that need org membership)
if (!getenv('TESTS_NO_DB')) {
    $existing_members = $db->prepare_select('SELECT COUNT(*) as cnt FROM org_members', '');
    if (!$existing_members || $existing_members[0]['cnt'] == 0) {
        // Check if default org exists
        $default_org = $db->prepare_select_one(
            "SELECT id FROM orgs WHERE slug = 'default' LIMIT 1", ''
        );
        if (!$default_org) {
            // Create default org
            $db->prepare_query(
                "INSERT INTO orgs (id, name, slug, deleted_at) VALUES (1, 'Default Organization', 'default', NULL)",
                ''
            );
        }
        // Seed org_members for all active users
        $db->prepare_query(
            "INSERT INTO org_members (org_id, user_id, joined_at, role)
             SELECT 1, u.id, NOW(), CASE u.user_level WHEN 1 THEN 'owner' WHEN 2 THEN 'admin' ELSE 'member' END
             FROM users u WHERE u.deleted_at IS NULL AND NOT EXISTS (
                 SELECT 1 FROM org_members om WHERE om.user_id = u.id AND om.org_id = 1
             )",
            ''
        );
    }
}

echo "Test bootstrap loaded.\n";
if (!getenv('TESTS_NO_DB')) {
    echo "DB connection: OK\n";
} else {
    echo "DB connection: SKIPPED (TESTS_NO_DB=1)\n";
}
